<?php

namespace Tests\Feature;

use App\Services\CognitoJwtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AuthMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_responses_include_security_headers(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
    }

    public function test_it_returns_401_when_token_is_required_and_missing(): void
    {
        config()->set('cognito.required', true);

        $response = $this->getJson('/api/auth/me');

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Missing Bearer token',
            ]);
    }

    public function test_it_returns_401_with_invalid_bearer_token(): void
    {
        config()->set('cognito.required', true);

        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/auth/me');

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid token',
            ])
            ->assertJsonMissingPath('error');
    }

    public function test_it_returns_me_payload_when_token_is_valid(): void
    {
        config()->set('cognito.required', true);

        $empresaId = DB::table('empresas')->insertGetId([
            'nombre' => 'Empresa Test',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('roles')->insert([
            'codigo' => 'tecnico',
            'nombre' => 'Tecnico',
        ]);

        $claims = [
            'sub' => 'user-123',
            'email' => 'tech@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['tecnico'],
            'custom:empresa_id' => (string) $empresaId,
        ];

        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('valid-token')
            ->andReturn($claims);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/auth/me');

        $response
            ->assertOk()
            ->assertJson([
                'sub' => 'user-123',
                'email' => 'tech@example.com',
                'empresa_id' => $empresaId,
            ]);
    }

    public function test_it_does_not_autoprovision_into_first_company_without_empresa_claim(): void
    {
        config()->set('cognito.required', true);

        DB::table('empresas')->insert([
            'nombre' => 'Empresa No Debe Usarse',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('roles')->insert([
            'codigo' => 'tecnico',
            'nombre' => 'Tecnico',
        ]);

        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('valid-token')
            ->andReturn([
                'sub' => 'missing-company',
                'email' => 'sin.empresa@example.com',
                'token_use' => 'access',
                'cognito:groups' => ['tecnico'],
            ]);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/auth/me');

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Usuario no provisionado. Contacta a un administrador.',
            ]);

        $this->assertDatabaseMissing('usuarios', [
            'email' => 'sin.empresa@example.com',
        ]);
    }

    public function test_it_returns_403_when_local_user_is_inactive(): void
    {
        config()->set('cognito.required', true);

        $empresaId = DB::table('empresas')->insertGetId([
            'nombre' => 'Empresa Test',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rolId = DB::table('roles')->where('codigo', 'tecnico')->value('id');
        if (! $rolId) {
            $rolId = DB::table('roles')->insertGetId([
                'codigo' => 'tecnico',
                'nombre' => 'Técnico',
            ]);
        }

        DB::table('usuarios')->insert([
            'empresa_id' => $empresaId,
            'rol_id' => $rolId,
            'nombre' => 'User',
            'apellido' => 'Inactive',
            'email' => 'inactive@example.com',
            'activo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $claims = [
            'sub' => 'user-inactive',
            'email' => 'inactive@example.com',
            'token_use' => 'access',
            'custom:empresa_id' => (string) $empresaId,
        ];

        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('valid-token')
            ->andReturn($claims);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/auth/me');

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Usuario inactivo. Contacta a un administrador.',
            ]);
    }

    public function test_me_returns_local_role_as_effective_group_when_cognito_group_is_stale(): void
    {
        config()->set('cognito.required', true);

        $empresaId = DB::table('empresas')->insertGetId([
            'nombre' => 'Empresa Test',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supervisorRoleId = DB::table('roles')->insertGetId([
            'codigo' => 'supervisor',
            'nombre' => 'Supervisor',
        ]);

        DB::table('usuarios')->insert([
            'empresa_id' => $empresaId,
            'rol_id' => $supervisorRoleId,
            'nombre' => 'Supervisor',
            'apellido' => 'Local',
            'email' => 'me.role@example.com',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('valid-token')
            ->andReturn([
                'sub' => 'user-local-role',
                'email' => 'me.role@example.com',
                'token_use' => 'access',
                'cognito:groups' => ['tecnico'],
                'custom:empresa_id' => (string) $empresaId,
            ]);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/auth/me');

        $response
            ->assertOk()
            ->assertJson([
                'groups' => ['supervisor'],
                'local_role' => 'supervisor',
            ]);
    }
}
