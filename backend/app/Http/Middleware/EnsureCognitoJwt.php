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

        $dbUser = null;
        $identityCandidates = array_values(array_unique(array_filter([
            $claims['email'] ?? null,
            $claims['cognito:username'] ?? null,
            $claims['username'] ?? null,
            $claims['preferred_username'] ?? null,
        ], fn ($value): bool => is_string($value) && trim($value) !== '')));

        foreach ($identityCandidates as $identity) {
            $normalized = mb_strtolower(trim($identity));
            $dbUser = \Illuminate\Support\Facades\DB::table('usuarios')
                ->whereRaw('LOWER(email) = ?', [$normalized])
                ->orWhereRaw("split_part(LOWER(email), '@', 1) = ?", [$normalized])
                ->first();

            if ($dbUser) {
                break;
            }
        }

        if ($dbUser && ! $empresaId) {
            $empresaId = $dbUser->empresa_id;
        }

        $request->attributes->set('auth.claims', $claims);
        $request->attributes->set('auth.empresa_id', $empresaId ? (int) $empresaId : null);
        $request->attributes->set('auth.user_id', $dbUser ? $dbUser->id : null);

        if ($empresaId && ! $request->headers->has('X-Empresa-Id')) {
            $request->headers->set('X-Empresa-Id', (string) $empresaId);
        }

        return $next($request);
    }
}
