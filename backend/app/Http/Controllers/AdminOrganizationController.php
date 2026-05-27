<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrganizationController extends Controller
{
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
        return response()->json($empresa, 201);
    }

    public function createYacimiento(Request $request): JsonResponse
    {
        $data = $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'nombre' => 'required|string|max:150',
            'codigo' => 'required|string|max:50',
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
            'created_at' => now(),
        ]);

        $yac = DB::table('yacimientos')->where('id', $id)->first(['id', 'nombre', 'codigo', 'empresa_id']);
        return response()->json($yac, 201);
    }
}
