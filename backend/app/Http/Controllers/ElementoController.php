<?php

namespace App\Http\Controllers;

use App\Models\Elemento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElementoController extends Controller
{
    public function listElements(Request $request): JsonResponse
    {
        $empresaId = $request->header('X-Empresa-Id', $request->query('empresa_id'));

        $elementos = Elemento::query()
            ->with(['tipoElemento:id,nombre,codigo', 'criticidad:id,nombre,nivel,color', 'yacimiento:id,empresa_id,nombre'])
            ->forEmpresa($empresaId)
            ->orderBy('nombre')
            ->get()
            ->map(fn (Elemento $elemento): array => [
                'id' => $elemento->id,
                'nombre' => $elemento->nombre,
                'codigo' => $elemento->codigo,
                'tipo_elemento_id' => $elemento->tipo_elemento_id,
                'tipo' => $elemento->tipoElemento?->nombre,
                'funcion' => $elemento->funcion,
                'empresa_id' => $elemento->yacimiento?->empresa_id,
                'yacimiento' => $elemento->yacimiento?->nombre,
                'ubicacion' => $elemento->ubicacion_descripcion,
                'criticidad' => $elemento->criticidad?->nombre,
                'criticidad_nivel' => $elemento->criticidad?->nivel,
                'criticidad_color' => $elemento->criticidad?->color,
            ]);

        return response()->json($elementos);
    }
}
