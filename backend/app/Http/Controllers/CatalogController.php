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

        $yacimientos = Yacimiento::query()
            ->when($scope['empresa_id'], fn ($query) => $query->where('empresa_id', $scope['empresa_id']))
            ->when(
                $scope['is_supervisor'] && $scope['is_pae_supervisor'],
                fn ($query) => $query->whereIn('id', $scope['assigned_yacimiento_ids'])
            )
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
