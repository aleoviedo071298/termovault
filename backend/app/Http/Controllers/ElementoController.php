<?php

namespace App\Http\Controllers;

use App\Models\Elemento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ElementoController extends Controller
{
    public function listElements(Request $request): JsonResponse
    {
        $empresaId = $request->header('X-Empresa-Id', $request->query('empresa_id'));
        $userId = $request->attributes->get('auth.user_id');

        $query = Elemento::query()
            ->with([
                'tipoElemento:id,nombre,codigo', 
                'criticidad:id,nombre,nivel,color', 
                'yacimiento:id,empresa_id,nombre',
                'nivelTension:id,kv,etiqueta'
            ])
            ->forEmpresa($empresaId);

        if ($request->boolean('my_inspections_only', false) && $userId) {
            $query->whereHas('inspecciones', function ($q) use ($userId) {
                $q->where('tecnico_id', $userId);
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
        $empresaId = $request->attributes->get('auth.empresa_id');

        $elemento = Elemento::query()
            ->with([
                'tipoElemento:id,nombre,codigo',
                'criticidad:id,nombre,nivel,color',
                'yacimiento:id,empresa_id,nombre',
                'nivelTension:id,kv,etiqueta'
            ])
            ->forEmpresa($empresaId)
            ->find($id);

        if (! $elemento) {
            return response()->json(['message' => 'Elemento no encontrado'], 404);
        }

        $inspecciones = $elemento->inspecciones()
            ->with([
                'tecnico:id,nombre,apellido',
                'archivos:id,inspeccion_id,tipo,nombre_original,s3_bucket,s3_key,tamano_bytes,mime_type',
                'novedades' => fn ($query) => $query->with('criticidad:id,nombre,nivel,color')
            ])
            ->orderBy('fecha_inspeccion', 'desc')
            ->get()
            ->map(fn ($inspeccion) => [
                'id' => $inspeccion->id,
                'fecha_inspeccion' => $inspeccion->fecha_inspeccion,
                'cuadrilla' => $inspeccion->cuadrilla,
                'integrantes' => $inspeccion->integrantes,
                'empresa_contratista' => $inspeccion->empresa_contratista,
                'temperatura_ambiente' => $inspeccion->temperatura_ambiente,
                'humedad_relativa' => $inspeccion->humedad_relativa,
                'carga_pct' => $inspeccion->carga_pct,
                'condiciones_clima' => $inspeccion->condiciones_clima,
                'resumen' => $inspeccion->resumen,
                'estado' => $inspeccion->estado,
                'tecnico' => $inspeccion->tecnico ? $inspeccion->tecnico->nombre . ' ' . $inspeccion->tecnico->apellido : null,
                'archivos' => $inspeccion->archivos->map(fn ($archivo) => [
                    'id' => $archivo->id,
                    'tipo' => $archivo->tipo,
                    'nombre' => $archivo->nombre_original,
                    'bucket' => $archivo->s3_bucket,
                    'key' => $archivo->s3_key,
                    'tamano' => $archivo->tamano_bytes,
                ]),
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
                ])
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
            'inspecciones' => $inspecciones
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('auth.empresa_id');

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

        if ($empresaId) {
            $yacimientoExists = DB::table('yacimientos')
                ->where('id', $data['yacimiento_id'])
                ->where('empresa_id', $empresaId)
                ->exists();
            if (! $yacimientoExists) {
                return response()->json(['message' => 'El yacimiento seleccionado no es válido para tu empresa'], 422);
            }
        }

        $elemento = Elemento::create($data);

        return response()->json($elemento, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('auth.empresa_id');

        $elemento = Elemento::query()->forEmpresa($empresaId)->find($id);
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

        if ($empresaId) {
            $yacimientoExists = DB::table('yacimientos')
                ->where('id', $data['yacimiento_id'])
                ->where('empresa_id', $empresaId)
                ->exists();
            if (! $yacimientoExists) {
                return response()->json(['message' => 'El yacimiento seleccionado no es válido para tu empresa'], 422);
            }
        }

        $elemento->update($data);

        return response()->json($elemento);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $empresaId = $request->attributes->get('auth.empresa_id');

        $elemento = Elemento::query()->forEmpresa($empresaId)->find($id);
        if (! $elemento) {
            return response()->json(['message' => 'Elemento no encontrado'], 404);
        }

        DB::transaction(function () use ($elemento) {
            // Delete inspections first (cascades to files, findings, comments)
            $elemento->inspecciones()->delete();
            // Delete the element itself
            $elemento->delete();
        });

        return response()->json(['message' => 'Elemento eliminado correctamente']);
    }
}
