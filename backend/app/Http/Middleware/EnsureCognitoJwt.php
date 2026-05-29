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

        // If user is already authenticated (e.g., via actingAs in tests), use it
        if ($authUser = auth()->user()) {
            $request->attributes->set('auth.user_id', $authUser->id);
            $request->attributes->set('auth.empresa_id', $authUser->empresa_id);
            $request->attributes->set('auth.claims', [
                'email' => $authUser->email,
                'cognito:username' => $authUser->email,
            ]);

            return $next($request);
        }

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

        // Resolve identity using EXACT email matching against Cognito-assigned claims.
        //  - email: trusted only when verified (present in id tokens).
        //  - cognito:username / username: Cognito-assigned, immutable and unique, so
        //    matching them exactly is safe. Access tokens carry identity here, not in
        //    an email claim, so we accept them when they look like an email.
        // We deliberately EXCLUDE preferred_username (user-settable) and never use
        // prefix/LIKE matching, which previously let "alice" resolve to "alice@x.com".
        $identityCandidates = [];
        if ($this->emailClaimIsVerified($claims)) {
            $identityCandidates[] = (string) $claims['email'];
        }
        foreach (['cognito:username', 'username'] as $claimKey) {
            $value = $claims[$claimKey] ?? null;
            if (is_string($value) && str_contains($value, '@')) {
                $identityCandidates[] = $value;
            }
        }
        $identityCandidates = array_values(array_unique(array_filter(
            array_map(fn (string $value): string => mb_strtolower(trim($value)), $identityCandidates),
            fn (string $value): bool => $value !== ''
        )));

        $dbUser = null;
        foreach ($identityCandidates as $normalized) {
            $dbUser = DB::table('usuarios')
                ->whereRaw('LOWER(email) = ?', [$normalized])
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

    /**
     * The email claim is usable for identity resolution only when it is present
     * and not explicitly flagged as unverified. Cognito sends email_verified as a
     * boolean true (ID tokens) or the string "true"; access tokens may omit it
     * entirely, in which case the email claim is accepted as-is.
     */
    private function emailClaimIsVerified(array $claims): bool
    {
        $email = $claims['email'] ?? null;
        if (! is_string($email) || trim($email) === '') {
            return false;
        }

        if (! array_key_exists('email_verified', $claims)) {
            return true;
        }

        $verified = $claims['email_verified'];

        return $verified === true
            || $verified === 1
            || $verified === '1'
            || (is_string($verified) && mb_strtolower(trim($verified)) === 'true');
    }
}
