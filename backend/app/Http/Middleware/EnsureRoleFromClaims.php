<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleFromClaims
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $claims = $request->attributes->get('auth.claims');
        if (! is_array($claims)) {
            return response()->json([
                'message' => 'Missing authentication claims',
            ], 401);
        }

        $roles = $this->extractRoles($claims);
        if ($roles === []) {
            $roles = $this->extractRolesFromLocalUser($request);
        }
        if ($roles === []) {
            return response()->json([
                'message' => 'Role claim is missing and local role could not be resolved',
            ], 403);
        }

        $normalizedAllowed = array_map(fn (string $role): string => mb_strtolower(trim($role)), $allowedRoles);
        $hasAnyAllowedRole = collect($roles)
            ->map(fn (string $role): string => mb_strtolower(trim($role)))
            ->contains(fn (string $role): bool => in_array($role, $normalizedAllowed, true));

        if (! $hasAnyAllowedRole) {
            return response()->json([
                'message' => 'Insufficient role permissions',
                'required_roles' => $allowedRoles,
                'roles' => $roles,
            ], 403);
        }

        return $next($request);
    }

    private function extractRoles(array $claims): array
    {
        $fromGroups = $claims['cognito:groups'] ?? null;
        if (is_array($fromGroups)) {
            return array_values(array_filter($fromGroups, fn ($role): bool => is_string($role) && trim($role) !== ''));
        }

        if (is_string($fromGroups) && trim($fromGroups) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $fromGroups))));
        }

        $singleRole = $claims['custom:role'] ?? $claims['role'] ?? null;
        if (is_string($singleRole) && trim($singleRole) !== '') {
            return [trim($singleRole)];
        }

        return [];
    }

    private function extractRolesFromLocalUser(Request $request): array
    {
        $userId = $request->attributes->get('auth.user_id');
        if (! $userId) {
            return [];
        }

        $roleCode = \Illuminate\Support\Facades\DB::table('usuarios')
            ->join('roles', 'roles.id', '=', 'usuarios.rol_id')
            ->where('usuarios.id', (int) $userId)
            ->value('roles.codigo');

        if (! is_string($roleCode) || trim($roleCode) === '') {
            return [];
        }

        return [trim($roleCode)];
    }
}
