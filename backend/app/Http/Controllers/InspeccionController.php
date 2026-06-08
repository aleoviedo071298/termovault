<?php

namespace App\Http\Controllers;

use App\Models\Archivo;
use App\Models\Elemento;
use App\Models\Inspeccion;
use App\Models\Novedad;
use App\Services\AuditTrail;
use App\Services\Auth\AccessScopeResolver;
use App\Services\FileSignatureGuard;
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
        private readonly FileSignatureGuard $fileSignatureGuard,
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

    /**
     * Transiciones de estado válidas (state machine).
     * Cada key es el estado actual, el value es la lista de estados destino permitidos.
     */
    private const VALID_TRANSITIONS = [
        Inspeccion::ESTADO_ENVIADA  => [Inspeccion::ESTADO_REVISADA],
        Inspeccion::ESTADO_REVISADA => [Inspeccion::ESTADO_CERRADA],
        Inspeccion::ESTADO_CERRADA  => [], // estado terminal
    ];

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

        $newEstado = $data['estado'];
        $currentEstado = $inspeccion->estado;

        // ── FIX [013-A]: Validar transición de estado (state machine) ──
        $allowed = self::VALID_TRANSITIONS[$currentEstado] ?? [];
        if (! in_array($newEstado, $allowed, true)) {
            Log::notice('authz.denied.inspeccion.estado.transition', [
                'user_id' => $scope['user_id'] ?? null,
                'inspeccion_id' => $id,
                'from' => $currentEstado,
                'to' => $newEstado,
            ]);
            return response()->json([
                'message' => "Transición de estado inválida: {$currentEstado} → {$newEstado}",
            ], 422);
        }

        // ── FIX [013-B]: Segregación de funciones en cierre ──
        // Un supervisor (no admin) no puede cerrar una inspección que él mismo revisó.
        if ($newEstado === Inspeccion::ESTADO_CERRADA && ! $scope['is_admin']) {
            if ((int) $inspeccion->revisada_por === (int) $scope['user_id']) {
                Log::notice('authz.denied.inspeccion.estado.segregation', [
                    'user_id' => $scope['user_id'] ?? null,
                    'inspeccion_id' => $id,
                    'revisada_por' => $inspeccion->revisada_por,
                ]);
                return response()->json([
                    'message' => 'No puedes cerrar una inspección que vos mismo revisaste (segregación de funciones)',
                ], 403);
            }
        }

        // ── Aplicar cambio de estado ──
        $inspeccion->estado = $newEstado;
        if (array_key_exists('observaciones_revisor', $data)) {
            $inspeccion->observaciones_revisor = $data['observaciones_revisor'];
        }
        $inspeccion->updated_by = $scope['user_id'];

        if ($newEstado === Inspeccion::ESTADO_REVISADA) {
            $inspeccion->revisada_por = $scope['user_id'];
            $inspeccion->fecha_revision = now();
            $inspeccion->cerrada_por = null;
            $inspeccion->fecha_cierre = null;
        }

        if ($newEstado === Inspeccion::ESTADO_CERRADA) {
            $inspeccion->cerrada_por = $scope['user_id'];
            $inspeccion->fecha_cierre = now();
            // revisada_por ya fue asignado en la transición enviada → revisada
        }

        $inspeccion->save();

        $this->auditTrail->record('inspeccion.estado.updated', [
            'actor_user_id' => $scope['user_id'] ?? null,
            'inspeccion_id' => $inspeccion->id,
            'from_estado' => $currentEstado,
            'to_estado' => $newEstado,
            'revisada_por' => $inspeccion->revisada_por,
            'cerrada_por' => $inspeccion->cerrada_por,
        ]);

        if ($newEstado === Inspeccion::ESTADO_CERRADA) {
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
            'integrantes' => 'nullable|string',
            'empresa_contratista' => 'nullable|string|max:150',
            'condiciones_clima' => 'nullable|string|max:50',
            'resumen' => 'nullable|string',
            'estado' => 'nullable|in:' . Inspeccion::ESTADO_ENVIADA, // Solo estado inicial al crear: revisar/cerrar va por PATCH /estado con control de rol
            // Informe formal OPCIONAL: PDF / Word / Excel. Un solo archivo.
            'reporte' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10240', // Max 10MB
            // Termografías OBLIGATORIAS: al menos un .is2 o .zip. La extensión .is2 se valida
            // manualmente más abajo porque es un formato propietario sin MIME confiable
            // (la regla mimes: de Laravel no lo reconoce).
            'termografias' => 'required|array|min:1',
            'termografias.*' => 'file|max:61440', // Max 60MB por archivo térmico
            // Compatibilidad: campo legacy de un único ZIP (no usado por el frontend nuevo).
            'imagenes' => 'nullable|file|mimes:zip|max:51200',
            'novedades' => 'nullable|string', // JSON string containing array of findings
        ], [
            'termografias.required' => 'Debes adjuntar al menos un archivo térmico (.is2 o .zip).',
            'termografias.array' => 'Formato de archivos térmicos inválido.',
            'termografias.min' => 'Debes adjuntar al menos un archivo térmico (.is2 o .zip).',
        ]);

        // Validación manual de extensiones térmicas (.is2 no tiene MIME confiable).
        $allowedThermalExtensions = ['is2', 'zip'];
        foreach ((array) $request->file('termografias', []) as $thermalFile) {
            $ext = strtolower($thermalFile->getClientOriginalExtension());
            if (! in_array($ext, $allowedThermalExtensions, true)) {
                return response()->json([
                    'message' => 'Solo se permiten archivos térmicos .is2 o paquetes .zip.',
                    'errors' => ['termografias' => ['Extensión no permitida: .' . $ext]],
                ], 422);
            }
        }

        // FIX [008]: Inspección de magic bytes. La regla mimes: de Laravel no cubre
        // el campo "termografias" (.is2 propietario se valida solo por extensión),
        // así que un atacante podría renombrar un ejecutable a .is2/.zip. Bloqueamos
        // por contenido cualquier firma de ejecutable/script en TODOS los adjuntos.
        $uploadedFiles = array_merge(
            $request->hasFile('reporte') ? [$request->file('reporte')] : [],
            (array) $request->file('termografias', []),
            $request->hasFile('imagenes') ? [$request->file('imagenes')] : [],
        );

        $threat = $this->fileSignatureGuard->firstThreat($uploadedFiles);
        if ($threat !== null) {
            Log::warning('upload.rejected.dangerous_signature', [
                'user_id' => $scope['user_id'] ?? null,
                'file_name' => $threat['name'],
                'threat' => $threat['threat'],
            ]);
            return response()->json([
                'message' => 'El archivo adjunto no es válido: se detectó contenido ejecutable.',
                'errors' => ['archivo' => [
                    "El archivo \"{$threat['name']}\" parece contener {$threat['threat']} y fue rechazado por seguridad.",
                ]],
            ], 422);
        }

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

            // 2. Handle Reporte File (informe formal opcional)
            if ($request->hasFile('reporte')) {
                $reportFile = $request->file('reporte');
                $extension = strtolower($reportFile->getClientOriginalExtension());
                $tipo = match ($extension) {
                    'xls', 'xlsx' => 'informe_excel',
                    'pdf' => 'informe_pdf',
                    default => 'informe_word',
                };

                $this->storeInspectionFile($inspeccion, $reportFile, 'reports', $tipo, $userId);
            }

            // 3. Handle Termografías (múltiples .is2 / .zip)
            foreach ((array) $request->file('termografias', []) as $thermalFile) {
                $ext = strtolower($thermalFile->getClientOriginalExtension());
                $tipo = $ext === 'zip' ? 'termografia_zip' : 'termografia_is2';
                $this->storeInspectionFile($inspeccion, $thermalFile, 'termografias', $tipo, $userId);
            }

            // 3b. Compatibilidad: campo legacy "imagenes" (un único ZIP).
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
