<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = [
            'https://app.example.com',
            'https://app.staging.example.com',
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ];
        $origin = $request->headers->get('Origin');

        if ($request->isMethod('OPTIONS')) {
            $response = response()->noContent(204);
            if (in_array($origin, $allowedOrigins, true)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
                $response->headers->set('Access-Control-Allow-Credentials', 'false');
                $response->headers->set('Access-Control-Max-Age', '3600');
            }
            return $response;
        }

        $response = $next($request);

        if (in_array($origin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            $response->headers->set('Access-Control-Allow-Credentials', 'false');
            $response->headers->set('Access-Control-Max-Age', '3600');
            // Permite que el navegador lea estos headers en respuestas cross-origin.
            // Necesario para que el frontend obtenga el nombre de archivo en las descargas.
            $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        }

        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Disable framing (prevent clickjacking)
        $response->headers->set('X-Frame-Options', 'DENY');

        // CSP Header (producción, sin orígenes de desarrollo).
        // Nota: estas respuestas son JSON de la API; la CSP relevante para XSS es
        // la del documento HTML (servida por Nginx en el frontend).
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: https:; " .
            "connect-src 'self' https://cognito-idp.us-east-2.amazonaws.com; " .
            "frame-ancestors 'none'; " .
            "base-uri 'self'; " .
            "form-action 'self'"
        );

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'no-referrer');

        // Permissions Policy
        $response->headers->set('Permissions-Policy', 
            'geolocation=(), microphone=(), camera=(), payment=()'
        );

        $this->applyCacheHeaders($request, $response);

        return $response;
    }

    private function applyCacheHeaders(Request $request, Response $response): void
    {
        // Static assets or public files from backend
        if ($request->is('public/*') || $request->is('*.js') || $request->is('*.css') || $request->is('*.png') || $request->is('*.jpg') || $request->is('*.gif') || $request->is('*.svg') || $request->is('*.woff2')) {
            $response->headers->set('Cache-Control', 'public, max-age=2592000, immutable');
            return;
        }

        // API responses with cacheable static data: 1 hour
        if ($request->is('api/catalogos')) {
            $response->headers->set('Cache-Control', 'public, max-age=3600');
            return;
        }

        // User-specific or sensitive API data: Don't cache
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            return;
        }

        // Default Cache-Control for backend HTML or non-API responses
        $response->headers->set('Cache-Control', 'public, max-age=2592000, immutable');
    }
}
