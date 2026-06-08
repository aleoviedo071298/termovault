<?php

namespace App\Http\Controllers;

use App\Services\AuditTrail;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function index(): JsonResponse
    {
        $users = Usuario::query()
            ->with(['rol:id,codigo,nombre', 'empresa:id,nombre', 'yacimientos:id,nombre,codigo'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (Usuario $user) => [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'email' => $user->email,
                'activo' => $user->activo,
                'empresa_id' => $user->empresa_id,
                'empresa' => $user->empresa?->nombre,
                'rol' => $user->rol?->codigo,
                'yacimientos' => $user->yacimientos->map(fn ($y) => [
                    'id' => $y->id,
                    'nombre' => $y->nombre,
                    'codigo' => $y->codigo,
                ])->values(),
            ]);

        return response()->json($users);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'roles' => DB::table('roles')->select('id', 'codigo', 'nombre')->orderBy('id')->get(),
            'empresas' => DB::table('empresas')->select('id', 'nombre')->where('activo', true)->orderBy('nombre')->get(),
            'yacimientos' => DB::table('yacimientos')
                ->select('id', 'nombre', 'codigo', 'empresa_id', 'permite_supervisor_elementos')
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request, null);
        $rol = DB::table('roles')->where('codigo', $data['rol_codigo'])->first();
        if (! $rol) {
            return response()->json(['message' => 'Rol invalido'], 422);
        }

        $email = mb_strtolower(trim($data['email']));
        $yacimientos = collect($data['yacimientos'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        $user = DB::transaction(function () use ($data, $rol, $email, $yacimientos) {
            $user = Usuario::query()->create([
                'empresa_id' => (int) $data['empresa_id'],
                'rol_id' => (int) $rol->id,
                'nombre' => trim($data['nombre']),
                'apellido' => trim($data['apellido']),
                'email' => $email,
                'activo' => true,
            ]);

            $user->yacimientos()->sync($yacimientos->all());

            return $user->load(['rol:id,codigo,nombre', 'empresa:id,nombre', 'yacimientos:id,nombre,codigo']);
        });

        $actorId = $request->attributes->get('auth.user_id');

        $this->auditTrail->record('usuario.created', [
            'actor_user_id' => $actorId,
            'usuario_id' => $user->id,
            'rol' => $user->rol?->codigo,
            'empresa_id' => $user->empresa_id,
        ]);

        // FIX [002]: crear directamente un usuario con privilegio admin es un
        // evento sensible que debe alertarse igual que una escalada.
        if ($this->roleRank($user->rol?->codigo) >= $this->roleRank('admin')) {
            $this->auditTrail->alert('usuario.created_with_admin_privilege', [
                'actor_user_id' => $actorId,
                'usuario_id' => $user->id,
                'rol' => $user->rol?->codigo,
            ]);
        }

        return response()->json([
            'id' => $user->id,
            'nombre' => $user->nombre,
            'apellido' => $user->apellido,
            'email' => $user->email,
            'activo' => $user->activo,
            'empresa_id' => $user->empresa_id,
            'rol' => $user->rol?->codigo,
            'empresa' => $user->empresa?->nombre,
            'yacimientos' => $user->yacimientos->map(fn ($y) => ['id' => $y->id, 'nombre' => $y->nombre, 'codigo' => $y->codigo])->values(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = Usuario::query()->with(['rol:id,codigo', 'yacimientos:id'])->find($id);
        if (! $user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $data = $this->validatePayload($request, $id);
        $rol = DB::table('roles')->where('codigo', $data['rol_codigo'])->first();
        if (! $rol) {
            return response()->json(['message' => 'Rol invalido'], 422);
        }

        // FIX [002]: snapshot del estado ANTES del cambio, para poder auditar
        // escaladas de privilegio (rol), reactivaciones y reasignaciones.
        $previous = [
            'rol' => $user->rol?->codigo,
            'empresa_id' => $user->empresa_id !== null ? (int) $user->empresa_id : null,
            'activo' => (bool) $user->activo,
            'yacimiento_ids' => $user->yacimientos->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all(),
        ];

        $email = mb_strtolower(trim($data['email']));
        $yacimientos = collect($data['yacimientos'] ?? [])->map(fn ($value) => (int) $value)->unique()->values();

        DB::transaction(function () use ($user, $data, $rol, $email, $yacimientos): void {
            $user->update([
                'empresa_id' => (int) $data['empresa_id'],
                'rol_id' => (int) $rol->id,
                'nombre' => trim($data['nombre']),
                'apellido' => trim($data['apellido']),
                'email' => $email,
                'activo' => (bool) ($data['activo'] ?? true),
            ]);

            $user->yacimientos()->sync($yacimientos->all());
        });

        $fresh = $user->load(['rol:id,codigo,nombre', 'empresa:id,nombre', 'yacimientos:id,nombre,codigo']);

        $current = [
            'rol' => $fresh->rol?->codigo,
            'empresa_id' => $fresh->empresa_id !== null ? (int) $fresh->empresa_id : null,
            'activo' => (bool) $fresh->activo,
            'yacimiento_ids' => $fresh->yacimientos->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all(),
        ];

        $actorId = $request->attributes->get('auth.user_id');

        // Auditoría enriquecida con before/after (antes solo se registraba el estado final).
        $this->auditTrail->record('usuario.updated', [
            'actor_user_id' => $actorId,
            'usuario_id' => $fresh->id,
            'previous' => $previous,
            'current' => $current,
        ]);

        // Alerta de escalada de privilegio: el rol subió de jerarquía
        // (tecnico < supervisor < admin). Evento WARNING para alerting.
        if ($this->roleRank($current['rol']) > $this->roleRank($previous['rol'])) {
            $this->auditTrail->alert('usuario.privilege_escalated', [
                'actor_user_id' => $actorId,
                'usuario_id' => $fresh->id,
                'from_rol' => $previous['rol'],
                'to_rol' => $current['rol'],
            ]);
        }

        // Alerta de reactivación: una cuenta deshabilitada vuelve a estar activa.
        if (! $previous['activo'] && $current['activo']) {
            $this->auditTrail->alert('usuario.reactivated', [
                'actor_user_id' => $actorId,
                'usuario_id' => $fresh->id,
            ]);
        }

        return response()->json([
            'id' => $fresh->id,
            'nombre' => $fresh->nombre,
            'apellido' => $fresh->apellido,
            'email' => $fresh->email,
            'activo' => $fresh->activo,
            'empresa_id' => $fresh->empresa_id,
            'empresa' => $fresh->empresa?->nombre,
            'rol' => $fresh->rol?->codigo,
            'yacimientos' => $fresh->yacimientos->map(fn ($y) => ['id' => $y->id, 'nombre' => $y->nombre, 'codigo' => $y->codigo])->values(),
        ]);
    }

    /**
     * Jerarquía de roles para detectar escaladas de privilegio.
     * Un valor mayor implica más privilegios.
     */
    private function roleRank(?string $code): int
    {
        return match ($code) {
            'admin' => 3,
            'supervisor' => 2,
            'tecnico' => 1,
            default => 0,
        };
    }

    private function validatePayload(Request $request, ?int $ignoreId): array
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'empresa_id' => 'required|exists:empresas,id',
            'rol_codigo' => 'required|in:admin,supervisor,tecnico',
            'yacimientos' => 'nullable|array',
            'yacimientos.*' => 'integer|exists:yacimientos,id',
            'activo' => 'nullable|boolean',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $emailQuery = DB::table('usuarios')->whereRaw('LOWER(email) = ?', [$email]);
        if ($ignoreId) {
            $emailQuery->where('id', '!=', $ignoreId);
        }
        if ($emailQuery->exists()) {
            abort(response()->json(['message' => 'Ya existe un usuario local con ese email'], 422));
        }

        return $data;
    }
}
