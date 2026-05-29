<?php

use App\Http\Middleware\EnsureCognitoJwt;
use App\Http\Middleware\EnsureRoleFromClaims;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->remove(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'cognito.auth' => EnsureCognitoJwt::class,
            'role.claim' => EnsureRoleFromClaims::class,
        ]);

        // CSRF Protection Strategy (M5):
        // This API is stateless and JWT-authenticated. State-changing operations (POST, PUT, PATCH, DELETE)
        // are protected by:
        // 1. JWT token authentication (requires valid Cognito token)
        // 2. HTTPS-only communication (enforced by infrastructure)
        // 3. SameSite cookies not used (API doesn't use session cookies)
        //
        // Traditional CSRF tokens are not needed for JSON API endpoints with JWT auth.
        // If form-based flows are ever added, apply VerifyCsrfToken middleware explicitly.
        //
        // All requests follow RFC 7231 semantics: GET/HEAD/OPTIONS are idempotent;
        // POST/PUT/PATCH/DELETE require Authorization header with valid JWT.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
