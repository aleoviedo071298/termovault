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

        return $response;
    }
}
