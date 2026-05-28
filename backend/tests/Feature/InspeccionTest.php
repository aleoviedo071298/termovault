<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Inspeccion;
use App\Models\Elemento;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Inspeccion (Inspection Report) Tests
 *
 * Verifica flujos críticos de inspecciones:
 * - Técnico puede crear inspección
 * - Validación de estado enum (enviada|revisada|cerrada)
 * - File upload validation (MIME, extension, size)
 * - Solo supervisor/admin pueden cambiar estado
 * - Permiso de scope (técnico solo ve sus propias)
 */
class InspeccionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        // Create shared empresa and yacimiento for tests
        $this->empresa = \App\Models\Empresa::factory()->create();
        $this->yacimiento = \App\Models\Yacimiento::factory()->create(['empresa_id' => $this->empresa->id]);
    }

    /**
     * Test: Técnico puede crear inspección
     */
    public function test_tecnico_can_create_inspeccion(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

        $response = $this->actingAs($tecnico)
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),  // Use date format instead of datetime
                'cuadrilla' => 'Cuadrilla A',
                'empresa_contratista' => 'PECOM',
                'condiciones_clima' => 'Despejado',
                'resumen' => 'Inspección rutinaria',
                'estado' => 'enviada',
                'novedades' => json_encode([])
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inspecciones', [
            'tecnico_id' => $tecnico->id,
            'elemento_id' => $elemento->id,
            'estado' => 'enviada'
        ]);
    }

    /**
     * Test: Validación de estado enum en creación
     *
     * CRÍTICO: M9 - Validar que solo valores permitidos se aceptan
     */
    public function test_inspeccion_estado_enum_validation(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

        // Intentar crear con estado inválido
        $response = $this->actingAs($tecnico)
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),
                'estado' => 'estado_invalido',  // ❌ NO es una opción válida
                'novedades' => json_encode([])
            ]);

        // Debe rechazar con 422
        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
    }

    /**
     * Test: Solo técnico que creó puede ver su inspección
     */
    public function test_tecnico_can_only_see_own_inspecciones(): void
    {
        $tecnico1 = Usuario::factory()->tecnico()->create();
        $tecnico2 = Usuario::factory()->tecnico()->create();
        $elemento = Elemento::factory()->create();

        // Tecnico 1 crea inspección
        $inspeccion = Inspeccion::factory()->create([
            'tecnico_id' => $tecnico1->id,
            'elemento_id' => $elemento->id
        ]);

        // Tecnico 2 intenta verla
        $response = $this->actingAs($tecnico2)
            ->getJson("/api/inspecciones/{$inspeccion->id}");

        // No debería verla (scope restriction)
        $response->assertStatus(404);
    }

    /**
     * Test: Supervisor puede cambiar estado
     */
    public function test_supervisor_can_update_inspeccion_estado(): void
    {
        $supervisor = Usuario::factory()->supervisor()->create();
        $inspeccion = Inspeccion::factory()->create(['estado' => 'enviada']);

        $response = $this->actingAs($supervisor)
            ->patchJson("/api/inspecciones/{$inspeccion->id}/estado", [
                'estado' => 'revisada',
                'observaciones_revisor' => 'OK, sin observaciones'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('inspecciones', [
            'id' => $inspeccion->id,
            'estado' => 'revisada'
        ]);
    }

    /**
     * Test: Técnico NO puede cambiar estado
     */
    public function test_tecnico_cannot_update_inspeccion_estado(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create();
        $inspeccion = Inspeccion::factory()->create(['estado' => 'enviada']);

        $response = $this->actingAs($tecnico)
            ->patchJson("/api/inspecciones/{$inspeccion->id}/estado", [
                'estado' => 'revisada'
            ]);

        // Debe rechazar
        $response->assertStatus(403);
    }

    /**
     * Test: Validación MIME type en file upload
     *
     * CRÍTICO: Solo Word/Excel (.doc, .docx, .xls, .xlsx) y ZIP permitidos
     */
    public function test_inspeccion_file_upload_mime_validation(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create();
        $elemento = Elemento::factory()->create();

        // Crear un archivo inválido (por ejemplo, .exe)
        $invalidFile = UploadedFile::fake()->create('malware.exe', 100);

        $response = $this->actingAs($tecnico)
            ->post('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d H:i:s'),
                'reporte' => $invalidFile,  // ❌ .exe no permitido
                'novedades' => json_encode([])
            ]);

        // Debe rechazar
        $response->assertStatus(422);
    }

    /**
     * Test: Validación tamaño máximo de archivo
     */
    public function test_inspeccion_file_upload_size_limit(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create();
        $elemento = Elemento::factory()->create();

        // Crear archivo Word muy grande (> 10MB)
        $largeFile = UploadedFile::fake()
            ->create('report.docx', 11 * 1024);  // 11 MB

        $response = $this->actingAs($tecnico)
            ->post('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d H:i:s'),
                'reporte' => $largeFile,  // ❌ > 10MB
                'novedades' => json_encode([])
            ]);

        // Debe rechazar
        $response->assertStatus(422);
    }

    /**
     * Test: Validación ZIP para imágenes
     */
    public function test_inspeccion_imagenes_must_be_zip(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create();
        $elemento = Elemento::factory()->create();

        // Subir JPG en lugar de ZIP
        $jpgFile = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($tecnico)
            ->post('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d H:i:s'),
                'imagenes' => $jpgFile,  // ❌ Solo ZIP permitido
                'novedades' => json_encode([])
            ]);

        // Debe rechazar
        $response->assertStatus(422);
    }

    /**
     * Test: Novedades se crean con estado 'abierta'
     */
    public function test_inspeccion_novedades_created_as_abierta(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create();
        $elemento = Elemento::factory()->create();

        $response = $this->actingAs($tecnico)
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d H:i:s'),
                'novedades' => json_encode([
                    [
                        'criticidad_id' => 1,
                        'titulo' => 'Hallazgo de prueba',
                        'descripcion' => 'Test finding',
                        'temperatura_detectada' => 75.5
                    ]
                ])
            ]);

        $response->assertStatus(201);

        // Verificar que la novedad se creó como 'abierta'
        $this->assertDatabaseHas('novedades', [
            'titulo' => 'Hallazgo de prueba',
            'estado' => 'abierta'
        ]);
    }

    /**
     * Test: Cierre de inspección pasa novedades a 'resuelta'
     */
    public function test_inspeccion_closure_resolves_novedades(): void
    {
        $supervisor = Usuario::factory()->supervisor()->create();
        $inspeccion = Inspeccion::factory()->create(['estado' => 'revisada']);
        $novedad = $inspeccion->novedades()->create([
            'titulo' => 'Test',
            'estado' => 'abierta'
        ]);

        $response = $this->actingAs($supervisor)
            ->patchJson("/api/inspecciones/{$inspeccion->id}/estado", [
                'estado' => 'cerrada'
            ]);

        $response->assertStatus(200);

        // Verificar que novedad fue resuelta
        $this->assertDatabaseHas('novedades', [
            'id' => $novedad->id,
            'estado' => 'resuelta'
        ]);
    }
}
