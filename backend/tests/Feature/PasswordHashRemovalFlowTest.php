<?php

namespace Tests\Feature;

use App\Services\CognitoJwtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PasswordHashRemovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private function mockVerifier(array $claims): void
    {
        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')->andReturn($claims);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);
    }

    public function test_cognito_autoprovision_still_works_without_password_hash_column(): void
    {
        config()->set('cognito.required', true);

        $empresaId = DB::table('empresas')->insertGetId([
            'nombre' => 'PECOM',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('roles')->insert([
            'codigo' => 'tecnico',
            'nombre' => 'Técnico',
        ]);

        $this->mockVerifier([
            'sub' => 'new-user-1',
            'email' => 'nuevo.cognito@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['tecnico'],
            'custom:empresa_id' => (string) $empresaId,
            'given_name' => 'Nuevo',
            'family_name' => 'Usuario',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/auth/me');

        $response->assertOk();
        $this->assertDatabaseHas('usuarios', [
            'email' => 'nuevo.cognito@example.com',
            'empresa_id' => $empresaId,
        ]);
    }

    public function test_admin_can_create_local_user_without_password_hash_column(): void
    {
        config()->set('cognito.required', true);

        $empresaId = DB::table('empresas')->insertGetId([
            'nombre' => 'PECOM',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRoleId = DB::table('roles')->insertGetId([
            'codigo' => 'admin',
            'nombre' => 'Administrador',
        ]);

        DB::table('roles')->insert([
            'codigo' => 'tecnico',
            'nombre' => 'Técnico',
        ]);

        DB::table('usuarios')->insert([
            'empresa_id' => $empresaId,
            'rol_id' => $adminRoleId,
            'nombre' => 'Admin',
            'apellido' => 'Principal',
            'email' => 'admin.create@example.com',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mockVerifier([
            'sub' => 'admin-1',
            'email' => 'admin.create@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['admin'],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->postJson('/api/admin/usuarios', [
                'nombre' => 'Marijo',
                'apellido' => 'Tecnico',
                'email' => 'marijo.create@example.com',
                'empresa_id' => $empresaId,
                'rol_codigo' => 'tecnico',
                'yacimientos' => [],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('usuarios', [
            'email' => 'marijo.create@example.com',
            'empresa_id' => $empresaId,
        ]);
    }
}

