<?php

namespace App\Http\Controllers;

use App\Models\Archivo;
use App\Models\Elemento;
use App\Models\Inspeccion;
use App\Models\Novedad;
use App\Services\Auth\AccessScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InspeccionController extends Controller
{
    public function __construct(private readonly AccessScopeResolver $scopeResolver) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);

        $query = Inspeccion::query()
            ->with([
                'tecnico:id,nombre,apellido,email',
                'elemento:id,nombre,codigo,yacimiento_id,tipo_elemento_id',
                'elemento.yacimiento:id,nombre,codigo,empresa_id',
                'elemento.tipoElemento:id,nombre',
                'archivos:id,inspeccion_id,tipo,nombre_original,s3_bucket,s3_key,tamano_bytes,mime_type',
                'novedades:id,inspeccion_id,criticidad_id,titulo,descripcion,ubicacion_dentro_elemento,temperatura_detectada,accion_recomendada,estado',
                'novedades.criticidad:id,nombre,nivel,color',
            ])
            ->join('elementos as e', 'e.id', '=', 'inspecciones.elemento_id')
            ->join('yacimientos as y', 'y.id', '=', 'e.yacimiento_id')
            ->join('usuarios as u', 'u.id', '=', 'inspecciones.tecnico_id')
            ->select('inspecciones.*');

        if ($scope['is_admin']) {
            // no extra filter
        } elseif ($scope['is_tecnico']) {
            $query->where('inspecciones.tecnico_id', $scope['user_id']);
            if ($scope['assigned_yacimiento_ids'] !== []) {
                $query->whereIn('e.yacimiento_id', $scope['assigned_yacimiento_ids']);
            } else {
                $query->whereRaw('1=0');
            }
        } elseif ($scope['is_supervisor']) {
            if ($scope['is_pae_supervisor'] && $scope['assigned_yacimiento_ids'] !== []) {
                $query->whereIn('e.yacimiento_id', $scope['assigned_yacimiento_ids']);
            } else {
                $query->where('u.empresa_id', $scope['empresa_id']);
                if ($scope['assigned_yacimiento_ids'] !== []) {
                    $query->whereIn('e.yacimiento_id', $scope['assigned_yacimiento_ids']);
                } else {
                    $query->whereRaw('1=0');
                }
            }
        } else {
            $query->whereRaw('1=0');
        }

        $inspeccion = $query->find($id);
        if (! $inspeccion) {
            return response()->json(['message' => 'Inspección no encontrada'], 404);
        }

        return response()->json([
            'id' => $inspeccion->id,
            'fecha_inspeccion' => $inspeccion->fecha_inspeccion,
            'estado' => $inspeccion->estado,
            'cuadrilla' => $inspeccion->cuadrilla,
            'integrantes' => $inspeccion->integrantes,
            'empresa_contratista' => $inspeccion->empresa_contratista,
            'condiciones_clima' => $inspeccion->condiciones_clima,
            'temperatura_ambiente' => $inspeccion->temperatura_ambiente,
            'humedad_relativa' => $inspeccion->humedad_relativa,
            'carga_pct' => $inspeccion->carga_pct,
            'resumen' => $inspeccion->resumen,
            'observaciones_revisor' => $inspeccion->observaciones_revisor,
            'tecnico' => $inspeccion->tecnico ? [
                'id' => $inspeccion->tecnico->id,
                'nombre' => trim($inspeccion->tecnico->nombre . ' ' . $inspeccion->tecnico->apellido),
                'email' => $inspeccion->tecnico->email,
            ] : null,
            'elemento' => $inspeccion->elemento ? [
                'id' => $inspeccion->elemento->id,
                'nombre' => $inspeccion->elemento->nombre,
                'codigo' => $inspeccion->elemento->codigo,
                'tipo' => $inspeccion->elemento->tipoElemento?->nombre,
                'yacimiento' => $inspeccion->elemento->yacimiento?->nombre,
            ] : null,
            'archivos' => $inspeccion->archivos->map(fn ($archivo) => [
                'id' => $archivo->id,
                'tipo' => $archivo->tipo,
                'nombre' => $archivo->nombre_original,
                'bucket' => $archivo->s3_bucket,
                'key' => $archivo->s3_key,
                'tamano' => $archivo->tamano_bytes,
                'mime' => $archivo->mime_type,
            ])->values(),
            'novedades' => $inspeccion->novedades->map(fn ($n) => [
                'id' => $n->id,
                'titulo' => $n->titulo,
                'descripcion' => $n->descripcion,
                'ubicacion' => $n->ubicacion_dentro_elemento,
                'temperatura' => $n->temperatura_detectada,
                'criticidad' => $n->criticidad?->nombre,
                'criticidad_color' => $n->criticidad?->color,
                'estado' => $n->estado,
                'accion_recomendada' => $n->accion_recomendada,
            ])->values(),
        ]);
    }

    public function updateEstado(Request $request, int $id): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);

        // Solo admin o supervisor PAE pueden revisar/cerrar.
        if (! $scope['is_admin'] && ! $scope['is_pae_supervisor']) {
            return response()->json(['message' => 'No tenes permisos para revisar o cerrar informes'], 403);
        }

        $data = $request->validate([
            'estado' => 'required|in:enviada,revisada,cerrada',
            'observaciones_revisor' => 'nullable|string',
        ]);

        $query = Inspeccion::query()
            ->join('elementos as e', 'e.id', '=', 'inspecciones.elemento_id')
            ->join('yacimientos as y', 'y.id', '=', 'e.yacimiento_id')
            ->join('usuarios as u', 'u.id', '=', 'inspecciones.tecnico_id')
            ->select('inspecciones.*');

        if ($scope['is_admin']) {
            // full access
        } elseif ($scope['is_supervisor']) {
            if ($scope['is_pae_supervisor'] && $scope['assigned_yacimiento_ids'] !== []) {
                $query->whereIn('e.yacimiento_id', $scope['assigned_yacimiento_ids']);
            } else {
                $query->where('u.empresa_id', $scope['empresa_id']);
                if ($scope['assigned_yacimiento_ids'] !== []) {
                    $query->whereIn('e.yacimiento_id', $scope['assigned_yacimiento_ids']);
                } else {
                    $query->whereRaw('1=0');
                }
            }
        }

        $inspeccion = $query->find($id);
        if (! $inspeccion) {
            return response()->json(['message' => 'Inspección no encontrada'], 404);
        }

        $inspeccion->estado = $data['estado'];
        if (array_key_exists('observaciones_revisor', $data)) {
            $inspeccion->observaciones_revisor = $data['observaciones_revisor'];
        }
        $inspeccion->revisada_por = $scope['user_id'];
        $inspeccion->fecha_revision = now();
        $inspeccion->save();

        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'id' => $inspeccion->id,
            'estado' => $inspeccion->estado,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);
        $userId = $scope['user_id'];

        $data = $request->validate([
            'elemento_id' => 'required|exists:elementos,id',
            'fecha_inspeccion' => 'required|date',
            'cuadrilla' => 'nullable|string|max:100',
            'integrantes' => 'nullable|string',
            'empresa_contratista' => 'nullable|string|max:150',
            'temperatura_ambiente' => 'nullable|numeric',
            'humedad_relativa' => 'nullable|numeric',
            'carga_pct' => 'nullable|numeric',
            'condiciones_clima' => 'nullable|string|max:50',
            'resumen' => 'nullable|string',
            'estado' => 'nullable|string|max:20',
            'reporte' => 'nullable|file|max:15360', // Max 15MB
            'imagenes' => 'nullable|file|max:61440', // Max 60MB
            'novedades' => 'nullable|string', // JSON string containing array of findings
        ]);

        if (! $this->scopeResolver->canCreateInspectionForElement($scope, (int) $data['elemento_id'])) {
            return response()->json(['message' => 'No tenes permisos para cargar inspecciones en este elemento'], 403);
        }

        $elemento = Elemento::query()->find($data['elemento_id']);

        return DB::transaction(function () use ($request, $data, $userId, $elemento) {
            // 1. Create Inspeccion
            $inspeccion = Inspeccion::create([
                'elemento_id' => $elemento->id,
                'tecnico_id' => $userId ?? 1, // Fallback to 1 if no user_id (testing)
                'fecha_inspeccion' => $data['fecha_inspeccion'],
                'cuadrilla' => $data['cuadrilla'] ?? null,
                'integrantes' => $data['integrantes'] ?? null,
                'empresa_contratista' => $data['empresa_contratista'] ?? 'PECOM S.A.',
                'temperatura_ambiente' => $data['temperatura_ambiente'] ?? null,
                'humedad_relativa' => $data['humedad_relativa'] ?? null,
                'carga_pct' => $data['carga_pct'] ?? null,
                'condiciones_clima' => $data['condiciones_clima'] ?? null,
                'resumen' => $data['resumen'] ?? null,
                'estado' => $data['estado'] ?? 'enviada',
            ]);

            // 2. Handle Reporte File
            if ($request->hasFile('reporte')) {
                $reportFile = $request->file('reporte');
                $extension = strtolower($reportFile->getClientOriginalExtension());
                $tipo = ($extension === 'xls' || $extension === 'xlsx') ? 'informe_excel' : 'informe_word';
                
                $path = $reportFile->store('reports', 'public');
                
                Archivo::create([
                    'inspeccion_id' => $inspeccion->id,
                    'tipo' => $tipo,
                    'nombre_original' => $reportFile->getClientOriginalName(),
                    's3_bucket' => 'local',
                    's3_key' => $path,
                    'tamano_bytes' => $reportFile->getSize(),
                    'mime_type' => $reportFile->getMimeType(),
                    'subido_por' => $userId ?? 1,
                ]);
            }

            // 3. Handle Imagenes ZIP File
            if ($request->hasFile('imagenes')) {
                $imgFile = $request->file('imagenes');
                $path = $imgFile->store('images', 'public');

                Archivo::create([
                    'inspeccion_id' => $inspeccion->id,
                    'tipo' => 'pack_imagenes_zip',
                    'nombre_original' => $imgFile->getClientOriginalName(),
                    's3_bucket' => 'local',
                    's3_key' => $path,
                    'tamano_bytes' => $imgFile->getSize(),
                    'mime_type' => $imgFile->getMimeType(),
                    'subido_por' => $userId ?? 1,
                ]);
            }

            // 4. Handle Findings (Novedades)
            if (!empty($data['novedades'])) {
                $findings = json_decode($data['novedades'], true);
                if (is_array($findings)) {
                    foreach ($findings as $finding) {
                        Novedad::create([
                            'inspeccion_id' => $inspeccion->id,
                            'criticidad_id' => !empty($finding['criticidad_id']) ? (int) $finding['criticidad_id'] : null,
                            'titulo' => $finding['titulo'] ?? 'Hallazgo sin título',
                            'descripcion' => $finding['descripcion'] ?? null,
                            'ubicacion_dentro_elemento' => $finding['ubicacion_dentro_elemento'] ?? null,
                            'temperatura_detectada' => isset($finding['temperatura_detectada']) && $finding['temperatura_detectada'] !== '' ? (float) $finding['temperatura_detectada'] : null,
                            'accion_recomendada' => $finding['accion_recomendada'] ?? null,
                            'estado' => 'abierta',
                        ]);
                    }
                }
            }

            return response()->json([
                'message' => 'Inspección registrada correctamente',
                'inspeccion' => $inspeccion->load(['archivos', 'novedades'])
            ], 201);
        });
    }
}
