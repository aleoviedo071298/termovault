<?php

namespace Tests\Feature;

use App\Services\CognitoJwtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_it_returns_403_when_claims_do_not_include_allowed_roles(): void
    {
        config()->set('cognito.required', true);

        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('role-miss-token')
            ->andReturn([
                'sub' => 'user-1',
                'token_use' => 'access',
                'custom:role' => 'tecnico',
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

        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->with('role-hit-token')
            ->andReturn([
                'sub' => 'user-2',
                'token_use' => 'access',
                'cognito:groups' => ['supervisor'],
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
}
