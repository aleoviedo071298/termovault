<?php

use App\Http\Middleware\EnsureCognitoJwt;
use App\Http\Middleware\EnsureRoleFromClaims;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        // Respuestas de error genéricas para la API: no revelar el framework
        // (Laravel) ni la ruta solicitada. Aplica solo a /api/* o peticiones
        // que esperan JSON; el resto conserva el comportamiento por defecto.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // comportamiento por defecto (no-API)
            }

            // 404: ruta o recurso inexistente.
            if ($e instanceof NotFoundHttpException) {
                return response()->json(['error' => 'Recurso no encontrado'], 404);
            }

            // Otras HttpException (405, 403 lanzadas como abort, etc.):
            // respetar el status pero con mensaje neutro y sin detalle interno.
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $generic = [
                    400 => 'Solicitud inválida',
                    401 => 'No autenticado',
                    403 => 'Acceso denegado',
                    405 => 'Método no permitido',
                    429 => 'Demasiadas solicitudes',
                ];

                // No interceptar errores de validación (422): el cliente necesita
                // el detalle de los campos. Se delega al manejador por defecto.
                if ($status === 422) {
                    return null;
                }

                return response()->json([
                    'error' => $generic[$status] ?? 'Error',
                ], $status);
            }

            return null; // 500 y demás: manejador por defecto (respeta APP_DEBUG=false)
        });
    })->create();
