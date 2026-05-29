<?php

namespace App\Http\Controllers;

use App\Models\Elemento;
use App\Services\AuditTrail;
use App\Services\Auth\AccessScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ElementoController extends Controller
{
    public function __construct(
        private readonly AccessScopeResolver $scopeResolver,
        private readonly AuditTrail $auditTrail,
    ) {}

    private function archivoPayload($archivo): array
    {
        return [
            'id' => $archivo->id,
            'tipo' => $archivo->tipo,
            'nombre' => $archivo->nombre_original,
            'download_url' => "/archivos/{$archivo->id}/download",
            'tamano' => $archivo->tamano_bytes,
        ];
    }

    public function listElements(Request $request): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);

        $query = Elemento::query()
            ->with([
                'tipoElemento:id,nombre,codigo',
                'criticidad:id,nombre,nivel,color',
                'yacimiento:id,empresa_id,nombre',
                'nivelTension:id,kv,etiqueta',
            ]);

        $query = $this->scopeResolver->applyElementScope($query, $scope);

        if ($request->boolean('my_inspections_only', false) && $scope['user_id']) {
            $query->whereHas('inspecciones', function ($q) use ($scope): void {
                $q->where('tecnico_id', $scope['user_id']);
            });
        }

        $elementos = $query->orderBy('nombre')
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
                'criticidad' => $elemento->criticidad?->nombre,
                'criticidad_nivel' => $elemento->criticidad?->nivel,
                'criticidad_color' => $elemento->criticidad?->color,
                'nivel_tension_id' => $elemento->nivel_tension_id,
                'tension' => $elemento->nivelTension?->etiqueta,
            ]);

        return response()->json($elementos);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);

        $elementoQuery = Elemento::query()
            ->with([
                'tipoElemento:id,nombre,codigo',
                'criticidad:id,nombre,nivel,color',
                'yacimiento:id,empresa_id,nombre',
                'nivelTension:id,kv,etiqueta',
            ]);

        $elemento = $this->scopeResolver->applyElementScope($elementoQuery, $scope)->find($id);

        if (! $elemento) {
            return response()->json(['message' => 'Elemento no encontrado'], 404);
        }

        $inspecciones = $elemento->inspecciones()
            ->with([
                'tecnico:id,nombre,apellido',
                'archivos:id,inspeccion_id,tipo,nombre_original,s3_bucket,s3_key,tamano_bytes,mime_type',
                'novedades' => fn ($query) => $query->with('criticidad:id,nombre,nivel,color'),
            ])
            ->orderBy('fecha_inspeccion', 'desc')
            ->get()
            ->map(fn ($inspeccion) => [
                'id' => $inspeccion->id,
                'fecha_inspeccion' => $inspeccion->fecha_inspeccion,
                'cuadrilla' => $inspeccion->cuadrilla,
                'integrantes' => $inspeccion->integrantes,
                'empresa_contratista' => $inspeccion->empresa_contratista,
                'condiciones_clima' => $inspeccion->condiciones_clima,
                'resumen' => $inspeccion->resumen,
                'estado' => $inspeccion->estado,
                'tecnico' => $inspeccion->tecnico ? $inspeccion->tecnico->nombre . ' ' . $inspeccion->tecnico->apellido : null,
                'archivos' => $inspeccion->archivos->map(fn ($archivo) => $this->archivoPayload($archivo)),
                'novedades' => $inspeccion->novedades->map(fn ($novedad) => [
                    'id' => $novedad->id,
                    'titulo' => $novedad->titulo,
                    'descripcion' => $novedad->descripcion,
                    'ubicacion' => $novedad->ubicacion_dentro_elemento,
                    'temperatura' => $novedad->temperatura_detectada,
                    'accion_recomendada' => $novedad->accion_recomendada,
                    'criticidad' => $novedad->criticidad?->nombre,
                    'criticidad_color' => $novedad->criticidad?->color,
                    'estado' => $novedad->estado,
                ]),
            ]);

        return response()->json([
            'elemento' => [
                'id' => $elemento->id,
                'nombre' => $elemento->nombre,
                'codigo' => $elemento->codigo,
                'tipo_elemento_id' => $elemento->tipo_elemento_id,
                'tipo' => $elemento->tipoElemento?->nombre,
                'funcion' => $elemento->funcion,
                'nivel_tension_id' => $elemento->nivel_tension_id,
                'tension' => $elemento->nivelTension?->kv ? $elemento->nivelTension->kv . ' kV' : null,
                'yacimiento_id' => $elemento->yacimiento_id,
                'yacimiento' => $elemento->yacimiento?->nombre,
                'marca' => $elemento->marca,
                'modelo' => $elemento->modelo,
                'n_serie' => $elemento->n_serie,
                'criticidad_id' => $elemento->criticidad_id,
                'criticidad' => $elemento->criticidad?->nombre,
                'criticidad_color' => $elemento->criticidad?->color,
                'estado_operativo' => $elemento->estado_operativo,
                'observaciones' => $elemento->observaciones_generales,
            ],
            'inspecciones' => $inspecciones,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);
        $actorId = $scope['user_id'];

        $data = $request->validate([
            'yacimiento_id' => 'required|exists:yacimientos,id',
            'tipo_elemento_id' => 'required|exists:tipos_elemento,id',
            'funcion' => 'nullable|string|max:20',
            'nivel_tension_id' => 'nullable|exists:niveles_tension,id',
            'nombre' => 'required|string|max:150',
            'codigo' => 'required|string|max:50',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'n_serie' => 'nullable|string|max:100',
            'criticidad_id' => 'nullable|exists:criticidades,id',
            'estado_operativo' => 'nullable|string|max:30',
            'observaciones_generales' => 'nullable|string',
            'activo' => 'nullable|boolean',
        ]);

        if (! $this->scopeResolver->canMutateElement($scope, (int) $data['yacimiento_id'])) {
            Log::notice('authz.denied.element.create', [
                'user_id' => $scope['user_id'] ?? null,
                'target_yacimiento_id' => (int) $data['yacimiento_id'],
                'roles' => $scope['roles'] ?? [],
            ]);
            return response()->json(['message' => 'No tenes permisos para crear elementos en este yacimiento'], 403);
        }

        $elemento = Elemento::create([
            ...$data,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->auditTrail->record('elemento.created', [
            'actor_user_id' => $actorId,
            'elemento_id' => $elemento->id,
            'yacimiento_id' => $elemento->yacimiento_id,
        ]);

        return response()->json($elemento, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);
        $actorId = $scope['user_id'];

        $elemento = $this->scopeResolver->applyElementScope(Elemento::query(), $scope)->find($id);
        if (! $elemento) {
            return response()->json(['message' => 'Elemento no encontrado'], 404);
        }

        $data = $request->validate([
            'yacimiento_id' => 'required|exists:yacimientos,id',
            'tipo_elemento_id' => 'required|exists:tipos_elemento,id',
            'funcion' => 'nullable|string|max:20',
            'nivel_tension_id' => 'nullable|exists:niveles_tension,id',
            'nombre' => 'required|string|max:150',
            'codigo' => 'required|string|max:50',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'n_serie' => 'nullable|string|max:100',
            'criticidad_id' => 'nullable|exists:criticidades,id',
            'estado_operativo' => 'nullable|string|max:30',
            'observaciones_generales' => 'nullable|string',
            'activo' => 'nullable|boolean',
        ]);

        if (! $this->scopeResolver->canMutateElement($scope, (int) $data['yacimiento_id'])) {
            Log::notice('authz.denied.element.update', [
                'user_id' => $scope['user_id'] ?? null,
                'elemento_id' => (int) $id,
                'target_yacimiento_id' => (int) $data['yacimiento_id'],
                'roles' => $scope['roles'] ?? [],
            ]);
            return response()->json(['message' => 'No tenes permisos para editar elementos en este yacimiento'], 403);
        }

        $elemento->update([
            ...$data,
            'updated_by' => $actorId,
        ]);

        $this->auditTrail->record('elemento.updated', [
            'actor_user_id' => $actorId,
            'elemento_id' => $elemento->id,
            'yacimiento_id' => $elemento->yacimiento_id,
        ]);

        return response()->json($elemento);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);

        $elemento = $this->scopeResolver->applyElementScope(Elemento::query(), $scope)->find($id);
        if (! $elemento) {
            return response()->json(['message' => 'Elemento no encontrado'], 404);
        }

        if (! $this->scopeResolver->canMutateElement($scope, (int) $elemento->yacimiento_id)) {
            return response()->json(['message' => 'No tenes permisos para eliminar elementos en este yacimiento'], 403);
        }

        // Prevent hard-delete if element has inspection history (audit trail protection)
        $inspeccionCount = $elemento->inspecciones()->count();
        if ($inspeccionCount > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un elemento que tiene inspecciones cargadas. Elimina o archiva las inspecciones primero.',
                'inspecciones_count' => $inspeccionCount,
            ], 422);
        }

        $elemento->delete();

        $this->auditTrail->record('elemento.deleted', [
            'actor_user_id' => $scope['user_id'] ?? null,
            'elemento_id' => (int) $id,
            'yacimiento_id' => $elemento->yacimiento_id,
        ]);

        return response()->json(['message' => 'Elemento eliminado correctamente']);
    }
}
