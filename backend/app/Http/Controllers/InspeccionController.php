<?php

namespace App\Http\Controllers;

use App\Models\Archivo;
use App\Models\Elemento;
use App\Models\Inspeccion;
use App\Models\Novedad;
use App\Services\AuditTrail;
use App\Services\Auth\AccessScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InspeccionController extends Controller
{
    public function __construct(
        private readonly AccessScopeResolver $scopeResolver,
        private readonly AuditTrail $auditTrail,
    ) {}

    private function archivoPayload(Archivo $archivo): array
    {
        return [
            'id' => $archivo->id,
            'tipo' => $archivo->tipo,
            'nombre' => $archivo->nombre_original,
            'download_url' => "/archivos/{$archivo->id}/download",
            'tamano' => $archivo->tamano_bytes,
            'mime' => $archivo->mime_type,
        ];
    }

    private function safeStorageFileName(string $originalName): string
    {
        $name = basename($originalName);
        $name = Str::ascii($name);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? '';
        $name = trim($name, '.-_');

        return $name !== '' ? $name : 'archivo';
    }

    private function storeInspectionFile(
        Inspeccion $inspeccion,
        UploadedFile $file,
        string $folder,
        string $tipo,
        int $userId
    ): void {
        $archivo = Archivo::create([
            'inspeccion_id' => $inspeccion->id,
            'tipo' => $tipo,
            'nombre_original' => $file->getClientOriginalName(),
            's3_bucket' => config('filesystems.disks.s3.bucket'),
            's3_key' => 'pending/' . (string) Str::uuid(),
            'tamano_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'subido_por' => $userId,
        ]);

        $filename = $archivo->id . '-' . $this->safeStorageFileName($file->getClientOriginalName());
        $directory = "inspecciones/{$inspeccion->id}/{$folder}";
        $path = $file->storeAs($directory, $filename, 's3');

        if (! $path) {
            throw new \RuntimeException('No se pudo guardar el archivo en MinIO');
        }

        $archivo->update(['s3_key' => $path]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);

        $query = Inspeccion::query()
            ->with([
            'tecnico:id,nombre,apellido,email',
                'revisor:id,nombre,apellido,email',
                'cerrador:id,nombre,apellido,email',
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
            if (($scope['is_owner_supervisor'] ?? false) && $scope['assigned_yacimiento_ids'] !== []) {
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
            'resumen' => $inspeccion->resumen,
            'observaciones_revisor' => $inspeccion->observaciones_revisor,
            'revisada_por' => $inspeccion->revisor ? [
                'id' => $inspeccion->revisor->id,
                'nombre' => trim($inspeccion->revisor->nombre . ' ' . $inspeccion->revisor->apellido),
                'email' => $inspeccion->revisor->email,
            ] : null,
            'fecha_revision' => $inspeccion->fecha_revision,
            'cerrada_por' => $inspeccion->cerrador ? [
                'id' => $inspeccion->cerrador->id,
                'nombre' => trim($inspeccion->cerrador->nombre . ' ' . $inspeccion->cerrador->apellido),
                'email' => $inspeccion->cerrador->email,
            ] : null,
            'fecha_cierre' => $inspeccion->fecha_cierre,
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
            'archivos' => $inspeccion->archivos->map(fn ($archivo) => $this->archivoPayload($archivo))->values(),
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

        // Solo admin o supervisor owner pueden revisar/cerrar.
        if (! $scope['is_admin'] && ! ($scope['is_owner_supervisor'] ?? false)) {
            Log::notice('authz.denied.inspeccion.estado', [
                'user_id' => $scope['user_id'] ?? null,
                'inspeccion_id' => $id,
                'roles' => $scope['roles'] ?? [],
            ]);
            return response()->json(['message' => 'No tenes permisos para revisar o cerrar informes'], 403);
        }

        $data = $request->validate([
            'estado' => 'required|in:' . implode(',', Inspeccion::getEstados()),
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
            if (($scope['is_owner_supervisor'] ?? false) && $scope['assigned_yacimiento_ids'] !== []) {
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
        $inspeccion->updated_by = $scope['user_id'];

        if ($data['estado'] === Inspeccion::ESTADO_REVISADA) {
            $inspeccion->revisada_por = $scope['user_id'];
            $inspeccion->fecha_revision = now();
            $inspeccion->cerrada_por = null;
            $inspeccion->fecha_cierre = null;
        }

        if ($data['estado'] === Inspeccion::ESTADO_CERRADA) {
            // Si se cierra directamente sin paso previo por "revisada", dejamos trazabilidad mínima.
            if (! $inspeccion->revisada_por) {
                $inspeccion->revisada_por = $scope['user_id'];
                $inspeccion->fecha_revision = now();
            }
            $inspeccion->cerrada_por = $scope['user_id'];
            $inspeccion->fecha_cierre = now();
        }

        $inspeccion->save();

        $this->auditTrail->record('inspeccion.estado.updated', [
            'actor_user_id' => $scope['user_id'] ?? null,
            'inspeccion_id' => $inspeccion->id,
            'estado' => $inspeccion->estado,
            'revisada_por' => $inspeccion->revisada_por,
            'cerrada_por' => $inspeccion->cerrada_por,
        ]);

        if ($data['estado'] === Inspeccion::ESTADO_CERRADA) {
            Novedad::query()
                ->where('inspeccion_id', $inspeccion->id)
                ->where('estado', Novedad::ESTADO_ABIERTA)
                ->update(['estado' => Novedad::ESTADO_RESUELTA]);

            $this->auditTrail->record('novedades.bulk_resolved_on_close', [
                'actor_user_id' => $scope['user_id'] ?? null,
                'inspeccion_id' => $inspeccion->id,
            ]);
        }

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
        if (! $userId) {
            return response()->json(['message' => 'Usuario autenticado no resuelto'], 401);
        }

        $data = $request->validate([
            'elemento_id' => 'required|exists:elementos,id',
            'fecha_inspeccion' => 'required|date',
            'cuadrilla' => 'nullable|string|max:100',
            'integrantes' => 'nullable|string',
            'empresa_contratista' => 'nullable|string|max:150',
            'condiciones_clima' => 'nullable|string|max:50',
            'resumen' => 'nullable|string',
            'estado' => 'nullable|in:' . Inspeccion::ESTADO_ENVIADA, // Solo estado inicial al crear: revisar/cerrar va por PATCH /estado con control de rol
            'reporte' => 'nullable|file|mimes:doc,docx,xls,xlsx|max:10240', // Max 10MB, Word/Excel only
            'imagenes' => 'nullable|file|mimes:zip|max:51200', // Max 50MB, ZIP only
            'novedades' => 'nullable|string', // JSON string containing array of findings
        ]);

        if (! $this->scopeResolver->canCreateInspectionForElement($scope, (int) $data['elemento_id'])) {
            Log::notice('authz.denied.inspeccion.create', [
                'user_id' => $scope['user_id'] ?? null,
                'target_elemento_id' => (int) $data['elemento_id'],
                'roles' => $scope['roles'] ?? [],
            ]);
            return response()->json(['message' => 'Elemento fuera de alcance para tu perfil'], 422);
        }

        $elemento = Elemento::query()->find($data['elemento_id']);

        return DB::transaction(function () use ($request, $data, $userId, $elemento) {
            $empresaSnapshot = trim((string) ($data['empresa_contratista'] ?? ''));
            if ($empresaSnapshot === '') {
                $empresaSnapshot = (string) (DB::table('usuarios as u')
                     ->join('empresas as e', 'e.id', '=', 'u.empresa_id')
                     ->where('u.id', (int) $userId)
                     ->value('e.nombre') ?? '');
            }
            if ($empresaSnapshot === '') {
                $empresaSnapshot = null;
            }

            // 1. Create Inspeccion
            $inspeccion = Inspeccion::create([
                'elemento_id' => $elemento->id,
                'tecnico_id' => $userId,
                'fecha_inspeccion' => $data['fecha_inspeccion'],
                'cuadrilla' => $data['cuadrilla'] ?? null,
                'integrantes' => $data['integrantes'] ?? null,
                'empresa_contratista' => $empresaSnapshot,
                'condiciones_clima' => $data['condiciones_clima'] ?? null,
                'resumen' => $data['resumen'] ?? null,
                'estado' => $data['estado'] ?? Inspeccion::ESTADO_ENVIADA,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->auditTrail->record('inspeccion.created', [
                'actor_user_id' => $userId,
                'inspeccion_id' => $inspeccion->id,
                'elemento_id' => $elemento->id,
            ]);

            // 2. Handle Reporte File
            if ($request->hasFile('reporte')) {
                $reportFile = $request->file('reporte');
                $extension = strtolower($reportFile->getClientOriginalExtension());
                $tipo = ($extension === 'xls' || $extension === 'xlsx') ? 'informe_excel' : 'informe_word';

                $this->storeInspectionFile($inspeccion, $reportFile, 'reports', $tipo, $userId);
            }

            // 3. Handle Imagenes ZIP File
            if ($request->hasFile('imagenes')) {
                $imgFile = $request->file('imagenes');
                $this->storeInspectionFile($inspeccion, $imgFile, 'images', 'pack_imagenes_zip', $userId);
            }

            // 4. Handle Findings (Novedades)
            if (!empty($data['novedades'])) {
                $findings = json_decode($data['novedades'], true);
                if (is_array($findings)) {
                    foreach ($findings as $finding) {
                        $novedad = Novedad::create([
                            'inspeccion_id' => $inspeccion->id,
                            'criticidad_id' => !empty($finding['criticidad_id']) ? (int) $finding['criticidad_id'] : null,
                            'titulo' => $finding['titulo'] ?? 'Hallazgo sin título',
                            'descripcion' => $finding['descripcion'] ?? null,
                            'ubicacion_dentro_elemento' => $finding['ubicacion_dentro_elemento'] ?? null,
                            'temperatura_detectada' => isset($finding['temperatura_detectada']) && $finding['temperatura_detectada'] !== '' ? (float) $finding['temperatura_detectada'] : null,
                            'accion_recomendada' => $finding['accion_recomendada'] ?? null,
                            'estado' => Novedad::ESTADO_ABIERTA,
                        ]);
                        $this->auditTrail->record('novedad.created', [
                            'actor_user_id' => $userId,
                            'inspeccion_id' => $inspeccion->id,
                            'novedad_id' => $novedad->id,
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
