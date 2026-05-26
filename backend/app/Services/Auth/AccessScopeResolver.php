<?php

namespace App\Services\Auth;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccessScopeResolver
{
    public function resolve(Request $request): array
    {
        $userId = $request->attributes->get('auth.user_id');
        $empresaId = $request->attributes->get('auth.empresa_id');
        $claims = $request->attributes->get('auth.claims', []);
        $roles = $this->extractRoles($claims);

        $dbUser = null;
        if ($userId) {
            $dbUser = Usuario::query()->with('yacimientos:id,codigo', 'empresa:id,nombre')->find($userId);
        }

        if ($dbUser && ! $empresaId) {
            $empresaId = $dbUser->empresa_id;
        }

        $isAdmin = in_array('admin', $roles, true);
        $isSupervisor = in_array('supervisor', $roles, true);
        $isTecnico = in_array('tecnico', $roles, true);

        $assignedYacimientoIds = [];
        $assignedYacimientoCodes = [];
        if ($dbUser) {
            $assignedYacimientoIds = $dbUser->yacimientos->pluck('id')->map(fn ($id) => (int) $id)->all();
            $assignedYacimientoCodes = $dbUser->yacimientos
                ->pluck('codigo')
                ->filter(fn ($code) => is_string($code) && trim($code) !== '')
                ->map(fn ($code) => mb_strtoupper(trim((string) $code)))
                ->values()
                ->all();
        }

        $isPaeCompany = false;
        if ($dbUser?->empresa?->nombre) {
            $companyName = mb_strtoupper(trim((string) $dbUser->empresa->nombre));
            $isPaeCompany = $companyName === 'PAE' || str_contains($companyName, 'PAE');
        }

        return [
            'user_id' => $dbUser?->id ? (int) $dbUser->id : null,
            'empresa_id' => $empresaId ? (int) $empresaId : null,
            'roles' => $roles,
            'is_admin' => $isAdmin,
            'is_supervisor' => $isSupervisor,
            'is_tecnico' => $isTecnico,
            'is_pae_supervisor' => $isSupervisor && $isPaeCompany && in_array('YAC-PAE', $assignedYacimientoCodes, true),
            'assigned_yacimiento_ids' => $assignedYacimientoIds,
        ];
    }

    public function applyElementScope(Builder $query, array $scope): Builder
    {
        if ($scope['is_admin']) {
            return $query;
        }

        if (($scope['is_tecnico'] || $scope['is_supervisor']) && $scope['assigned_yacimiento_ids'] !== []) {
            return $query->whereIn('yacimiento_id', $scope['assigned_yacimiento_ids']);
        }

        return $query->whereRaw('1 = 0');
    }

    public function canMutateElement(array $scope, int $yacimientoId): bool
    {
        if ($scope['is_admin']) {
            return true;
        }

        if (! $scope['is_supervisor']) {
            return false;
        }

        // Solo supervisor PAE puede administrar elementos.
        return $scope['is_pae_supervisor']
            && in_array($yacimientoId, $scope['assigned_yacimiento_ids'], true);
    }

    public function canCreateInspectionForElement(array $scope, int $elementId): bool
    {
        $element = DB::table('elementos')->select('id', 'yacimiento_id')->where('id', $elementId)->first();
        if (! $element) {
            return false;
        }

        if ($scope['is_admin']) {
            return true;
        }

        if ($scope['is_tecnico']) {
            return in_array((int) $element->yacimiento_id, $scope['assigned_yacimiento_ids'], true);
        }

        if ($scope['is_supervisor']) {
            return in_array((int) $element->yacimiento_id, $scope['assigned_yacimiento_ids'], true);
        }

        return false;
    }

    private function extractRoles(array $claims): array
    {
        $fromGroups = $claims['cognito:groups'] ?? [];
        if (is_string($fromGroups)) {
            $fromGroups = explode(',', $fromGroups);
        }

        if (! is_array($fromGroups)) {
            $fromGroups = [];
        }

        return collect($fromGroups)
            ->filter(fn ($role) => is_string($role) && trim($role) !== '')
            ->map(fn (string $role): string => mb_strtolower(trim($role)))
            ->unique()
            ->values()
            ->all();
    }
}
