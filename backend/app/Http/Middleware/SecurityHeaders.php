<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! (bool) config('security.headers.enabled', true)) {
            return $response;
        }

        foreach (config('security.headers.values', []) as $header => $value) {
            if (is_string($value) && trim($value) !== '') {
                $response->headers->set($header, $value);
            }
        }

        $this->applyCacheHeaders($request, $response);

        return $response;
    }

    private function applyCacheHeaders(Request $request, Response $response): void
    {
        // API responses include auth context and user-sensitive data.
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            return;
        }

        // Non-API responses from backend are safe to cache aggressively.
        $response->headers->set('Cache-Control', 'public, max-age=2592000, immutable');
    }
}
