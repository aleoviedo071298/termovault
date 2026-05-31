<?php

namespace App\Http\Controllers;

use App\Models\Criticidad;
use App\Models\NivelTension;
use App\Models\TipoElemento;
use App\Models\Yacimiento;
use App\Services\Auth\AccessScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(private readonly AccessScopeResolver $scopeResolver) {}

    public function index(Request $request): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);

        if ($scope['is_admin']) {
            $yacimientos = Yacimiento::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo']);
        } else {
            $yacimientoIds = $scope['assigned_yacimiento_ids'] ?? [];

            if ($yacimientoIds === [] && $scope['is_supervisor'] && $scope['empresa_id']) {
                $yacimientoIds = Yacimiento::query()
                    ->where('empresa_id', (int) $scope['empresa_id'])
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            $yacimientos = Yacimiento::query()
                ->whereIn('id', $yacimientoIds !== [] ? $yacimientoIds : [-1])
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo']);
        }

        $catalogs = \Illuminate\Support\Facades\Cache::remember('catalogs.static', 3600, function () {
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

            return [
                'tipos_elemento' => $tipos,
                'niveles_tension' => $tensiones,
                'criticidades' => $criticidades,
            ];
        });

        return response()->json([
            'yacimientos' => $yacimientos,
            'tipos_elemento' => $catalogs['tipos_elemento'],
            'niveles_tension' => $catalogs['niveles_tension'],
            'criticidades' => $catalogs['criticidades'],
        ]);
    }
}
