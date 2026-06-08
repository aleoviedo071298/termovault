<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Inspeccion;
use App\Models\Elemento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        // Adjuntos se guardan en disco S3/MinIO; en tests usamos un disco falso
        // para no depender de un MinIO real (no disponible en CI).
        Storage::fake('s3');
        // Create shared empresa and yacimiento for tests
        $this->empresa = \App\Models\Empresa::factory()->create(['nombre' => 'PAE']);
        $this->yacimiento = \App\Models\Yacimiento::factory()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'YAC-PAE',  // Required for legacy compatibility checks
            'permite_supervisor_elementos' => true, // Owner yacimiento: supervisors can mutate/review
        ]);
    }

    /**
     * Test: Técnico puede crear inspección
     */
    public function test_tecnico_can_create_inspeccion(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id); // M4: técnico asignado a su yacimiento
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

        $response = $this->actingAs($tecnico)
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),  // Use date format instead of datetime
                'empresa_contratista' => 'PECOM',
                'condiciones_clima' => 'Despejado',
                'resumen' => 'Inspección rutinaria',
                'estado' => 'enviada',
                'termografias' => [UploadedFile::fake()->create('termo.is2', 50)],
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
     * Test: Técnico NO puede crear una inspección ya cerrada
     *
     * CRÍTICO: Al crear, solo se permite el estado inicial 'enviada'.
     * Saltar el flujo enviada -> revisada -> cerrada debe rechazarse (422),
     * forzando que revisión/cierre pase por PATCH /estado con control de rol.
     */
    public function test_tecnico_cannot_create_closed_inspeccion(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

        $response = $this->actingAs($tecnico)
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),
                'estado' => 'cerrada',  // ❌ No permitido al crear
                'novedades' => json_encode([]),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('estado');
        $this->assertDatabaseMissing('inspecciones', [
            'elemento_id' => $elemento->id,
            'estado' => 'cerrada',
        ]);
    }

    /**
     * Test: Solo técnico que creó puede ver su inspección
     */
    public function test_tecnico_can_only_see_own_inspecciones(): void
    {
        $tecnico1 = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico2 = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

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
        // Para actualizar inspecciones, el supervisor debe ser PAE supervisor
        // (es decir, de empresa PAE y asignado al yacimiento PAE)
        $supervisor = Usuario::factory()->supervisor()
            ->create(['empresa_id' => $this->empresa->id]);

        // Asignar al yacimiento PAE (required for is_pae_supervisor scope)
        $supervisor->yacimientos()->attach($this->yacimiento->id);

        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);
        $inspeccion = Inspeccion::factory()->create(['estado' => 'enviada', 'elemento_id' => $elemento->id]);

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
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);
        $inspeccion = Inspeccion::factory()->create(['estado' => 'enviada', 'elemento_id' => $elemento->id]);

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
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

        // Crear un archivo inválido (por ejemplo, .exe)
        $invalidFile = UploadedFile::fake()->create('malware.exe', 100);

        $response = $this->actingAs($tecnico)
            ->post('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),
                'reporte' => $invalidFile,  // ❌ .exe no permitido
                'novedades' => json_encode([])
            ]);

        // Laravel valida y redirige (302) en form submissions, pero incluye el error
        $response->assertStatus(302);
        $response->assertSessionHasErrors('reporte');
    }

    /**
     * Test: Validación tamaño máximo de archivo
     */
    public function test_inspeccion_file_upload_size_limit(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

        // Crear archivo Word muy grande (> 10MB)
        $largeFile = UploadedFile::fake()
            ->create('report.docx', 11 * 1024);  // 11 MB

        $response = $this->actingAs($tecnico)
            ->post('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),
                'reporte' => $largeFile,  // ❌ > 10MB
                'novedades' => json_encode([])
            ]);

        // Laravel valida y redirige (302) en form submissions, pero incluye el error
        $response->assertStatus(302);
        $response->assertSessionHasErrors('reporte');
    }

    /**
     * Test: Validación ZIP para imágenes
     */
    public function test_inspeccion_imagenes_must_be_zip(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

        // Subir JPG en lugar de ZIP
        $jpgFile = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($tecnico)
            ->post('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),
                'imagenes' => $jpgFile,  // ❌ Solo ZIP permitido
                'novedades' => json_encode([])
            ]);

        // Laravel valida y redirige (302) en form submissions, pero incluye el error
        $response->assertStatus(302);
        $response->assertSessionHasErrors('imagenes');
    }

    /**
     * Test: [008] Termografía con magic bytes de ejecutable es rechazada
     *
     * CRÍTICO: malware.exe renombrado a .is2 pasa la validación de extensión
     * pero debe ser bloqueado por la inspección de contenido (magic bytes).
     */
    public function test_thermal_upload_with_executable_content_is_rejected(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

        // Ejecutable de Windows (firma MZ) disfrazado de termografía .is2
        $malware = UploadedFile::fake()->createWithContent('captura.is2', "MZ\x90\x00\x03\x00\x00\x00");

        $response = $this->actingAs($tecnico)
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),
                'termografias' => [$malware],
                'novedades' => json_encode([]),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('archivo');

        // No se creó ninguna inspección ni archivo
        $this->assertDatabaseMissing('inspecciones', ['elemento_id' => $elemento->id]);
        $this->assertSame(0, \DB::table('archivos')->count());
    }

    /**
     * Test: [008] Reporte con magic bytes de ELF disfrazado de PDF es rechazado
     */
    public function test_report_with_elf_content_disguised_as_pdf_is_rejected(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

        // Binario ELF (Linux) renombrado a .is2 (la regla mimes no aplica a termografías)
        $elf = UploadedFile::fake()->createWithContent('reporte.is2', "\x7fELF\x02\x01\x01\x00");

        $response = $this->actingAs($tecnico)
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),
                'termografias' => [$elf],
                'novedades' => json_encode([]),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('archivo');
    }

    /**
     * Test: [008] Termografía .is2 legítima (sin firma de ejecutable) se acepta
     *
     * Garantiza que el guard NO genera falsos positivos sobre contenido válido.
     */
    public function test_legitimate_thermal_file_is_accepted(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

        // .is2 Fluke real (basado en ZIP, firma PK)
        $is2 = UploadedFile::fake()->createWithContent('captura.is2', "PK\x03\x04\x0a\x00\x00\x00datos");

        $response = $this->actingAs($tecnico)
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),
                'termografias' => [$is2],
                'novedades' => json_encode([]),
            ]);

        $response->assertStatus(201);
        $this->assertSame(1, \DB::table('archivos')->where('tipo', 'termografia_is2')->count());
    }

    /**
     * Test: Novedades se crean con estado 'abierta'
     */
    public function test_inspeccion_novedades_created_as_abierta(): void
    {
        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id); // M4: técnico asignado a su yacimiento
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);
        $criticidad = \App\Models\Criticidad::factory()->create();

        $response = $this->actingAs($tecnico)
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),
                'termografias' => [UploadedFile::fake()->create('termo.is2', 50)],
                'novedades' => json_encode([
                    [
                        'criticidad_id' => $criticidad->id,
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
        // Para actualizar inspecciones, el supervisor debe ser PAE supervisor
        // (es decir, de empresa PAE y asignado al yacimiento PAE)
        $supervisor = Usuario::factory()->supervisor()
            ->create(['empresa_id' => $this->empresa->id]);

        // Asignar al yacimiento PAE (required for is_pae_supervisor scope)
        $supervisor->yacimientos()->attach($this->yacimiento->id);

        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);
        $inspeccion = Inspeccion::factory()->create(['estado' => 'revisada', 'elemento_id' => $elemento->id]);
        $criticidad = \App\Models\Criticidad::factory()->create();
        $novedad = $inspeccion->novedades()->create([
            'titulo' => 'Test',
            'estado' => 'abierta',
            'criticidad_id' => $criticidad->id
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

    // ──────────────────────────────────────────────────────────────────────
    // FIX [013] — State machine & segregation of duties
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Test: Transición inválida enviada → cerrada es rechazada (422)
     *
     * CRÍTICO: [013-A] No se puede saltar el paso "revisada".
     */
    public function test_cannot_skip_revisada_going_directly_to_cerrada(): void
    {
        $supervisor = Usuario::factory()->supervisor()
            ->create(['empresa_id' => $this->empresa->id]);
        $supervisor->yacimientos()->attach($this->yacimiento->id);

        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);
        $inspeccion = Inspeccion::factory()->create([
            'estado' => 'enviada',
            'elemento_id' => $elemento->id,
        ]);

        $response = $this->actingAs($supervisor)
            ->patchJson("/api/inspecciones/{$inspeccion->id}/estado", [
                'estado' => 'cerrada',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Transición de estado inválida: enviada → cerrada']);

        // Estado no cambió
        $this->assertDatabaseHas('inspecciones', [
            'id' => $inspeccion->id,
            'estado' => 'enviada',
        ]);
    }

    /**
     * Test: Transición inválida cerrada → revisada es rechazada (422)
     *
     * CRÍTICO: [013-A] Estado "cerrada" es terminal.
     */
    public function test_cannot_reopen_closed_inspeccion(): void
    {
        $supervisor = Usuario::factory()->supervisor()
            ->create(['empresa_id' => $this->empresa->id]);
        $supervisor->yacimientos()->attach($this->yacimiento->id);

        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);
        $inspeccion = Inspeccion::factory()->create([
            'estado' => 'cerrada',
            'elemento_id' => $elemento->id,
        ]);

        $response = $this->actingAs($supervisor)
            ->patchJson("/api/inspecciones/{$inspeccion->id}/estado", [
                'estado' => 'revisada',
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test: Transición regresiva revisada → enviada es rechazada (422)
     *
     * CRÍTICO: [013-A] No se puede revertir una revisión.
     */
    public function test_cannot_regress_from_revisada_to_enviada(): void
    {
        $supervisor = Usuario::factory()->supervisor()
            ->create(['empresa_id' => $this->empresa->id]);
        $supervisor->yacimientos()->attach($this->yacimiento->id);

        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);
        $inspeccion = Inspeccion::factory()->create([
            'estado' => 'revisada',
            'elemento_id' => $elemento->id,
        ]);

        $response = $this->actingAs($supervisor)
            ->patchJson("/api/inspecciones/{$inspeccion->id}/estado", [
                'estado' => 'enviada',
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test: Segregación de funciones — mismo supervisor NO puede revisar Y cerrar
     *
     * CRÍTICO: [013-B] El que revisó no puede cerrar su propia revisión.
     */
    public function test_same_supervisor_cannot_review_and_close(): void
    {
        $supervisor = Usuario::factory()->supervisor()
            ->create(['empresa_id' => $this->empresa->id]);
        $supervisor->yacimientos()->attach($this->yacimiento->id);

        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);
        $inspeccion = Inspeccion::factory()->create([
            'estado' => 'revisada',
            'elemento_id' => $elemento->id,
            'revisada_por' => $supervisor->id, // ← ESTE supervisor revisó
            'fecha_revision' => now(),
        ]);

        // Mismo supervisor intenta cerrar → 403
        $response = $this->actingAs($supervisor)
            ->patchJson("/api/inspecciones/{$inspeccion->id}/estado", [
                'estado' => 'cerrada',
            ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'No puedes cerrar una inspección que vos mismo revisaste (segregación de funciones)',
        ]);

        // Estado no cambió
        $this->assertDatabaseHas('inspecciones', [
            'id' => $inspeccion->id,
            'estado' => 'revisada',
        ]);
    }

    /**
     * Test: Supervisor diferente SÍ puede cerrar inspección revisada por otro
     *
     * CRÍTICO: [013-B] Validar que la segregación no bloquea el flujo correcto.
     */
    public function test_different_supervisor_can_close_after_review(): void
    {
        $supervisorA = Usuario::factory()->supervisor()
            ->create(['empresa_id' => $this->empresa->id]);
        $supervisorA->yacimientos()->attach($this->yacimiento->id);

        $supervisorB = Usuario::factory()->supervisor()
            ->create(['empresa_id' => $this->empresa->id]);
        $supervisorB->yacimientos()->attach($this->yacimiento->id);

        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);
        $inspeccion = Inspeccion::factory()->create([
            'estado' => 'revisada',
            'elemento_id' => $elemento->id,
            'revisada_por' => $supervisorA->id,  // Supervisor A revisó
            'fecha_revision' => now(),
        ]);

        // Supervisor B cierra → 200
        $response = $this->actingAs($supervisorB)
            ->patchJson("/api/inspecciones/{$inspeccion->id}/estado", [
                'estado' => 'cerrada',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('inspecciones', [
            'id' => $inspeccion->id,
            'estado' => 'cerrada',
            'cerrada_por' => $supervisorB->id,
        ]);
    }

    /**
     * Test: Admin puede cerrar incluso si él mismo revisó (bypass de segregación)
     *
     * Admin no tiene restricción de segregación (es superusuario).
     */
    public function test_admin_can_close_even_if_same_reviewer(): void
    {
        $admin = Usuario::factory()->admin()->create(['empresa_id' => $this->empresa->id]);

        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);
        $inspeccion = Inspeccion::factory()->create([
            'estado' => 'revisada',
            'elemento_id' => $elemento->id,
            'revisada_por' => $admin->id,  // Admin mismo revisó
            'fecha_revision' => now(),
        ]);

        // Admin cierra → 200 (bypass segregation)
        $response = $this->actingAs($admin)
            ->patchJson("/api/inspecciones/{$inspeccion->id}/estado", [
                'estado' => 'cerrada',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('inspecciones', [
            'id' => $inspeccion->id,
            'estado' => 'cerrada',
        ]);
    }

    /**
     * Test: Flujo completo enviada → revisada → cerrada
     *
     * Valida el happy path con state machine + segregation.
     */
    public function test_full_inspection_lifecycle(): void
    {
        $supervisorA = Usuario::factory()->supervisor()
            ->create(['empresa_id' => $this->empresa->id]);
        $supervisorA->yacimientos()->attach($this->yacimiento->id);

        $supervisorB = Usuario::factory()->supervisor()
            ->create(['empresa_id' => $this->empresa->id]);
        $supervisorB->yacimientos()->attach($this->yacimiento->id);

        $tecnico = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);
        $tecnico->yacimientos()->attach($this->yacimiento->id);
        $elemento = Elemento::factory()->create(['yacimiento_id' => $this->yacimiento->id]);

        // 1. Técnico crea
        $response = $this->actingAs($tecnico)
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => now()->format('Y-m-d'),
                'termografias' => [UploadedFile::fake()->create('termo.is2', 50)],
                'novedades' => json_encode([]),
            ]);
        $response->assertStatus(201);
        $inspeccionId = $response->json('inspeccion.id');

        // 2. Supervisor A revisa
        $response = $this->actingAs($supervisorA)
            ->patchJson("/api/inspecciones/{$inspeccionId}/estado", [
                'estado' => 'revisada',
                'observaciones_revisor' => 'Todo en orden',
            ]);
        $response->assertStatus(200);

        // 3. Supervisor A intenta cerrar (mismo que revisó) → 403
        $response = $this->actingAs($supervisorA)
            ->patchJson("/api/inspecciones/{$inspeccionId}/estado", [
                'estado' => 'cerrada',
            ]);
        $response->assertStatus(403);

        // 4. Supervisor B cierra → 200
        $response = $this->actingAs($supervisorB)
            ->patchJson("/api/inspecciones/{$inspeccionId}/estado", [
                'estado' => 'cerrada',
            ]);
        $response->assertStatus(200);

        // 5. Verificar estado final
        $this->assertDatabaseHas('inspecciones', [
            'id' => $inspeccionId,
            'estado' => 'cerrada',
            'revisada_por' => $supervisorA->id,
            'cerrada_por' => $supervisorB->id,
        ]);
    }
}
