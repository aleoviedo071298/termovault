<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * FIX [N-07] — Rate limiting global y específico.
 *
 * Verifica que las cuotas se aplican por usuario autenticado (no por IP) y
 * que los limiters de descargas / admin-write son más estrictos que el global.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Garantizar cache limpio entre tests.
        RateLimiter::clear('api');
        RateLimiter::clear('download');
        RateLimiter::clear('admin-write');
    }

    public function test_api_limiter_isolates_users_by_id_not_by_ip(): void
    {
        // Dos usuarios distintos, misma IP → claves diferentes.
        $reqA = \Illuminate\Http\Request::create('/x', server: ['REMOTE_ADDR' => '203.0.113.1']);
        $reqA->attributes->set('auth.user_id', 100);
        $keyA = call_user_func(RateLimiter::limiter('api'), $reqA)->key;

        $reqB = \Illuminate\Http\Request::create('/x', server: ['REMOTE_ADDR' => '203.0.113.1']);
        $reqB->attributes->set('auth.user_id', 200);
        $keyB = call_user_func(RateLimiter::limiter('api'), $reqB)->key;

        $this->assertNotSame($keyA, $keyB, 'Usuarios distintos no deben compartir cubo aunque vengan de la misma IP');
        $this->assertStringContainsString('user:100', $keyA);
        $this->assertStringContainsString('user:200', $keyB);
    }

    public function test_download_limiter_is_stricter_than_global(): void
    {
        // Validamos directamente sobre el RateLimiter resuelto.
        $request = \Illuminate\Http\Request::create('/api/archivos/1/download');
        $request->attributes->set('auth.user_id', 999);

        $limit = call_user_func(RateLimiter::limiter('download'), $request);
        $this->assertSame(30, $limit->maxAttempts, 'download debe permitir 30/min');

        $limitApi = call_user_func(RateLimiter::limiter('api'), $request);
        $this->assertSame(120, $limitApi->maxAttempts, 'api global debe permitir 120/min');

        $limitAdmin = call_user_func(RateLimiter::limiter('admin-write'), $request);
        $this->assertSame(30, $limitAdmin->maxAttempts, 'admin-write debe permitir 30/min');
    }

    public function test_limiter_key_is_user_when_authenticated(): void
    {
        $request = \Illuminate\Http\Request::create('/x');
        $request->attributes->set('auth.user_id', 42);
        $limit = call_user_func(RateLimiter::limiter('api'), $request);
        // La key debe contener "user:42", no la IP
        $this->assertStringContainsString('user:42', $limit->key);
        $this->assertStringNotContainsString('ip:', $limit->key);
    }

    public function test_limiter_key_falls_back_to_ip_when_unauthenticated(): void
    {
        $request = \Illuminate\Http\Request::create('/x');
        // sin auth.user_id
        $limit = call_user_func(RateLimiter::limiter('api'), $request);
        $this->assertStringStartsWith('ip:', $limit->key);
    }
}
