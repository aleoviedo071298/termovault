<?php

namespace App\Http\Middleware;

use App\Services\Auth\LocalUserProvisioner;
use App\Services\CognitoJwtVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureCognitoJwt
{
    public function __construct(
        private readonly CognitoJwtVerifier $verifier,
        private readonly LocalUserProvisioner $provisioner,
    ) {}

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
            Log::warning('Cognito JWT verification failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Invalid token',
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
            $dbUser = DB::table('usuarios')
                ->where(function ($query) use ($normalized): void {
                    $query->whereRaw('LOWER(email) = ?', [$normalized])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$normalized . '@%']);
                })
                ->first();

            if ($dbUser) {
                break;
            }
        }

        // Auto-provision local user when Cognito auth works but local record is missing.
        if (! $dbUser) {
            $dbUser = $this->provisioner->findOrProvisionFromClaims($claims);
        }

        if (! $dbUser) {
            Log::warning('Cognito user could not be resolved locally', [
                'email' => $claims['email'] ?? $claims['cognito:username'] ?? null,
                'has_empresa_claim' => isset($claims['custom:empresa_id']) || isset($claims['empresa_id']),
            ]);

            return response()->json([
                'message' => 'Usuario no provisionado. Contacta a un administrador.',
            ], 403);
        }

        if ($dbUser && ! (bool) ($dbUser->activo ?? true)) {
            return response()->json([
                'message' => 'Usuario inactivo. Contacta a un administrador.',
            ], 403);
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
