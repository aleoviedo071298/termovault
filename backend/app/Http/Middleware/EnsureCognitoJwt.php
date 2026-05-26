<?php

namespace App\Http\Middleware;

use App\Services\CognitoJwtVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            $dbUser = DB::table('usuarios')
                ->whereRaw('LOWER(email) = ?', [$normalized])
                ->orWhereRaw("split_part(LOWER(email), '@', 1) = ?", [$normalized])
                ->first();

            if ($dbUser) {
                break;
            }
        }

        // Auto-provision local user when Cognito auth works but local record is missing.
        if (! $dbUser) {
            $dbUser = $this->autoProvisionLocalUser($claims);
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

    private function autoProvisionLocalUser(array $claims): ?object
    {
        $email = $claims['email'] ?? null;
        if (! is_string($email) || trim($email) === '') {
            return null;
        }
        $email = mb_strtolower(trim($email));

        $existing = DB::table('usuarios')->whereRaw('LOWER(email)=?', [$email])->first();
        if ($existing) {
            return $existing;
        }

        $roleCode = $this->extractRoleCode($claims);
        $roleId = null;
        if ($roleCode) {
            $roleId = DB::table('roles')->whereRaw('LOWER(codigo)=?', [mb_strtolower($roleCode)])->value('id');
        }
        if (! $roleId) {
            $roleId = DB::table('roles')->where('codigo', 'tecnico')->value('id');
        }

        $empresaId = $claims['custom:empresa_id'] ?? $claims['empresa_id'] ?? null;
        if ($empresaId) {
            $empresaExists = DB::table('empresas')->where('id', (int) $empresaId)->exists();
            if (! $empresaExists) {
                $empresaId = null;
            }
        }
        if (! $empresaId) {
            $empresaId = DB::table('empresas')->orderBy('id')->value('id');
        }
        if (! $empresaId) {
            return null;
        }

        $nombre = trim((string) ($claims['given_name'] ?? 'Usuario'));
        $apellido = trim((string) ($claims['family_name'] ?? 'Cognito'));
        if ($nombre === '') {
            $nombre = Str::title(Str::before($email, '@'));
        }
        if ($apellido === '') {
            $apellido = 'Cognito';
        }

        $id = DB::table('usuarios')->insertGetId([
            'empresa_id' => (int) $empresaId,
            'rol_id' => (int) $roleId,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'password_hash' => password_hash(Str::random(32), PASSWORD_BCRYPT),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('usuarios')->where('id', $id)->first();
    }

    private function extractRoleCode(array $claims): ?string
    {
        $groups = $claims['cognito:groups'] ?? null;
        if (is_array($groups) && count($groups) > 0 && is_string($groups[0])) {
            return trim($groups[0]);
        }
        if (is_string($groups) && trim($groups) !== '') {
            return trim(explode(',', $groups)[0]);
        }
        $single = $claims['custom:role'] ?? $claims['role'] ?? null;
        if (is_string($single) && trim($single) !== '') {
            return trim($single);
        }
        return null;
    }
}
