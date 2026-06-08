<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Inspecciona los "magic bytes" (cabecera binaria) de un archivo subido para
 * detectar contenido ejecutable/scripts disfrazados con una extensión inocente.
 *
 * Estrategia: DENY-LIST de firmas peligrosas (no allow-list), porque:
 *   - Los archivos térmicos .is2 son un formato propietario (Fluke) sin firma
 *     estable garantizada entre versiones de cámara; un allow-list estricto
 *     rechazaría termografías legítimas en producción.
 *   - El vector real de [008] es "malware disfrazado de PDF/IS2/ZIP": basta con
 *     bloquear las firmas de ejecutables/scripts conocidos para neutralizarlo,
 *     sin riesgo de falsos positivos sobre formatos legítimos.
 *
 * Nota: NO bloqueamos OLE (D0CF11E0) porque los informes .doc/.xls legítimos lo
 * usan; el riesgo real (renombrar un .exe a .pdf) queda cubierto por la firma MZ.
 */
class FileSignatureGuard
{
    /**
     * Firmas binarias de contenido ejecutable/script peligroso.
     * Cada entrada: prefijo de bytes (hex en minúscula) => etiqueta legible.
     */
    private const DANGEROUS_SIGNATURES = [
        '4d5a'     => 'ejecutable de Windows (PE/DOS MZ)',
        '7f454c46' => 'ejecutable de Linux (ELF)',
        'feedface' => 'ejecutable de macOS (Mach-O 32-bit)',
        'feedfacf' => 'ejecutable de macOS (Mach-O 64-bit)',
        'cefaedfe' => 'ejecutable de macOS (Mach-O 32-bit, LE)',
        'cffaedfe' => 'ejecutable de macOS (Mach-O 64-bit, LE)',
        'cafebabe' => 'binario Java .class / Mach-O universal',
        'bebafeca' => 'binario Mach-O universal (LE)',
        '2321'     => 'script con shebang (#!)',
        '4d534346' => 'gabinete de Windows (MS-CAB)',
        '377abcaf271c' => 'archivo 7-Zip ejecutable encubierto',
    ];

    /**
     * Cantidad de bytes a leer de la cabecera (suficiente para todas las firmas).
     */
    private const HEADER_BYTES = 8;

    /**
     * Devuelve la etiqueta de la amenaza si el archivo tiene una firma peligrosa,
     * o null si la cabecera no coincide con ningún ejecutable/script conocido.
     */
    public function detectDangerous(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if ($path === false || $path === '' || ! is_readable($path)) {
            // Sin ruta legible no podemos inspeccionar; lo dejamos pasar para que
            // las demás reglas de validación (mimes/size) decidan.
            return null;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $header = (string) fread($handle, self::HEADER_BYTES);
        fclose($handle);

        if ($header === '') {
            return null;
        }

        $hex = strtolower(bin2hex($header));

        foreach (self::DANGEROUS_SIGNATURES as $signature => $label) {
            if (str_starts_with($hex, $signature)) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Inspecciona una colección de archivos y devuelve el primer hallazgo
     * peligroso como ['name' => ..., 'threat' => ...], o null si todos son seguros.
     *
     * @param  iterable<UploadedFile|null>  $files
     */
    public function firstThreat(iterable $files): ?array
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $threat = $this->detectDangerous($file);
            if ($threat !== null) {
                return [
                    'name' => $file->getClientOriginalName(),
                    'threat' => $threat,
                ];
            }
        }

        return null;
    }
}
