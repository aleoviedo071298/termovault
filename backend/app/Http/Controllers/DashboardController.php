<?php

namespace App\Http\Controllers;

use App\Services\Auth\AccessScopeResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private readonly AccessScopeResolver $scopeResolver) {}

    public function overview(Request $request): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);
        $role = $scope['is_admin'] ? 'admin' : ($scope['is_supervisor'] ? 'supervisor' : 'tecnico');

        $reportsBase = DB::table('inspecciones as i')
            ->join('elementos as e', 'e.id', '=', 'i.elemento_id')
            ->join('yacimientos as y', 'y.id', '=', 'e.yacimiento_id')
            ->join('usuarios as u', 'u.id', '=', 'i.tecnico_id')
            ->join('empresas as emp', 'emp.id', '=', 'u.empresa_id');

        $reportsBase = $this->applyInspectionScope($reportsBase, $scope);

        // Resolve global dashboard cache version
        $version = \Illuminate\Support\Facades\Cache::rememberForever('dashboard.version', fn() => microtime(true));
        // FIX [I-01]: solo los 6 filtros validados entran en la key. Antes
        // se usaba $request->all() que dejaba a un usuario autenticado generar
        // cache entries arbitrarias enviando query params irrelevantes.
        $filterKey = json_encode([
            'estado' => $request->string('estado')->value(),
            'tecnico_id' => $request->string('tecnico_id')->value(),
            'empresa_id' => $request->string('empresa_id')->value(),
            'yacimiento_id' => $request->string('yacimiento_id')->value(),
            'fecha_desde' => $request->string('fecha_desde')->value(),
            'fecha_hasta' => $request->string('fecha_hasta')->value(),
        ]);
        $cacheKey = "dashboard.overview.{$scope['user_id']}.v{$version}." . md5($filterKey);

        $cachedData = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($scope, $role, $reportsBase, $request) {
            $this->applyFilters($reportsBase, $request);

            $reports = (clone $reportsBase)
                ->leftJoin('novedades as n', 'n.inspeccion_id', '=', 'i.id')
                ->leftJoin('criticidades as c', 'c.id', '=', 'n.criticidad_id')
                ->groupBy(
                    'i.id',
                    'i.fecha_inspeccion',
                    'i.estado',
                    'i.observaciones_revisor',
                    'e.nombre',
                    'e.codigo',
                    'u.nombre',
                    'u.apellido',
                    'emp.nombre',
                    'y.nombre'
                )
                ->orderByDesc('i.fecha_inspeccion')
                ->limit(20)
                ->get([
                    'i.id',
                    'i.fecha_inspeccion',
                    'i.estado',
                    'i.observaciones_revisor',
                    'e.nombre as elemento_nombre',
                    'e.codigo as elemento_codigo',
                    'u.nombre as tecnico_nombre',
                    'u.apellido as tecnico_apellido',
                    'emp.nombre as empresa_nombre',
                    'y.nombre as yacimiento_nombre',
                    DB::raw("COALESCE(MAX(c.nivel), 0) as criticidad_max_nivel"),
                    DB::raw("COUNT(DISTINCT CASE WHEN n.id IS NOT NULL THEN n.id END) as hallazgos"),
                ])
                ->map(function ($row): array {
                    $severity = match ((int) $row->criticidad_max_nivel) {
                        0 => 'Normal',
                        1 => 'Baja',
                        4 => 'Crítica',
                        3 => 'Alta',
                        2 => 'Media',
                        default => 'Normal',
                    };
                    return [
                        'id' => (int) $row->id,
                        'fecha_inspeccion' => $row->fecha_inspeccion,
                        'estado' => (string) $row->estado,
                        'observaciones_revisor' => $row->observaciones_revisor,
                        'elemento' => trim($row->elemento_nombre . ' (' . $row->elemento_codigo . ')'),
                        'tecnico' => trim($row->tecnico_nombre . ' ' . $row->tecnico_apellido),
                        'empresa' => $row->empresa_nombre,
                        'yacimiento' => $row->yacimiento_nombre,
                        'criticidad' => $severity,
                        'hallazgos' => (int) $row->hallazgos,
                    ];
                })
                ->values();

            $allReportsScoped = $this->applyInspectionScope(
                DB::table('inspecciones as i')
                    ->join('elementos as e', 'e.id', '=', 'i.elemento_id')
                    ->join('yacimientos as y', 'y.id', '=', 'e.yacimiento_id')
                    ->join('usuarios as u', 'u.id', '=', 'i.tecnico_id'),
                $scope
            );

            $total = (clone $allReportsScoped)->count('i.id');
            $pending = (clone $allReportsScoped)->whereIn('i.estado', ['borrador', 'enviada'])->count('i.id');
            $reviewed = (clone $allReportsScoped)->whereIn('i.estado', ['revisada', 'cerrada'])->count('i.id');

            $critical = $this->applyInspectionScope(
                DB::table('inspecciones as i')
                    ->join('novedades as n', 'n.inspeccion_id', '=', 'i.id')
                    ->join('criticidades as c', 'c.id', '=', 'n.criticidad_id')
                    ->join('elementos as e', 'e.id', '=', 'i.elemento_id')
                    ->join('yacimientos as y', 'y.id', '=', 'e.yacimiento_id')
                    ->join('usuarios as u', 'u.id', '=', 'i.tecnico_id'),
                $scope
            )->where('c.nivel', '>=', 3)->distinct('i.id')->count('i.id');

            $monthStart = Carbon::now()->startOfMonth();
            $monthEnd = Carbon::now()->endOfMonth();
            $myMonth = (clone $allReportsScoped)
                ->where('i.tecnico_id', $scope['user_id'])
                ->where('i.fecha_inspeccion', '>=', $monthStart)
                ->count('i.id');
            $monthReports = (clone $allReportsScoped)
                ->whereBetween('i.fecha_inspeccion', [$monthStart, $monthEnd])
                ->count('i.id');

            $withFiles = $this->applyInspectionScope(
                DB::table('inspecciones as i')
                    ->join('elementos as e', 'e.id', '=', 'i.elemento_id')
                    ->join('yacimientos as y', 'y.id', '=', 'e.yacimiento_id')
                    ->join('usuarios as u', 'u.id', '=', 'i.tecnico_id')
                    ->join('archivos as a', 'a.inspeccion_id', '=', 'i.id'),
                $scope
            )->distinct('i.id')->count('i.id');

            $lastReport = (clone $allReportsScoped)
                ->orderByDesc('i.fecha_inspeccion')
                ->value('i.fecha_inspeccion');

            $recentSubstations = (clone $allReportsScoped)
                ->selectRaw('e.nombre, MAX(i.fecha_inspeccion) as ultima_fecha')
                ->groupBy('e.nombre')
                ->orderByDesc('ultima_fecha')
                ->limit(5)
                ->get()
                ->map(fn ($row) => [
                    'nombre' => $row->nombre,
                    'ultima_fecha' => $row->ultima_fecha,
                ])
                ->values();

            $activeContractors = (clone $allReportsScoped)
                ->distinct('u.empresa_id')
                ->count('u.empresa_id');
            $thermographedElements = (clone $allReportsScoped)
                ->distinct('e.id')
                ->count('e.id');

            $criticalRecent = $reports
                ->filter(fn ($r) => in_array($r['criticidad'], ['Alta', 'Crítica'], true))
                ->take(5)
                ->values();

            $topTechnicians = [];
            if ($scope['is_admin'] || $scope['is_supervisor']) {
                $topTechnicians = (clone $allReportsScoped)
                    ->selectRaw('u.id, u.nombre, u.apellido, COUNT(i.id) as total')
                    ->groupBy('u.id', 'u.nombre', 'u.apellido')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->get()
                    ->map(fn ($row) => [
                        'id' => (int) $row->id,
                        'nombre' => trim($row->nombre . ' ' . $row->apellido),
                        'total' => (int) $row->total,
                    ])
                    ->values();
            }

            $stats = [
                'total_informes' => $total,
                'pendientes' => $pending,
                'revisados' => $reviewed,
                'fallas_criticas' => $critical,
                'tecnicos_activos' => (clone $allReportsScoped)->distinct('i.tecnico_id')->count('i.tecnico_id'),
                'empresas' => $scope['is_admin']
                    ? DB::table('empresas')->where('activo', true)->count()
                    : 1,
                'yacimientos' => $scope['is_admin']
                    ? DB::table('yacimientos')->where('activo', true)->count()
                    : (clone $allReportsScoped)->distinct('y.id')->count('y.id'),
                'informes_mes' => $myMonth,
                'informes_mes_global' => $monthReports,
                'mis_observados' => (clone $allReportsScoped)
                    ->where('i.tecnico_id', $scope['user_id'])
                    ->whereNotNull('i.observaciones_revisor')
                    ->count('i.id'),
                'mis_aprobados' => (clone $allReportsScoped)
                    ->where('i.tecnico_id', $scope['user_id'])
                    ->whereIn('i.estado', ['revisada', 'cerrada'])
                    ->count('i.id'),
                'informes_con_archivos' => $withFiles,
                'ultimo_informe' => $lastReport,
                'contratistas_activas' => $activeContractors,
                'elementos_termografiados' => $thermographedElements,
            ];

            return [
                'role' => $role,
                'scope' => [
                    'empresa_id' => $scope['empresa_id'],
                    'empresa_nombre' => $scope['empresa_nombre'],
                    'user_id' => $scope['user_id'],
                    'user_name' => $scope['user_name'],
                    'is_owner_supervisor' => $scope['is_owner_supervisor'] ?? false,
                    'assigned_yacimiento_names' => $scope['assigned_yacimiento_names'],
                ],
                'stats' => $stats,
                'top_tecnicos' => $topTechnicians,
                'subestaciones_recientes' => $recentSubstations,
                'informes_criticos_recientes' => $criticalRecent,
                'reports' => $reports,
            ];
        });

        return response()->json($cachedData);
    }

    private function applyInspectionScope($query, array $scope)
    {
        if ($scope['is_admin']) {
            return $query;
        }

        if ($scope['is_tecnico']) {
            $query->where('i.tecnico_id', $scope['user_id']);
            if ($scope['assigned_yacimiento_ids'] !== []) {
                $query->whereIn('e.yacimiento_id', $scope['assigned_yacimiento_ids']);
            } else {
                $query->whereRaw('1 = 0');
            }
            return $query;
        }

        if ($scope['is_supervisor']) {
            if (($scope['is_owner_supervisor'] ?? false) && $scope['assigned_yacimiento_ids'] !== []) {
                return $query->whereIn('e.yacimiento_id', $scope['assigned_yacimiento_ids']);
            }

            if ($scope['empresa_id']) {
                $query->where('u.empresa_id', $scope['empresa_id']);
                if ($scope['assigned_yacimiento_ids'] !== []) {
                    $query->whereIn('e.yacimiento_id', $scope['assigned_yacimiento_ids']);
                } else {
                    $query->whereRaw('1 = 0');
                }
                return $query;
            }
        }

        return $query->whereRaw('1 = 0');
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('estado')) {
            $query->where('i.estado', $request->string('estado')->value());
        }
        if ($request->filled('tecnico_id')) {
            $query->where('i.tecnico_id', (int) $request->string('tecnico_id')->value());
        }
        if ($request->filled('empresa_id')) {
            $query->where('u.empresa_id', (int) $request->string('empresa_id')->value());
        }
        if ($request->filled('yacimiento_id')) {
            $query->where('e.yacimiento_id', (int) $request->string('yacimiento_id')->value());
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('i.fecha_inspeccion', '>=', $request->string('fecha_desde')->value());
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('i.fecha_inspeccion', '<=', $request->string('fecha_hasta')->value());
        }
    }
}
