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
            ]);
    }

    public function test_it_returns_me_payload_when_token_is_valid(): void
    {
        config()->set('cognito.required', true);

        $claims = [
            'sub' => 'user-123',
            'email' => 'tech@example.com',
            'token_use' => 'access',
            'custom:empresa_id' => '1',
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
                'token_use' => 'access',
                'empresa_id' => 1,
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
            'password_hash' => bcrypt('secret123'),
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
}
