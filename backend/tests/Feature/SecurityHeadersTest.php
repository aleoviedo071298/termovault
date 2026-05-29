<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_web_responses_use_public_cache_policy(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertHeader('Cache-Control', 'immutable, max-age=2592000, public');
    }

    public function test_api_responses_use_no_store_cache_policy(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');
    }

    /**
     * Test: CORS headers present for allowed origin
     */
    public function test_cors_headers_allowed_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://app.example.com',
        ])->getJson('/api/health');

        $response->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->assertHeader('Access-Control-Allow-Credentials', 'false');
    }

    /**
     * Test: CORS headers NOT present for disallowed origin
     */
    public function test_cors_headers_blocked_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil.example.com',
        ])->getJson('/api/health');

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    /**
     * Test: CSP header present
     */
    public function test_csp_header_present(): void
    {
        $response = $this->getJson('/api/health');

        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    /**
     * Test: X-Frame-Options prevents clickjacking
     */
    public function test_x_frame_options_deny(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    /**
     * Test: X-Content-Type-Options prevents MIME sniffing
     */
    public function test_x_content_type_options_nosniff(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Test: Referrer-Policy limits information leakage
     */
    public function test_referrer_policy_no_referrer(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }

    /**
     * Test: Permissions-Policy restricts dangerous APIs
     */
    public function test_permissions_policy_present(): void
    {
        $response = $this->getJson('/api/health');

        $this->assertTrue($response->headers->has('Permissions-Policy'));
        $policy = $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
    }

    /**
     * Test: OPTIONS requests (CORS preflight) return 204
     */
    public function test_cors_preflight_request(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://app.example.com',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/health');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
    }
}
