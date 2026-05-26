<?php

namespace Tests\Feature;

use App\Services\CognitoJwtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
