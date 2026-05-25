<?php

namespace App\Http\Middleware;

use App\Services\CognitoJwtVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureCognitoJwt
{
    public function __construct(private readonly CognitoJwtVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $required = (bool) config('cognito.required', false);
        $header = $request->bearerToken();

        if (! $required && ! $header) {
            return $next($request);
        }

        if (! $header) {
            return response()->json([
                'message' => 'Missing Bearer token',
            ], 401);
        }

        try {
            $claims = $this->verifier->verify($header);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Invalid token',
                'error' => $exception->getMessage(),
            ], 401);
        }

        $empresaId = $claims['custom:empresa_id']
            ?? $claims['empresa_id']
            ?? null;

        $request->attributes->set('auth.claims', $claims);
        $request->attributes->set('auth.empresa_id', $empresaId ? (int) $empresaId : null);

        if ($empresaId && ! $request->headers->has('X-Empresa-Id')) {
            $request->headers->set('X-Empresa-Id', (string) $empresaId);
        }

        return $next($request);
    }
}
