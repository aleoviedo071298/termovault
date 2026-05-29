<?php

namespace Tests\Feature;

use App\Models\Elemento;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\TipoElemento;
use App\Models\Yacimiento;
use App\Services\CognitoJwtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ElementoManagementTest extends TestCase
{
    use RefreshDatabase;

    private $empresa;
    private $yacimiento;
    private $tipo;
    private $adminClaims;
    private $techClaims;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['nombre' => 'PECOM', 'cuit' => '30-12345678-0']);
        $this->yacimiento = Yacimiento::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'PAE',
            'codigo' => 'YAC-PAE'
        ]);
        $this->tipo = TipoElemento::create([
            'codigo' => 'subestacion',
            'nombre' => 'Subestación'
        ]);

        $adminRole = Role::create(['codigo' => 'admin', 'nombre' => 'Administrador']);
        $techRole = Role::create(['codigo' => 'tecnico', 'nombre' => 'Técnico']);

        // Insert database users for email fallback mapping
        \DB::table('usuarios')->insert([
            'empresa_id' => $this->empresa->id,
            'rol_id' => $adminRole->id,
            'nombre' => 'Admin',
            'apellido' => 'User',
            'email' => 'admin@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('usuarios')->insert([
            'empresa_id' => $this->empresa->id,
            'rol_id' => $techRole->id,
            'nombre' => 'Tech',
            'apellido' => 'User',
            'email' => 'tech@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->adminClaims = [
            'sub' => 'admin-123',
            'email' => 'admin@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['admin'],
        ];

        $this->techClaims = [
            'sub' => 'tech-123',
            'email' => 'tech@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['tecnico'],
        ];
    }

    private function mockVerifier($claims, $token = 'valid-token')
    {
        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->andReturn($claims);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);
    }

    public function test_admin_can_create_element(): void
    {
        config()->set('cognito.required', true);
        $this->mockVerifier($this->adminClaims);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->postJson('/api/elementos', [
                'yacimiento_id' => $this->yacimiento->id,
                'tipo_elemento_id' => $this->tipo->id,
                'nombre' => 'Nueva Subestación Test',
                'codigo' => 'SET-TEST-NEW',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('elementos', [
            'nombre' => 'Nueva Subestación Test',
            'codigo' => 'SET-TEST-NEW'
        ]);
    }

    public function test_technician_cannot_create_element(): void
    {
        config()->set('cognito.required', true);
        $this->mockVerifier($this->techClaims);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->postJson('/api/elementos', [
                'yacimiento_id' => $this->yacimiento->id,
                'tipo_elemento_id' => $this->tipo->id,
                'nombre' => 'Nueva Subestación Test',
                'codigo' => 'SET-TEST-NEW',
            ]);

        $response->assertStatus(403);
    }

    public function test_can_view_single_element_with_details(): void
    {
        config()->set('cognito.required', true);
        $this->mockVerifier($this->adminClaims);

        $elemento = Elemento::create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'Elemento Test Detail',
            'codigo' => 'SET-DETAIL',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson("/api/elementos/{$elemento->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'elemento' => [
                    'id', 'nombre', 'codigo', 'tipo', 'yacimiento'
                ],
                'inspecciones'
            ]);
    }

    public function test_admin_can_delete_element(): void
    {
        config()->set('cognito.required', true);
        $this->mockVerifier($this->adminClaims);

        $elemento = Elemento::create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'Element to Delete',
            'codigo' => 'SET-DELETE-ME',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->deleteJson("/api/elementos/{$elemento->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('elementos', ['id' => $elemento->id]);
    }

    public function test_technician_cannot_delete_element(): void
    {
        config()->set('cognito.required', true);
        $this->mockVerifier($this->techClaims);

        $elemento = Elemento::create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'Element to Secure',
            'codigo' => 'SET-SECURE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->deleteJson("/api/elementos/{$elemento->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('elementos', ['id' => $elemento->id]);
    }

    public function test_can_filter_elements_inspected_by_technician(): void
    {
        config()->set('cognito.required', true);
        $this->mockVerifier($this->techClaims);

        $elemento1 = Elemento::create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'SET Inspected',
            'codigo' => 'SET-INSP',
        ]);

        $elemento2 = Elemento::create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'SET Clean',
            'codigo' => 'SET-CLEAN',
        ]);

        // Add inspection to elemento1 authored by technician
        $techUser = \App\Models\Usuario::where('email', 'tech@example.com')->first();
        \App\Models\Inspeccion::create([
            'elemento_id' => $elemento1->id,
            'tecnico_id' => $techUser->id,
            'fecha_inspeccion' => now(),
            'cuadrilla' => '625',
            'empresa_contratista' => 'PECOM'
        ]);

        // Fetch elements listing without filter
        $responseAll = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/elementos');
        $responseAll->assertOk();
        $this->assertCount(2, $responseAll->json());

        // Fetch elements listing with filter
        $responseFiltered = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/elementos?my_inspections_only=true');
        $responseFiltered->assertOk();
        $responseFiltered->assertJsonCount(1);
        $responseFiltered->assertJsonFragment(['codigo' => 'SET-INSP']);
        $responseFiltered->assertJsonMissing(['codigo' => 'SET-CLEAN']);
    }

    public function test_owner_supervisor_can_create_element_for_flagged_yacimiento(): void
    {
        config()->set('cognito.required', true);

        $capsa = Empresa::create(['nombre' => 'CAPSA', 'cuit' => '30-87654321-0']);
        $capsaYacimiento = Yacimiento::create([
            'empresa_id' => $capsa->id,
            'nombre' => 'Yacimiento CAPSA',
            'codigo' => 'YAC-CAPSA',
            'permite_supervisor_elementos' => true,
        ]);

        $supervisorRole = Role::firstOrCreate(['codigo' => 'supervisor'], ['nombre' => 'Supervisor']);
        $supervisorId = \DB::table('usuarios')->insertGetId([
            'empresa_id' => $capsa->id,
            'rol_id' => $supervisorRole->id,
            'nombre' => 'Supervisor',
            'apellido' => 'CAPSA',
            'email' => 'supervisor.capsa@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('usuario_yacimientos')->insert([
            'usuario_id' => $supervisorId,
            'yacimiento_id' => $capsaYacimiento->id,
        ]);

        $this->mockVerifier([
            'sub' => 'sup-capsa-123',
            'email' => 'supervisor.capsa@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['supervisor'],
            'custom:empresa_id' => (string) $capsa->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->postJson('/api/elementos', [
                'yacimiento_id' => $capsaYacimiento->id,
                'tipo_elemento_id' => $this->tipo->id,
                'nombre' => 'Elemento CAPSA Owner',
                'codigo' => 'CAPSA-OWN-1',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('elementos', [
            'codigo' => 'CAPSA-OWN-1',
            'yacimiento_id' => $capsaYacimiento->id,
        ]);
    }

    public function test_non_owner_supervisor_cannot_create_element_even_if_assigned(): void
    {
        config()->set('cognito.required', true);

        $capsa = Empresa::create(['nombre' => 'CAPSA', 'cuit' => '30-11223344-0']);
        $capsaYacimiento = Yacimiento::create([
            'empresa_id' => $capsa->id,
            'nombre' => 'Yacimiento CAPSA Secundario',
            'codigo' => 'YAC-CAPSA-2',
            'permite_supervisor_elementos' => false,
        ]);

        $supervisorRole = Role::firstOrCreate(['codigo' => 'supervisor'], ['nombre' => 'Supervisor']);
        $supervisorId = \DB::table('usuarios')->insertGetId([
            'empresa_id' => $capsa->id,
            'rol_id' => $supervisorRole->id,
            'nombre' => 'Supervisor',
            'apellido' => 'NoOwner',
            'email' => 'supervisor.noowner@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('usuario_yacimientos')->insert([
            'usuario_id' => $supervisorId,
            'yacimiento_id' => $capsaYacimiento->id,
        ]);

        $this->mockVerifier([
            'sub' => 'sup-noowner-123',
            'email' => 'supervisor.noowner@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['supervisor'],
            'custom:empresa_id' => (string) $capsa->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->postJson('/api/elementos', [
                'yacimiento_id' => $capsaYacimiento->id,
                'tipo_elemento_id' => $this->tipo->id,
                'nombre' => 'Elemento CAPSA NoOwner',
                'codigo' => 'CAPSA-NOOWN-1',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('elementos', [
            'codigo' => 'CAPSA-NOOWN-1',
        ]);
    }

    public function test_non_owner_supervisor_cannot_delete_element(): void
    {
        config()->set('cognito.required', true);

        $capsa = Empresa::create(['nombre' => 'CAPSA', 'cuit' => '30-55667788-0']);
        $capsaYacimiento = Yacimiento::create([
            'empresa_id' => $capsa->id,
            'nombre' => 'Yacimiento CAPSA Borrado',
            'codigo' => 'YAC-CAPSA-3',
            'permite_supervisor_elementos' => false,
        ]);

        $supervisorRole = Role::firstOrCreate(['codigo' => 'supervisor'], ['nombre' => 'Supervisor']);
        $supervisorId = \DB::table('usuarios')->insertGetId([
            'empresa_id' => $capsa->id,
            'rol_id' => $supervisorRole->id,
            'nombre' => 'Supervisor',
            'apellido' => 'NoOwnerDelete',
            'email' => 'supervisor.nodelete@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('usuario_yacimientos')->insert([
            'usuario_id' => $supervisorId,
            'yacimiento_id' => $capsaYacimiento->id,
        ]);

        $elemento = Elemento::create([
            'yacimiento_id' => $capsaYacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'Elemento CAPSA Protegido',
            'codigo' => 'CAPSA-NODEL-1',
        ]);

        $this->mockVerifier([
            'sub' => 'sup-nodelete-123',
            'email' => 'supervisor.nodelete@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['supervisor'],
            'custom:empresa_id' => (string) $capsa->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->deleteJson("/api/elementos/{$elemento->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('elementos', ['id' => $elemento->id]);
    }

    /**
     * Test: CSRF protection via JWT authentication (M5)
     *
     * Verifies that state-changing endpoints are protected by JWT auth,
     * not traditional CSRF tokens. This is appropriate for a stateless JSON API.
     * The API uses Authorization headers (JWT) instead of CSRF tokens.
     */
    public function test_state_changing_endpoints_use_jwt_not_csrf(): void
    {
        config()->set('cognito.required', true);

        $elemento = Elemento::create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'Elemento Test',
            'codigo' => 'TEST-001',
        ]);

        // Request without Authorization header should fail
        // (when Cognito auth is enabled)
        $response = $this->deleteJson("/api/elementos/{$elemento->id}");

        $this->assertTrue(in_array($response->status(), [401, 403, 405]));
        $this->assertDatabaseHas('elementos', ['id' => $elemento->id]);
    }

    /**
     * Test: Admin bypass of yacimiento filtering is by design (M6)
     *
     * Confirms that admins are NOT restricted by yacimiento assignment.
     * This allows enterprise-wide operations while maintaining per-role
     * authorization checks (canMutateElement, etc.).
     */
    public function test_admin_can_mutate_elements_outside_assigned_yacimientos(): void
    {
        // Create a second yacimiento
        $otherYacimiento = Yacimiento::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'PAE 2',
            'codigo' => 'YAC-PAE2'
        ]);

        // Create elemento in the second yacimiento
        $elemento = Elemento::create([
            'yacimiento_id' => $otherYacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'Elemento en YAC 2',
            'codigo' => 'YAC2-001',
        ]);

        // Admin claims (not assigned to any yacimiento via usuario_yacimientos)
        $adminRole = Role::where('codigo', 'admin')->first();
        $adminId = \DB::table('usuarios')
            ->where('email', 'admin@example.com')
            ->value('id');

        $this->mockVerifier([
            'sub' => 'admin-bypass-123',
            'email' => 'admin@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['admin'],
        ]);

        // Admin should be able to view/delete elemento in any yacimiento
        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson("/api/elementos/{$elemento->id}");

        $response->assertOk();
        $this->assertEquals($elemento->id, $response['elemento']['id']);

        // Admin should be able to delete elemento in any yacimiento (M6 design decision)
        $deleteResponse = $this->withHeader('Authorization', 'Bearer valid-token')
            ->deleteJson("/api/elementos/{$elemento->id}");

        $deleteResponse->assertOk();
        $this->assertDatabaseMissing('elementos', ['id' => $elemento->id]);
    }
}
