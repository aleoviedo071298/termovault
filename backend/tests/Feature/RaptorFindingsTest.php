<?php

namespace Tests\Feature;

use App\Models\Archivo;
use App\Models\Criticidad;
use App\Models\Elemento;
use App\Models\Empresa;
use App\Models\Inspeccion;
use App\Models\Novedad;
use App\Models\TipoElemento;
use App\Models\Usuario;
use App\Models\Yacimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests para los hallazgos identificados por Raptor (auditoría estática).
 *
 * [M-01] ArchivoController fail-closed para técnico sin yacimientos.
 * [M-02] Validación per-item de novedades + count limit + max lengths.
 */
class RaptorFindingsTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Yacimiento $yacimiento;
    private TipoElemento $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

        foreach (['admin', 'supervisor', 'tecnico'] as $code) {
            \App\Models\Role::firstOrCreate(['codigo' => $code], ['nombre' => $code]);
        }

        $this->empresa = Empresa::factory()->create(['nombre' => 'CAPSA']);
        $this->yacimiento = Yacimiento::factory()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'YAC-A',
            'permite_supervisor_elementos' => true,
        ]);
        $this->tipo = TipoElemento::factory()->create(['codigo' => 'subestacion']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // [M-01] — Técnico desasignado NO debe poder descargar sus archivos viejos
    // ──────────────────────────────────────────────────────────────────────

    public function test_m01_revoked_tecnico_cannot_download_own_old_files(): void
    {
        // Técnico crea inspección + archivo asociado mientras está asignado.
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id);

        $elemento = Elemento::factory()->create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
        ]);

        $inspeccion = Inspeccion::factory()->create([
            'elemento_id' => $elemento->id,
            'tecnico_id' => $tecnico->id,
        ]);

        $archivo = Archivo::create([
            'inspeccion_id' => $inspeccion->id,
            'tipo' => 'termografia_is2',
            'nombre_original' => 'test.is2',
            's3_bucket' => 'test-bucket',
            's3_key' => 'inspecciones/' . $inspeccion->id . '/termografias/' . uniqid() . '-test.is2',
            'tamano_bytes' => 1024,
            'mime_type' => 'application/octet-stream',
            'subido_por' => $tecnico->id,
        ]);

        // Verificación previa: con asignación activa, puede descargar (sería 200 si hubiera archivo en S3 fake)
        // El test es del scope check, no del contenido — esperamos 200 o 404 por archivo no presente, NO 404 por scope.
        // Para evitar dependencia del Storage::disk fake, solo testeamos el caso bloqueado.

        // ACCIÓN: admin revoca todas las asignaciones de yacimiento del técnico
        $tecnico->yacimientos()->detach();
        $tecnico->refresh();
        $this->assertCount(0, $tecnico->yacimientos, 'precondición: técnico ya no tiene yacimientos asignados');

        // El técnico desasignado intenta descargar su archivo viejo
        $response = $this->actingAs($tecnico)
            ->getJson("/api/archivos/{$archivo->id}/download");

        // Antes del fix: 200 + file stream (fail-open).
        // Después del fix: 404 (fail-closed, indistinguible de "no existe").
        $this->assertSame(404, $response->status(), 'técnico revocado NO debe poder descargar su archivo viejo');
    }

    public function test_m01_tecnico_with_active_assignment_can_still_access_their_files(): void
    {
        // Garantizamos que el fix no rompe el caso legítimo: técnico ACTIVO sí puede.
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id);

        $elemento = Elemento::factory()->create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
        ]);
        $inspeccion = Inspeccion::factory()->create([
            'elemento_id' => $elemento->id,
            'tecnico_id' => $tecnico->id,
        ]);

        // Subimos un archivo real al storage fake con magic bytes válidos (.is2 = PK)
        $disk = Storage::disk('s3');
        $key = 'inspecciones/' . $inspeccion->id . '/termografias/' . uniqid() . '-test.is2';
        $disk->put($key, "PK\x03\x04" . str_repeat("\0", 100));

        $archivo = Archivo::create([
            'inspeccion_id' => $inspeccion->id,
            'tipo' => 'termografia_is2',
            'nombre_original' => 'test.is2',
            's3_bucket' => 'test-bucket',
            's3_key' => $key,
            'tamano_bytes' => 104,
            'mime_type' => 'application/octet-stream',
            'subido_por' => $tecnico->id,
        ]);

        $response = $this->actingAs($tecnico)
            ->get("/api/archivos/{$archivo->id}/download");

        // Status 200 (file stream) — no 404, no 401, no 403.
        $code = $response->getStatusCode();
        $this->assertNotSame(404, $code, 'técnico activo SÍ debe poder descargar');
        $this->assertNotSame(403, $code);
    }

    // ──────────────────────────────────────────────────────────────────────
    // [M-02] — Validación per-item de novedades
    // ──────────────────────────────────────────────────────────────────────

    public function test_m02_rejects_more_than_50_novedades_in_one_inspection(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id);
        $elemento = Elemento::factory()->create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
        ]);

        // 51 hallazgos en una sola inspección → debe rechazarse con 422
        $novedades = array_fill(0, 51, ['titulo' => 'X', 'descripcion' => 'y']);

        $response = $this->actingAs($tecnico)->postJson('/api/inspecciones', [
            'elemento_id' => $elemento->id,
            'fecha_inspeccion' => now()->format('Y-m-d'),
            'termografias' => [UploadedFile::fake()->create('t.is2', 30)],
            'novedades' => json_encode($novedades),
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Máximo 50 hallazgos por inspección.']);
        $this->assertSame(0, Novedad::count(), 'no debe haberse creado ninguna novedad');
    }

    public function test_m02_truncates_oversized_titulo_descripcion_etc(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id);
        $elemento = Elemento::factory()->create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
        ]);

        // titulo ~ 1KB, descripcion ~ 8KB, ubicacion ~ 2KB, accion ~ 4KB
        $novedades = [[
            'titulo' => str_repeat('T', 1024),
            'descripcion' => str_repeat('D', 8192),
            'ubicacion_dentro_elemento' => str_repeat('U', 2048),
            'accion_recomendada' => str_repeat('A', 4096),
        ]];

        $response = $this->actingAs($tecnico)->postJson('/api/inspecciones', [
            'elemento_id' => $elemento->id,
            'fecha_inspeccion' => now()->format('Y-m-d'),
            'termografias' => [UploadedFile::fake()->create('t.is2', 30)],
            'novedades' => json_encode($novedades),
        ]);

        $response->assertStatus(201);
        $novedad = Novedad::first();
        $this->assertNotNull($novedad);
        // Límites efectivos: controller + DB + mutator del modelo (defense in depth).
        // - titulo: controller trunca a 200 (alineado con VARCHAR(200))
        // - descripcion: controller trunca a 4096 (TEXT)
        // - ubicacion: controller trunca a 200 (alineado con VARCHAR(200))
        // - accion: controller trunca a 2048; el mutator setAccionRecomendadaAttribute
        //   del modelo Novedad re-trunca a 500 (defense in depth pre-existente)
        $this->assertSame(200, mb_strlen($novedad->titulo), 'titulo truncado a 200');
        $this->assertSame(4096, mb_strlen($novedad->descripcion), 'descripcion truncada a 4096');
        $this->assertSame(200, mb_strlen($novedad->ubicacion_dentro_elemento), 'ubicacion truncada a 200');
        $this->assertSame(500, mb_strlen($novedad->accion_recomendada), 'accion truncada a 500 por el mutator del modelo');
    }

    public function test_m02_invalid_criticidad_id_becomes_null(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id);
        $elemento = Elemento::factory()->create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
        ]);
        $criticidadValida = Criticidad::factory()->create();
        $criticidadInvalida = 99999;

        $novedades = [
            ['titulo' => 'OK', 'criticidad_id' => $criticidadValida->id],
            ['titulo' => 'BAD', 'criticidad_id' => $criticidadInvalida],
        ];

        $response = $this->actingAs($tecnico)->postJson('/api/inspecciones', [
            'elemento_id' => $elemento->id,
            'fecha_inspeccion' => now()->format('Y-m-d'),
            'termografias' => [UploadedFile::fake()->create('t.is2', 30)],
            'novedades' => json_encode($novedades),
        ]);

        $response->assertStatus(201);
        $this->assertSame($criticidadValida->id, Novedad::where('titulo', 'OK')->first()->criticidad_id);
        $this->assertNull(Novedad::where('titulo', 'BAD')->first()->criticidad_id, 'criticidad inexistente debe quedar null');
    }

    public function test_m02_rejects_oversized_raw_novedades_json(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id);
        $elemento = Elemento::factory()->create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
        ]);

        // > 128 KB de string crudo en el campo novedades → 422 por max
        $huge = json_encode([['titulo' => str_repeat('X', 200_000)]]);
        $this->assertGreaterThan(131_072, strlen($huge));

        $response = $this->actingAs($tecnico)->postJson('/api/inspecciones', [
            'elemento_id' => $elemento->id,
            'fecha_inspeccion' => now()->format('Y-m-d'),
            'termografias' => [UploadedFile::fake()->create('t.is2', 30)],
            'novedades' => $huge,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('novedades');
    }
}
