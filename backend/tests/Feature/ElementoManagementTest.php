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
            'password_hash' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('usuarios')->insert([
            'empresa_id' => $this->empresa->id,
            'rol_id' => $techRole->id,
            'nombre' => 'Tech',
            'apellido' => 'User',
            'email' => 'tech@example.com',
            'password_hash' => 'secret',
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
}
