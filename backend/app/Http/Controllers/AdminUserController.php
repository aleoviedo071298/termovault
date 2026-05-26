<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
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
            'yacimientos' => DB::table('yacimientos')->select('id', 'nombre', 'codigo', 'empresa_id')->where('activo', true)->orderBy('nombre')->get(),
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
                'password_hash' => password_hash(Str::random(32), PASSWORD_BCRYPT),
                'legajo' => $data['legajo'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'activo' => true,
            ]);

            $user->yacimientos()->sync($yacimientos->all());

            return $user->load(['rol:id,codigo,nombre', 'empresa:id,nombre', 'yacimientos:id,nombre,codigo']);
        });

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
        $user = Usuario::query()->find($id);
        if (! $user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $data = $this->validatePayload($request, $id);
        $rol = DB::table('roles')->where('codigo', $data['rol_codigo'])->first();
        if (! $rol) {
            return response()->json(['message' => 'Rol invalido'], 422);
        }

        $email = mb_strtolower(trim($data['email']));
        $yacimientos = collect($data['yacimientos'] ?? [])->map(fn ($value) => (int) $value)->unique()->values();

        DB::transaction(function () use ($user, $data, $rol, $email, $yacimientos): void {
            $user->update([
                'empresa_id' => (int) $data['empresa_id'],
                'rol_id' => (int) $rol->id,
                'nombre' => trim($data['nombre']),
                'apellido' => trim($data['apellido']),
                'email' => $email,
                'legajo' => $data['legajo'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'activo' => (bool) ($data['activo'] ?? true),
            ]);

            $user->yacimientos()->sync($yacimientos->all());
        });

        $fresh = $user->load(['rol:id,codigo,nombre', 'empresa:id,nombre', 'yacimientos:id,nombre,codigo']);

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
            'legajo' => 'nullable|string|max:50',
            'telefono' => 'nullable|string|max:30',
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

