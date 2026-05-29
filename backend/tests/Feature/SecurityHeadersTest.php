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
}
