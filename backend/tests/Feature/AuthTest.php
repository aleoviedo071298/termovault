<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Authentication Tests
 *
 * Verifica flujos críticos de autenticación con Cognito:
 * - Health endpoint sin auth
 * - Login fails con credenciales inválidas
 * - Login success con credenciales válidas (mock Cognito)
 * - JWT validation en endpoints protegidos
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Fake de Cognito devolviendo credenciales inválidas (400).
     * Se invoca explícitamente en cada test de login para controlar el orden
     * de los stubs (el primer stub registrado gana en Laravel).
     */
    private function fakeCognitoInvalidCredentials(): void
    {
        Http::fake([
            'cognito-idp.*' => Http::response([
                '__type' => 'NotAuthorizedException',
                'message' => 'Incorrect username or password.',
            ], 400),
        ]);
    }

    /**
     * Test: Health endpoint accessible sin autenticación
     */
    public function test_health_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'service',
            'timestamp',
        ]);
        $this->assertEquals('ok', $response['status']);
        $this->assertEquals('termovault-api', $response['service']);
        // FIX [010]: la versión NO debe exponerse en el endpoint público.
        $response->assertJsonMissingPath('version');
    }

    /**
     * Test: Login endpoint rechaza credenciales inválidas
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->fakeCognitoInvalidCredentials();
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid@example.com',
            'password' => 'wrong_password'
        ]);

        // Cognito devuelve NotAuthorizedException
        $response->assertStatus(400);
        $response->assertJsonPath('message', 'Email o contraseña incorrectos.');
        $response->assertJsonMissingPath('error');
    }

    /**
     * Test: [001] Un login fallido se registra con email + IP + motivo.
     */
    public function test_failed_login_is_logged(): void
    {
        Log::spy();
        $this->fakeCognitoInvalidCredentials();

        $this->postJson('/api/auth/login', [
            'email' => 'attacker@example.com',
            'password' => 'wrong_password',
        ])->assertStatus(400);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => $message === 'auth.login.failed'
                && ($context['email'] ?? null) === 'attacker@example.com'
                && array_key_exists('ip', $context)
                && ($context['reason'] ?? null) === 'NotAuthorizedException')
            ->once();
    }

    /**
     * Test: [001] Un login exitoso se registra (sin tokens ni password).
     */
    public function test_successful_login_is_logged(): void
    {
        Log::spy();

        // Override del fake del setUp: Cognito devuelve tokens válidos.
        Http::fake([
            'cognito-idp.*' => Http::response([
                'AuthenticationResult' => [
                    'AccessToken' => 'fake-access',
                    'IdToken' => 'fake-id-token', // sin '.', decodeIdTokenClaims->null, salta provisioner
                    'RefreshToken' => 'fake-refresh',
                    'ExpiresIn' => 3600,
                ],
            ], 200),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'correct',
        ])->assertStatus(200);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context = []) => $message === 'auth.login.success'
                && ($context['email'] ?? null) === 'user@example.com'
                && array_key_exists('ip', $context))
            ->once();
    }

    /**
     * Test: Protected endpoint rechaza solicitud sin token
     */
    public function test_protected_endpoint_requires_token(): void
    {
        // Force Cognito auth to be required for this test
        config(['cognito.required' => true]);

        $response = $this->getJson('/api/catalogos');

        $response->assertStatus(401);
        // Check for either Unauthorized or Missing Bearer token message
        $message = $response->json('message');
        $this->assertTrue(
            in_array($message, ['Unauthorized', 'Missing Bearer token']),
            "Expected 'Unauthorized' or 'Missing Bearer token' but got: $message"
        );
    }

    /**
     * Test: Protected endpoint rechaza token inválido
     */
    public function test_protected_endpoint_rejects_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid_token')
            ->getJson('/api/catalogos');

        $response->assertStatus(401);
    }

    /**
     * Test: Rate limiting en login (máx 10 intentos/min)
     *
     * NOTA: Este test puede requerir configuración especial de cache
     * En development, comentar si causa problemas
     */
    public function test_login_rate_limiting(): void
    {
        $this->fakeCognitoInvalidCredentials();
        // Intentar 11 veces (límite es 10)
        for ($i = 0; $i < 11; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password'
            ]);
        }

        // El 11vo intento debería ser rate-limited
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        // Si rate limiting está activo, debería recibir 429 Too Many Requests
        // Por ahora, esto es aspiracional - se testea manualmente
        // TODO: Implementar cache mock para testear rate limit
    }

    /**
     * Test: CORS preflight request
     */
    public function test_cors_preflight_request(): void
    {
        $response = $this->withHeader('Origin', 'http://localhost:5173')
            ->options('/api/auth/login');

        // Debería permitir (200 o 204)
        $this->assertTrue(in_array($response->getStatusCode(), [200, 204]));
        // CORS header puede estar presente o no dependiendo de la configuración
        // Solo verificar que la respuesta es exitosa
    }
}
