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
        $userId = $request->attributes->get('auth.user_id');

        if ($scope['is_admin']) {
            $yacimientos = Yacimiento::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo']);
        } else {
            $assignedIds = [];
            if ($userId) {
                $assignedIds = \Illuminate\Support\Facades\DB::table('usuario_yacimientos')
                    ->where('usuario_id', (int) $userId)
                    ->pluck('yacimiento_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            $yacimientos = Yacimiento::query()
                ->whereIn('id', $assignedIds !== [] ? $assignedIds : [-1])
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo']);
        }

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
