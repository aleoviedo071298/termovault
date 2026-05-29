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
        if (! $empresaId) {
            return null;
        }

        if (! DB::table('empresas')->where('id', (int) $empresaId)->exists()) {
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
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('usuarios')->where('id', $id)->first();
    }

    private function resolveEmail(array $claims): ?string
    {
        // Identity from Cognito-assigned claims, EXACT match only. The email claim is
        // trusted when verified; cognito:username/username are Cognito-assigned and may
        // carry the email (access tokens). preferred_username is excluded (user-settable).
        if ($this->emailIsVerified($claims)) {
            $email = $claims['email'] ?? null;
            if (is_string($email) && trim($email) !== '') {
                return mb_strtolower(trim($email));
            }
        }

        foreach (['cognito:username', 'username'] as $claimKey) {
            $value = $claims[$claimKey] ?? null;
            if (is_string($value) && trim($value) !== '' && str_contains($value, '@')) {
                return mb_strtolower(trim($value));
            }
        }

        return null;
    }

    /**
     * The email claim is usable only when not explicitly flagged as unverified.
     * Cognito sends email_verified as boolean true (ID tokens) or the string
     * "true"; when the claim is absent the email is accepted as-is.
     */
    private function emailIsVerified(array $claims): bool
    {
        if (! array_key_exists('email_verified', $claims)) {
            return true;
        }

        $verified = $claims['email_verified'];

        return $verified === true
            || $verified === 1
            || $verified === '1'
            || (is_string($verified) && mb_strtolower(trim($verified)) === 'true');
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
