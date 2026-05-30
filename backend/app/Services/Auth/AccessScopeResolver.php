<?php

namespace App\Services\Auth;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccessScopeResolver
{
    public function resolve(Request $request): array
    {
        $userId = $request->attributes->get('auth.user_id');
        $empresaId = $request->attributes->get('auth.empresa_id');
        $claims = $request->attributes->get('auth.claims', []);

        $dbUser = null;
        if ($userId) {
            $dbUser = Usuario::query()->with('rol:id,codigo', 'yacimientos:id,nombre,codigo,empresa_id,permite_supervisor_elementos', 'empresa:id,nombre')->find($userId);
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

        $ownerYacimientoIds = [];
        if ($dbUser) {
            $ownerYacimientoIds = $dbUser->yacimientos
                ->filter(fn ($yacimiento) => (bool) ($yacimiento->permite_supervisor_elementos ?? false))
                ->filter(fn ($yacimiento) => (int) ($yacimiento->empresa_id ?? 0) === (int) $dbUser->empresa_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }
        $isOwnerSupervisor = $isSupervisor && $ownerYacimientoIds !== [];

        return [
            'user_id' => $dbUser?->id ? (int) $dbUser->id : null,
            'empresa_id' => $empresaId ? (int) $empresaId : null,
            'empresa_nombre' => $dbUser?->empresa?->nombre,
            'user_name' => $dbUser ? trim($dbUser->nombre . ' ' . $dbUser->apellido) : null,
            'roles' => $roles,
            'is_admin' => $isAdmin,
            'is_supervisor' => $isSupervisor,
            'is_tecnico' => $isTecnico,
            'is_owner_supervisor' => $isOwnerSupervisor,
            'owner_yacimiento_ids' => $ownerYacimientoIds,
            'assigned_yacimiento_ids' => $assignedYacimientoIds,
            'assigned_yacimiento_codes' => $assignedYacimientoCodes,
            'assigned_yacimiento_names' => $dbUser
                ? $dbUser->yacimientos->pluck('nombre')->filter()->values()->all()
                : [],
        ];
    }

    /**
     * Apply yacimiento scope filtering to elemento query.
     *
     * M6 Design Decision: Admins intentionally bypass yacimiento filtering.
     * This allows admins to perform enterprise-wide operations without being restricted
     * by yacimiento assignment. Non-admin roles are restricted to their assigned yacimientos.
     *
     * Authorization checks in controllers (canMutateElement, canCreateInspectionForElement)
     * still apply at the business logic level, ensuring:
     * - Supervisors can only mutate elements in their assigned yacimientos
     * - Tecnicos can only create inspections for their assigned elements
     * - Admins can perform any operation
     *
     * @param Builder $query
     * @param array $scope Access scope from AccessScopeResolver::resolve()
     * @return Builder Modified query with yacimiento scope applied
     */
    public function applyElementScope(Builder $query, array $scope): Builder
    {
        if ($scope['is_admin']) {
            // Admin bypass: intentional design decision for enterprise operations
            return $query;
        }

        // Técnico: fail-closed (M4, Opción A). Sin yacimientos asignados no ve nada;
        // un admin debe asignarlo explícitamente. Lectura y escritura quedan alineadas.
        if ($scope['is_tecnico'] && ! $scope['is_supervisor']) {
            if ($scope['assigned_yacimiento_ids'] !== []) {
                return $query->whereIn('yacimiento_id', $scope['assigned_yacimiento_ids']);
            }

            return $query->whereRaw('1 = 0');
        }

        // Supervisor: por yacimientos asignados o, en su defecto, por empresa
        // (un supervisor de contratista sin asignación puntual ve los de su empresa).
        if ($scope['is_supervisor']) {
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
            $this->logScopeViolation('element.mutate.denied.non_supervisor', $scope, [
                'target_yacimiento_id' => $yacimientoId,
            ]);
            return false;
        }

        $allowed = ($scope['is_owner_supervisor'] ?? false)
            && in_array($yacimientoId, $scope['owner_yacimiento_ids'] ?? [], true);

        if (! $allowed) {
            $this->logScopeViolation('element.mutate.denied.out_of_scope', $scope, [
                'target_yacimiento_id' => $yacimientoId,
            ]);
        }

        return $allowed;
    }

    public function canCreateInspectionForElement(array $scope, int $elementId): bool
    {
        $element = DB::table('elementos as e')
            ->join('yacimientos as y', 'y.id', '=', 'e.yacimiento_id')
            ->select('e.id', 'e.yacimiento_id', 'y.empresa_id')
            ->where('e.id', $elementId)
            ->first();
        if (! $element) {
            $this->logScopeViolation('inspection.create.denied.element_not_found', $scope, [
                'target_element_id' => $elementId,
            ]);
            return false;
        }

        if ($scope['is_admin']) {
            return true;
        }

        if ($scope['is_tecnico'] && ! $scope['is_supervisor']) {
            // M4, Opción A: técnico sin yacimientos asignados no puede crear inspecciones.
            if ($scope['assigned_yacimiento_ids'] === []) {
                $this->logScopeViolation('inspection.create.denied.tecnico_unassigned', $scope, [
                    'target_element_id' => $elementId,
                ]);
                return false;
            }
            $allowed = in_array((int) $element->yacimiento_id, $scope['assigned_yacimiento_ids'], true);
            if (! $allowed) {
                $this->logScopeViolation('inspection.create.denied.yacimiento_unassigned', $scope, [
                    'target_element_id' => $elementId,
                    'target_yacimiento_id' => (int) $element->yacimiento_id,
                ]);
            }
            return $allowed;
        }

        if ($scope['is_supervisor']) {
            if ($scope['assigned_yacimiento_ids'] === []) {
                $allowed = (int) ($scope['empresa_id'] ?? 0) === (int) $element->empresa_id;
                if (! $allowed) {
                    $this->logScopeViolation('inspection.create.denied.empresa_mismatch', $scope, [
                        'target_element_id' => $elementId,
                        'target_empresa_id' => (int) $element->empresa_id,
                    ]);
                }
                return $allowed;
            }
            $allowed = in_array((int) $element->yacimiento_id, $scope['assigned_yacimiento_ids'], true);
            if (! $allowed) {
                $this->logScopeViolation('inspection.create.denied.yacimiento_unassigned', $scope, [
                    'target_element_id' => $elementId,
                    'target_yacimiento_id' => (int) $element->yacimiento_id,
                ]);
            }
            return $allowed;
        }

        $this->logScopeViolation('inspection.create.denied.role_not_allowed', $scope, [
            'target_element_id' => $elementId,
        ]);
        return false;
    }

    private function logScopeViolation(string $event, array $scope, array $extra = []): void
    {
        Log::notice('auth.scope.violation', array_merge([
            'event' => $event,
            'user_id' => $scope['user_id'] ?? null,
            'empresa_id' => $scope['empresa_id'] ?? null,
            'roles' => $scope['roles'] ?? [],
            'assigned_yacimiento_ids' => $scope['assigned_yacimiento_ids'] ?? [],
            'owner_yacimiento_ids' => $scope['owner_yacimiento_ids'] ?? [],
        ], $extra));
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
