<?php

namespace App\Http\Controllers;

use App\Models\Archivo;
use App\Services\Auth\AccessScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ArchivoController extends Controller
{
    public function __construct(private readonly AccessScopeResolver $scopeResolver) {}

    public function download(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);

        $archivo = Archivo::query()
            ->select('archivos.*', 'e.nombre as elemento_nombre', 'e.codigo as elemento_codigo', 'i.fecha_inspeccion as inspeccion_fecha')
            ->join('inspecciones as i', 'i.id', '=', 'archivos.inspeccion_id')
            ->join('elementos as e', 'e.id', '=', 'i.elemento_id')
            ->join('yacimientos as y', 'y.id', '=', 'e.yacimiento_id')
            ->join('usuarios as u', 'u.id', '=', 'i.tecnico_id')
            ->where('archivos.id', $id)
            ->when(! $scope['is_admin'], function ($query) use ($scope): void {
                if ($scope['is_tecnico']) {
                    $query->where('i.tecnico_id', $scope['user_id']);
                    if ($scope['assigned_yacimiento_ids'] !== []) {
                        $query->whereIn('e.yacimiento_id', $scope['assigned_yacimiento_ids']);
                    }
                    return;
                }

                if ($scope['is_supervisor']) {
                    if (($scope['is_owner_supervisor'] ?? false) && $scope['assigned_yacimiento_ids'] !== []) {
                        $query->whereIn('e.yacimiento_id', $scope['assigned_yacimiento_ids']);
                        return;
                    }

                    $query->where('u.empresa_id', $scope['empresa_id']);
                    if ($scope['assigned_yacimiento_ids'] !== []) {
                        $query->whereIn('e.yacimiento_id', $scope['assigned_yacimiento_ids']);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                    return;
                }

                $query->whereRaw('1 = 0');
            })
            ->first();

        if (! $archivo) {
            // M3: si el archivo existe pero quedó fuera de scope, es un intento de acceso
            // no autorizado y debe registrarse (mismo patrón que AccessScopeResolver).
            // No se revela al cliente la diferencia entre "no existe" y "sin permiso".
            if (Archivo::whereKey($id)->exists()) {
                Log::notice('auth.scope.violation', [
                    'event' => 'archivo.download.denied.out_of_scope',
                    'user_id' => $scope['user_id'] ?? null,
                    'empresa_id' => $scope['empresa_id'] ?? null,
                    'roles' => $scope['roles'] ?? [],
                    'archivo_id' => $id,
                    'ip' => $request->ip(),
                    'ua' => $request->userAgent(),
                ]);
            }

            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        $disk = Storage::disk($archivo->getStorageDisk());
        if (! $disk->exists($archivo->s3_key)) {
            return response()->json(['message' => 'Archivo no disponible'], 404);
        }

        // Validate file content against declared MIME type (magic bytes check)
        // Prevents serving e.g. a ZIP disguised as DOCX
        if (! $this->validateMimeTypeMatch($disk, $archivo)) {
            Log::warning('MIME type mismatch on download', [
                'archivo_id' => $archivo->id,
                'declared_mime' => $archivo->mime_type,
                's3_key' => $archivo->s3_key,
            ]);

            return response()->json([
                'message' => 'Archivo corrupto o MIME type inválido',
            ], 422);
        }

        $filename = $this->buildDownloadFilename($archivo);
        $headers = array_filter([
            'Content-Type' => $archivo->mime_type ?: 'application/octet-stream',
            'Content-Length' => $archivo->tamano_bytes ? (string) $archivo->tamano_bytes : null,
        ]);

        try {
            DB::table('auditoria_descargas_archivos')->insert([
                'archivo_id' => (int) $archivo->id,
                'usuario_id' => isset($scope['user_id']) ? (int) $scope['user_id'] : null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'descargado_en' => now(),
            ]);
        } catch (Throwable $e) {
            // La auditoria no debe bloquear la operacion principal de descarga.
            Log::warning('No se pudo registrar auditoria de descarga', [
                'archivo_id' => $archivo->id,
                'user_id' => $scope['user_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->streamDownload(function () use ($disk, $archivo): void {
            $stream = $disk->readStream($archivo->s3_key);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
                return;
            }

            echo $disk->get($archivo->s3_key);
        }, $filename, $headers);
    }

    /**
     * Build a normalized, human-friendly download filename:
     *   {archivo_id}_{nombreElemento}_{dd-mm-aaaa}_{tipo}.{ext}
     *
     * Examples:
     *   137_Trafo-Principal_29-05-2026_termografia.is2
     *   138_Trafo-Principal_29-05-2026_informe.pdf
     *
     * Computed at download time from DB data, so it also applies to files
     * uploaded before this convention existed. The physical s3_key is untouched.
     */
    private function buildDownloadFilename(Archivo $archivo): string
    {
        $elemento = $this->sanitizeSegment(
            (string) ($archivo->elemento_nombre ?? $archivo->elemento_codigo ?? 'elemento')
        );

        $fecha = 'sin-fecha';
        if (! empty($archivo->inspeccion_fecha)) {
            try {
                $fecha = Carbon::parse($archivo->inspeccion_fecha)->format('d-m-Y');
            } catch (Throwable) {
                $fecha = 'sin-fecha';
            }
        }

        $tipo = $this->downloadTipoLabel((string) $archivo->tipo);

        // Extensión original (desde el nombre original o, en su defecto, desde la s3_key).
        $ext = strtolower(pathinfo((string) ($archivo->nombre_original ?: $archivo->s3_key), PATHINFO_EXTENSION));

        $base = "{$archivo->id}_{$elemento}_{$fecha}_{$tipo}";

        return $ext !== '' ? "{$base}.{$ext}" : $base;
    }

    /**
     * Map the internal `tipo` to a short user-facing label.
     */
    private function downloadTipoLabel(string $tipo): string
    {
        if (str_starts_with($tipo, 'informe')) {
            return 'informe';
        }

        if (str_starts_with($tipo, 'termografia') || $tipo === 'pack_imagenes_zip') {
            return 'termografia';
        }

        return 'adjunto';
    }

    /**
     * Sanitize a name segment for safe use in a filename (ASCII, no spaces/specials).
     */
    private function sanitizeSegment(string $value): string
    {
        $value = Str::ascii($value);
        $value = preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'sin-nombre';
    }

    /**
     * Validate file MIME type by checking magic bytes (file signature).
     * Only for real files (>1000 bytes) and known archive/document formats.
     * Prevents serving files with mismatched MIME types (e.g., ZIP disguised as DOCX).
     */
    private function validateMimeTypeMatch($disk, $archivo): bool
    {
        $declaredMime = mb_strtolower((string) ($archivo->mime_type ?? ''));
        $fileSize = $archivo->tamano_bytes ?? 0;

        // Only validate if it's a known Office/archive format AND file is sizeable
        $knownFormats = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/msword',
            'application/zip',
            'application/x-zip-compressed',
            'application/pdf',
        ];

        $isKnownFormat = false;
        foreach ($knownFormats as $fmt) {
            if (str_contains($declaredMime, $fmt) || $declaredMime === $fmt) {
                $isKnownFormat = true;
                break;
            }
        }

        if (!$isKnownFormat || $fileSize < 1000) {
            return true; // Skip validation for unknowns or small files (likely test/temp)
        }

        // Read first 4 bytes to check magic signature
        $stream = $disk->readStream($archivo->s3_key);
        if (!is_resource($stream)) {
            $content = $disk->get($archivo->s3_key);
            $header = substr((string) $content, 0, 4);
        } else {
            $header = fread($stream, 4);
            fclose($stream);
        }

        $header = (string) $header;
        if (strlen($header) < 2) {
            return true;
        }

        // Magic signatures for known formats
        if (str_contains($declaredMime, 'openxmlformats') || str_contains($declaredMime, 'zip')) {
            return str_starts_with($header, 'PK'); // ZIP header
        }

        if (str_contains($declaredMime, 'msword')) {
            return str_starts_with($header, "\xD0\xCF"); // OLE2 header
        }

        if (str_contains($declaredMime, 'pdf')) {
            return str_starts_with($header, '%PDF');
        }

        return true; // Fallback: allow
    }
}
