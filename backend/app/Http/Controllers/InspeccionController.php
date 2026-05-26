<?php

namespace App\Http\Controllers;

use App\Models\Archivo;
use App\Models\Elemento;
use App\Models\Inspeccion;
use App\Models\Novedad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InspeccionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $empresaId = $request->attributes->get('auth.empresa_id');
        $userId = $request->attributes->get('auth.user_id');

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

        // Enforce multi-tenant check
        $elemento = Elemento::query()->forEmpresa($empresaId)->find($data['elemento_id']);
        if (!$elemento) {
            return response()->json(['message' => 'Elemento no encontrado o no pertenece a tu empresa'], 422);
        }

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
                'estado' => $data['estado'] ?? 'revisada', // Defaults to reviewed/published
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
