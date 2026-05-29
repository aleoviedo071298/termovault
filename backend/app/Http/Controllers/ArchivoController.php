<?php

namespace App\Http\Controllers;

use App\Models\Archivo;
use App\Services\Auth\AccessScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ArchivoController extends Controller
{
    public function __construct(private readonly AccessScopeResolver $scopeResolver) {}

    public function download(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request);

        $archivo = Archivo::query()
            ->select('archivos.*')
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
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        $disk = Storage::disk($archivo->getStorageDisk());
        if (! $disk->exists($archivo->s3_key)) {
            return response()->json(['message' => 'Archivo no disponible'], 404);
        }

        $filename = $archivo->nombre_original ?: 'archivo-'.$archivo->id;
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
}
