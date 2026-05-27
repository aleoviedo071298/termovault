<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalUserProvisioner
{
    public function findOrProvisionFromClaims(array $claims): ?object
    {
        $email = $this->resolveEmail($claims);
        if (! $email) {
            return null;
        }

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
        if (! $roleId) {
            return null;
        }

        $empresaId = $claims['custom:empresa_id'] ?? $claims['empresa_id'] ?? null;
        if ($empresaId && ! DB::table('empresas')->where('id', (int) $empresaId)->exists()) {
            $empresaId = null;
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

    private function resolveEmail(array $claims): ?string
    {
        $email = $claims['email'] ?? null;
        if (! is_string($email) || trim($email) === '') {
            $candidate = $claims['cognito:username'] ?? $claims['username'] ?? null;
            if (is_string($candidate) && str_contains($candidate, '@')) {
                $email = $candidate;
            }
        }

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return mb_strtolower(trim($email));
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
