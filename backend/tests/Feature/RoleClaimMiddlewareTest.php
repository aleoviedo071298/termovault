<?php

namespace Tests\Feature;

use App\Services\CognitoJwtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class RoleClaimMiddlewareTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['cognito.auth', 'role.claim:admin,supervisor'])
            ->get('/api/test-role-guard', fn () => response()->json(['ok' => true]));
    }

    private function createEmpresaAndRoles(): int
    {
        $empresaId = DB::table('empresas')->insertGetId([
            'nombre' => 'Empresa Test',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('roles')->insert([
            ['codigo' => 'tecnico', 'nombre' => 'Tecnico'],
            ['codigo' => 'supervisor', 'nombre' => 'Supervisor'],
        ]);

        return $empresaId;
    }

    public function test_it_returns_403_when_claims_do_not_include_allowed_roles(): void
    {
        config()->set('cognito.required', true);
        $empresaId = $this->createEmpresaAndRoles();

        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('role-miss-token')
            ->andReturn([
                'sub' => 'user-1',
                'email' => 'tecnico.role@example.com',
                'token_use' => 'access',
                'custom:role' => 'tecnico',
                'custom:empresa_id' => (string) $empresaId,
            ]);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);

        $response = $this->withHeader('Authorization', 'Bearer role-miss-token')
            ->getJson('/api/test-role-guard');

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Insufficient role permissions',
            ]);
    }

    public function test_it_returns_200_when_claims_include_an_allowed_group(): void
    {
        config()->set('cognito.required', true);
        $empresaId = $this->createEmpresaAndRoles();

        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('role-hit-token')
            ->andReturn([
                'sub' => 'user-2',
                'email' => 'supervisor.role@example.com',
                'token_use' => 'access',
                'cognito:groups' => ['supervisor'],
                'custom:empresa_id' => (string) $empresaId,
            ]);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);

        $response = $this->withHeader('Authorization', 'Bearer role-hit-token')
            ->getJson('/api/test-role-guard');

        $response
            ->assertOk()
            ->assertJson([
                'ok' => true,
            ]);
    }

    public function test_local_role_overrides_stale_cognito_group_for_authorization(): void
    {
        config()->set('cognito.required', true);
        $empresaId = $this->createEmpresaAndRoles();
        $supervisorRoleId = (int) DB::table('roles')->where('codigo', 'supervisor')->value('id');

        DB::table('usuarios')->insert([
            'empresa_id' => $empresaId,
            'rol_id' => $supervisorRoleId,
            'nombre' => 'Supervisor',
            'apellido' => 'Local',
            'email' => 'stale.role@example.com',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('stale-role-token')
            ->andReturn([
                'sub' => 'user-stale-role',
                'email' => 'stale.role@example.com',
                'token_use' => 'access',
                'cognito:groups' => ['tecnico'],
                'custom:empresa_id' => (string) $empresaId,
            ]);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);

        $response = $this->withHeader('Authorization', 'Bearer stale-role-token')
            ->getJson('/api/test-role-guard');

        $response
            ->assertOk()
            ->assertJson([
                'ok' => true,
            ]);
    }
}
