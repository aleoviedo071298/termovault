<?php

namespace App\Http\Controllers;

use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrganizationController extends Controller
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function createEmpresa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:150',
        ]);

        $id = DB::table('empresas')->insertGetId([
            'nombre' => trim($data['nombre']),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $empresa = DB::table('empresas')->where('id', $id)->first(['id', 'nombre']);
        $this->auditTrail->record('empresa.created', [
            'actor_user_id' => $request->attributes->get('auth.user_id'),
            'empresa_id' => $id,
        ]);
        return response()->json($empresa, 201);
    }

    public function createYacimiento(Request $request): JsonResponse
    {
        $data = $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:150',
            'codigo' => 'required|string|max:50',
            'permite_supervisor_elementos' => 'nullable|boolean',
        ]);

        $exists = DB::table('yacimientos')
            ->where('empresa_id', (int) $data['empresa_id'])
            ->whereRaw('LOWER(codigo) = ?', [mb_strtolower(trim($data['codigo']))])
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'Ya existe un yacimiento con ese código en la empresa seleccionada'], 422);
        }

        $id = DB::table('yacimientos')->insertGetId([
            'empresa_id' => (int) $data['empresa_id'],
            'nombre' => trim($data['nombre']),
            'codigo' => trim($data['codigo']),
            'activo' => true,
            'permite_supervisor_elementos' => (bool) ($data['permite_supervisor_elementos'] ?? false),
            'created_at' => now(),
        ]);

        $yac = DB::table('yacimientos')->where('id', $id)->first(['id', 'nombre', 'codigo', 'empresa_id', 'permite_supervisor_elementos']);
        $this->auditTrail->record('yacimiento.created', [
            'actor_user_id' => $request->attributes->get('auth.user_id'),
            'yacimiento_id' => $id,
            'empresa_id' => (int) $data['empresa_id'],
            'permite_supervisor_elementos' => (bool) ($data['permite_supervisor_elementos'] ?? false),
        ]);
        return response()->json($yac, 201);
    }
}
