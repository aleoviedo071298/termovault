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

        $dbUser = null;
        if ($userId) {
            $dbUser = Usuario::query()->with('rol:id,codigo', 'yacimientos:id,nombre,codigo', 'empresa:id,nombre')->find($userId);
        }

        $roles = $this->extractRoles($claims, $dbUser);

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
            'empresa_nombre' => $dbUser?->empresa?->nombre,
            'user_name' => $dbUser ? trim($dbUser->nombre . ' ' . $dbUser->apellido) : null,
            'roles' => $roles,
            'is_admin' => $isAdmin,
            'is_supervisor' => $isSupervisor,
            'is_tecnico' => $isTecnico,
            'is_pae_supervisor' => $isSupervisor && $isPaeCompany && in_array('YAC-PAE', $assignedYacimientoCodes, true),
            'assigned_yacimiento_ids' => $assignedYacimientoIds,
            'assigned_yacimiento_codes' => $assignedYacimientoCodes,
            'assigned_yacimiento_names' => $dbUser
                ? $dbUser->yacimientos->pluck('nombre')->filter()->values()->all()
                : [],
        ];
    }

    public function applyElementScope(Builder $query, array $scope): Builder
    {
        if ($scope['is_admin']) {
            return $query;
        }

        if ($scope['is_tecnico'] || $scope['is_supervisor']) {
            if ($scope['assigned_yacimiento_ids'] !== []) {
                return $query->whereIn('yacimiento_id', $scope['assigned_yacimiento_ids']);
            }

            if ($scope['empresa_id']) {
                return $query->whereIn('yacimiento_id', function ($sub) use ($scope): void {
                    $sub->select('id')
                        ->from('yacimientos')
                        ->where('empresa_id', (int) $scope['empresa_id']);
                });
            }
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
        $element = DB::table('elementos as e')
            ->join('yacimientos as y', 'y.id', '=', 'e.yacimiento_id')
            ->select('e.id', 'e.yacimiento_id', 'y.empresa_id')
            ->where('e.id', $elementId)
            ->first();
        if (! $element) {
            return false;
        }

        if ($scope['is_admin']) {
            return true;
        }

        if ($scope['is_tecnico']) {
            if ($scope['assigned_yacimiento_ids'] === []) {
                return (int) ($scope['empresa_id'] ?? 0) === (int) $element->empresa_id;
            }
            return in_array((int) $element->yacimiento_id, $scope['assigned_yacimiento_ids'], true);
        }

        if ($scope['is_supervisor']) {
            if ($scope['assigned_yacimiento_ids'] === []) {
                return (int) ($scope['empresa_id'] ?? 0) === (int) $element->empresa_id;
            }
            return in_array((int) $element->yacimiento_id, $scope['assigned_yacimiento_ids'], true);
        }

        return false;
    }

    private function extractRoles(array $claims, ?Usuario $dbUser = null): array
    {
        $localRole = $dbUser?->rol?->codigo;
        if (is_string($localRole) && trim($localRole) !== '') {
            return [mb_strtolower(trim($localRole))];
        }

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
