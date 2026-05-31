<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation errors.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into a response.
     */
    public function render($request, Throwable $exception): JsonResponse|\Illuminate\Http\Response
    {
        // Personalizar respuesta 404 para API
        if ($exception instanceof NotFoundHttpException && $request->expectsJson()) {
            return response()->json([
                'error' => 'Recurso no encontrado',
                'status' => 404,
            ], 404);
        }

        return parent::render($request, $exception);
    }
}
