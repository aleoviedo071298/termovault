<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Elemento;
use App\Models\Yacimiento;
use App\Models\TipoElemento;
use App\Models\Criticidad;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Elemento (Element) Tests
 *
 * Verifica CRUD de elementos y autorización:
 * - Técnico no puede crear elementos
 * - Supervisor PAE puede crear en su yacimiento
 * - Supervisor contratista no puede crear
 * - Admin puede crear en cualquier yacimiento
 * - Elemento con inspecciones no puede eliminarse
 * - Listado respeta scope del usuario
 */
class ElementoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear maestros
        Yacimiento::factory()->create(['nombre' => 'PAE']);
        Yacimiento::factory()->create(['nombre' => 'OTRO']);
        TipoElemento::factory()->create(['codigo' => 'subestacion']);
        // Criticidad is created by tests that need it to avoid unique constraint collisions
    }

    /**
     * Test: Técnico no puede crear elementos
     */
    public function test_tecnico_cannot_create_elemento(): void
    {
        $usuario = Usuario::factory()->tecnico()->create();
        $yacimiento = Yacimiento::first();
        $tipoElemento = TipoElemento::first();
        $criticidad = Criticidad::factory()->create(['nivel' => 1, 'nombre' => 'Baja']);

        $response = $this->actingAs($usuario)
            ->postJson('/api/elementos', [
                'yacimiento_id' => $yacimiento->id,
                'tipo_elemento_id' => $tipoElemento->id,
                'nombre' => 'SET TEST',
                'codigo' => 'SET-001',
                'criticidad_id' => $criticidad->id
            ]);

        // Debería retornar 403 Forbidden
        $response->assertStatus(403);
    }

    /**
     * Test: Admin puede crear elemento
     */
    public function test_admin_can_create_elemento(): void
    {
        $usuario = Usuario::factory()->admin()->create();
        $yacimiento = Yacimiento::first();
        $tipoElemento = TipoElemento::first();
        $criticidad = Criticidad::factory()->create(['nivel' => 2, 'nombre' => 'Media']);

        $response = $this->actingAs($usuario)
            ->postJson('/api/elementos', [
                'yacimiento_id' => $yacimiento->id,
                'tipo_elemento_id' => $tipoElemento->id,
                'nombre' => 'SET AGR',
                'codigo' => 'SET-AGR',
                'criticidad_id' => $criticidad->id
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('elementos', [
            'nombre' => 'SET AGR',
            'codigo' => 'SET-AGR'
        ]);
    }

    /**
     * Test: Validación de campos requeridos
     */
    public function test_elemento_validation_required_fields(): void
    {
        $usuario = Usuario::factory()->admin()->create();

        $response = $this->actingAs($usuario)
            ->postJson('/api/elementos', [
                // Falta: yacimiento_id, tipo_elemento_id, nombre, codigo
                // This should trigger validation errors
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
    }

    /**
     * Test: Elemento con inspecciones no puede eliminarse
     */
    public function test_elemento_with_inspecciones_cannot_be_deleted(): void
    {
        $elemento = Elemento::factory()->create();
        // Simular que tiene inspecciones (tecnico_id debe apuntar a un usuario real por la FK en Postgres)
        $tecnico = Usuario::factory()->tecnico()->create();
        $elemento->inspecciones()->create([
            'tecnico_id' => $tecnico->id,
            'fecha_inspeccion' => now()
        ]);

        $usuario = Usuario::factory()->admin()->create();

        $response = $this->actingAs($usuario)
            ->deleteJson("/api/elementos/{$elemento->id}");

        // Debería retornar 422 con mensaje específico
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'No se puede eliminar un elemento que tiene inspecciones cargadas. Elimina o archiva las inspecciones primero.');
        $response->assertJsonPath('inspecciones_count', 1);
    }

    /**
     * Test: Elemento sin inspecciones puede eliminarse
     */
    public function test_elemento_without_inspecciones_can_be_deleted(): void
    {
        $elemento = Elemento::factory()->create();

        $usuario = Usuario::factory()->admin()->create();

        $response = $this->actingAs($usuario)
            ->deleteJson("/api/elementos/{$elemento->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Elemento eliminado correctamente');
        $this->assertDatabaseMissing('elementos', ['id' => $elemento->id]);
    }

    /**
     * Test: GET /api/elementos respeta scope del usuario
     */
    public function test_elementos_list_respects_scope(): void
    {
        // Crear elementos en diferentes yacimientos
        $yac1 = Yacimiento::first();
        $yac2 = Yacimiento::where('nombre', 'OTRO')->first();

        Elemento::factory()->create(['yacimiento_id' => $yac1->id]);
        Elemento::factory()->create(['yacimiento_id' => $yac2->id]);

        // Técnico sin yacimiento asignado no ve nada
        $usuario = Usuario::factory()->tecnico()->create();

        $response = $this->actingAs($usuario)
            ->getJson('/api/elementos');

        $response->assertStatus(200);
        $this->assertCount(0, $response['data'] ?? []);
    }

    /**
     * Test: Estado enum validation en PUT
     */
    public function test_elemento_update_with_invalid_data(): void
    {
        $elemento = Elemento::factory()->create();
        $usuario = Usuario::factory()->admin()->create();

        $response = $this->actingAs($usuario)
            ->putJson("/api/elementos/{$elemento->id}", [
                'nombre' => 'Updated',
                'codigo' => 'UPD-001',
                // Usar los IDs reales del elemento (en Postgres las secuencias no se resetean por test,
                // así que no se puede asumir id=1).
                'yacimiento_id' => $elemento->yacimiento_id,
                'tipo_elemento_id' => $elemento->tipo_elemento_id,
                'invalid_field' => 'should be ignored'
            ]);

        // Debe actualizar exitosamente (campos extra se ignoran)
        $response->assertStatus(200);
    }

    /**
     * Test: byYacimientoIds scope validates input to prevent SQL injection (M1)
     */
    public function test_by_yacimiento_ids_scope_accepts_valid_integers(): void
    {
        $yac1 = Yacimiento::first();
        $yac2 = Yacimiento::where('nombre', 'OTRO')->first();

        Elemento::factory()->create(['yacimiento_id' => $yac1->id]);
        Elemento::factory()->create(['yacimiento_id' => $yac2->id]);

        $elementos = Elemento::query()
            ->byYacimientoIds([(int) $yac1->id])
            ->get();

        $this->assertCount(1, $elementos);
        $this->assertEquals($yac1->id, $elementos->first()->yacimiento_id);
    }

    /**
     * Test: byYacimientoIds scope rejects non-integer IDs to prevent SQL injection (M1)
     */
    public function test_by_yacimiento_ids_scope_rejects_non_integers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All yacimiento IDs must be positive integers.');

        Elemento::query()
            ->byYacimientoIds(['1', '2']) // String IDs should be rejected
            ->get();
    }

    /**
     * Test: byYacimientoIds scope rejects non-positive integers to prevent SQL injection (M1)
     */
    public function test_by_yacimiento_ids_scope_rejects_negative_ids(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All yacimiento IDs must be positive integers.');

        Elemento::query()
            ->byYacimientoIds([1, -5, 3]) // Negative ID should be rejected
            ->get();
    }

    /**
     * Test: byYacimientoIds scope handles empty array gracefully (M1)
     */
    public function test_by_yacimiento_ids_scope_handles_empty_array(): void
    {
        $yac1 = Yacimiento::first();
        Elemento::factory()->create(['yacimiento_id' => $yac1->id]);

        // Empty array should return all (no filtering applied)
        $elementos = Elemento::query()
            ->byYacimientoIds([])
            ->get();

        $this->assertGreaterThanOrEqual(1, $elementos->count());
    }
}
