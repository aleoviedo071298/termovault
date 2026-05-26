<?php

namespace App\Http\Controllers;

use App\Models\Criticidad;
use App\Models\NivelTension;
use App\Models\TipoElemento;
use App\Models\Yacimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('auth.empresa_id');

        $yacimientos = Yacimiento::query()
            ->when($empresaId, fn ($query) => $query->where('empresa_id', $empresaId))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        $tipos = TipoElemento::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'requiere_tension']);

        $tensiones = NivelTension::query()
            ->where('activo', true)
            ->orderBy('kv')
            ->get(['id', 'kv', 'etiqueta']);

        $criticidades = Criticidad::query()
            ->orderBy('nivel')
            ->get(['id', 'nivel', 'nombre', 'color']);

        return response()->json([
            'yacimientos' => $yacimientos,
            'tipos_elemento' => $tipos,
            'niveles_tension' => $tensiones,
            'criticidades' => $criticidades,
        ]);
    }
}
