<?php

namespace Tests\Feature;

use App\Services\CognitoJwtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'")
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');
    }

    public function test_it_returns_401_when_token_is_required_and_missing(): void
    {
        config()->set('cognito.required', true);
        Log::spy();

        $response = $this->getJson('/api/auth/me');

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Missing Bearer token',
            ]);

        Log::shouldHaveReceived('notice')
            ->once()
            ->with('auth.jwt.missing_token', Mockery::on(fn (array $context): bool => isset($context['ip'])));
    }

    public function test_it_returns_401_with_invalid_bearer_token(): void
    {
        config()->set('cognito.required', true);
        Log::spy();

        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/auth/me');

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid token',
            ])
            ->assertJsonMissingPath('error');

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Cognito JWT verification failed', Mockery::on(fn (array $context): bool => ($context['event'] ?? null) === 'auth.jwt.invalid_token'));
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

    public function test_preferred_username_matching_email_prefix_does_not_resolve_to_that_user(): void
    {
        config()->set('cognito.required', true);

        $empresaId = DB::table('empresas')->insertGetId([
            'nombre' => 'Empresa Test',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rolId = DB::table('roles')->insertGetId([
            'codigo' => 'tecnico',
            'nombre' => 'Tecnico',
        ]);

        // Victim user whose email prefix ("alice") could be abused for impersonation.
        $victimId = DB::table('usuarios')->insertGetId([
            'empresa_id' => $empresaId,
            'rol_id' => $rolId,
            'nombre' => 'Alice',
            'apellido' => 'Victim',
            'email' => 'alice@example.com',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attacker token: no email claim, only a preferred_username that matches the
        // prefix of the victim's email. The old LIKE 'alice@%' lookup would have
        // resolved this to alice@example.com.
        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('valid-token')
            ->andReturn([
                'sub' => 'attacker-999',
                'preferred_username' => 'alice',
                'token_use' => 'access',
                'cognito:groups' => ['tecnico'],
                'custom:empresa_id' => (string) $empresaId,
            ]);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/auth/me');

        // Must NOT be resolved as the victim; identity is unresolvable -> 403.
        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Usuario no provisionado. Contacta a un administrador.',
            ]);

        // The victim record must be untouched and no impersonating user created.
        $this->assertDatabaseHas('usuarios', [
            'id' => $victimId,
            'email' => 'alice@example.com',
        ]);
        $this->assertSame(1, DB::table('usuarios')->count());
    }

    public function test_access_token_username_email_resolves_via_exact_match(): void
    {
        config()->set('cognito.required', true);

        $empresaId = DB::table('empresas')->insertGetId([
            'nombre' => 'Empresa Test',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rolId = DB::table('roles')->insertGetId([
            'codigo' => 'tecnico',
            'nombre' => 'Tecnico',
        ]);

        $bobId = DB::table('usuarios')->insertGetId([
            'empresa_id' => $empresaId,
            'rol_id' => $rolId,
            'nombre' => 'Bob',
            'apellido' => 'Tecnico',
            'email' => 'bob@example.com',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Real Cognito ACCESS token: no email claim; identity is carried in the
        // Cognito-assigned `username` (equals the login email in an email-alias pool).
        // This must resolve to the existing user via an exact match.
        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('valid-token')
            ->andReturn([
                'sub' => 'bob-sub',
                'username' => 'bob@example.com',
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
                'id' => $bobId,
                'local_role' => 'tecnico',
                'empresa_id' => $empresaId,
            ]);

        // Resolved the existing user exactly; no impersonating record created.
        $this->assertSame(1, DB::table('usuarios')->count());
    }

    public function test_unverified_email_does_not_resolve_or_provision(): void
    {
        config()->set('cognito.required', true);

        $empresaId = DB::table('empresas')->insertGetId([
            'nombre' => 'Empresa Test',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rolId = DB::table('roles')->insertGetId([
            'codigo' => 'tecnico',
            'nombre' => 'Tecnico',
        ]);

        $victimId = DB::table('usuarios')->insertGetId([
            'empresa_id' => $empresaId,
            'rol_id' => $rolId,
            'nombre' => 'Carol',
            'apellido' => 'Victim',
            'email' => 'carol@example.com',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Email present but explicitly marked unverified: must not be trusted.
        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('valid-token')
            ->andReturn([
                'sub' => 'attacker-002',
                'email' => 'carol@example.com',
                'email_verified' => false,
                'token_use' => 'access',
                'cognito:groups' => ['tecnico'],
                'custom:empresa_id' => (string) $empresaId,
            ]);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/auth/me');

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Usuario no provisionado. Contacta a un administrador.',
            ]);

        $this->assertDatabaseHas('usuarios', [
            'id' => $victimId,
            'email' => 'carol@example.com',
        ]);
        $this->assertSame(1, DB::table('usuarios')->count());
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
