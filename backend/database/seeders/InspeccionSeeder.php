<?php

namespace Database\Seeders;

use App\Models\Archivo;
use App\Models\Elemento;
use App\Models\Inspeccion;
use App\Models\Novedad;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InspeccionSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch all elements and the default user (Alejandro)
        $elementos = Elemento::all();
        $tecnico = Usuario::where('email', 'aleoviedo071298@gmail.com')->first() 
            ?? Usuario::first();

        if (!$tecnico || $elementos->isEmpty()) {
            return;
        }

        $climas = ['Despejado', 'Parcialmente Nublado', 'Nublado', 'Llovizna', 'Viento Fuerte'];
        $cuadrillas = ['Cuadrilla Norte - Termografía', 'Mantenimiento Preventivo Oeste', 'Monitoreo Predictivo Pecom'];
        $integrantes = [
            "A. Oviedo, M. Gomez, J. Perez",
            "A. Oviedo, R. Lopez",
            "A. Oviedo, S. Diaz, F. Rodriguez"
        ];

        // We will seed inspections for all elements.
        // Some elements will have 2 inspections, some will have 1, some will have 0.
        foreach ($elementos as $index => $elemento) {
            // Determine number of inspections
            $numInspecciones = ($index % 3 === 0) ? 2 : (($index % 3 === 1) ? 1 : 0);

            for ($i = 0; $i < $numInspecciones; $i++) {
                $fecha = Carbon::now()->subMonths(($i + 1) * 3)->subDays($index % 28);
                
                $inspeccion = Inspeccion::create([
                    'elemento_id' => $elemento->id,
                    'tecnico_id' => $tecnico->id,
                    'fecha_inspeccion' => $fecha,
                    'cuadrilla' => $cuadrillas[$index % count($cuadrillas)],
                    'integrantes' => $integrantes[$index % count($integrantes)],
                    'empresa_contratista' => 'PECOM S.A.',
                    'temperatura_ambiente' => 12.5 + ($index % 15) + ($i * 2),
                    'humedad_relativa' => 35 + ($index % 40) - ($i * 5),
                    'carga_pct' => 60.0 + ($index % 30) + ($i * 5),
                    'condiciones_clima' => $climas[$index % count($climas)],
                    'lat_gps' => $elemento->lat ?? -45.8641 + ($index * 0.0001),
                    'lng_gps' => $elemento->lng ?? -67.4983 - ($index * 0.0001),
                    'resumen' => "Inspección termográfica preventiva de rutina en {$elemento->nombre}. Se relevaron todos los componentes críticos bajo carga normal.",
                    'estado' => $i === 0 ? 'revisada' : 'cerrada',
                    'revisada_por' => $tecnico->id,
                    'fecha_revision' => $fecha->copy()->addDays(2),
                    'observaciones_revisor' => 'Informe revisado y aprobado. Novedades ingresadas en el flujo de seguimiento.',
                ]);

                // Create word report file
                Archivo::create([
                    'inspeccion_id' => $inspeccion->id,
                    'tipo' => 'informe_word',
                    'nombre_original' => "Informe Termografico - {$elemento->codigo} - {$fecha->format('Ymd')}.docx",
                    's3_bucket' => 'termovault-reports',
                    's3_key' => "reports/{$elemento->id}/{$inspeccion->id}_report.docx",
                    'tamano_bytes' => 1024 * 1024 * (1.5 + ($index % 5) / 2),
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'subido_por' => $tecnico->id,
                ]);

                // Create pack images zip file
                Archivo::create([
                    'inspeccion_id' => $inspeccion->id,
                    'tipo' => 'pack_imagenes_zip',
                    'nombre_original' => "Imagenes Termograficas - {$elemento->codigo} - {$fecha->format('Ymd')}.zip",
                    's3_bucket' => 'termovault-images',
                    's3_key' => "images/{$elemento->id}/{$inspeccion->id}_images.zip",
                    'tamano_bytes' => 1024 * 1024 * (15 + ($index % 30)),
                    'mime_type' => 'application/zip',
                    'subido_por' => $tecnico->id,
                ]);

                // If this is a capacitor bank or a high criticity index, or just index % 2 == 0, seed findings (novedades)
                if ($index % 2 === 0) {
                    $criticidadId = ($index % 4) + 1; // 1 to 4 (Baja, Media, Alta, Crítica)
                    
                    Novedad::create([
                        'inspeccion_id' => $inspeccion->id,
                        'criticidad_id' => $criticidadId,
                        'titulo' => "Sobrecalentamiento en punto de conexión",
                        'descripcion' => "Se detectó una temperatura elevada de " . (45 + $index % 40) . "°C en la fase " . ($index % 3 === 0 ? 'R' : ($index % 3 === 1 ? 'S' : 'T')) . " del conexionado principal.",
                        'ubicacion_dentro_elemento' => "Borna superior de fase " . ($index % 3 === 0 ? 'A' : 'B'),
                        'temperatura_detectada' => 45.0 + ($index % 40),
                        'accion_recomendada' => "Realizar limpieza de contactos, reapriete y aplicar grasa conductora en la próxima parada programada.",
                        'estado' => $i === 0 ? 'abierta' : 'resuelta',
                        'fecha_resolucion' => $i === 0 ? null : $fecha->copy()->addDays(15),
                        'resuelta_en_inspeccion_id' => null,
                    ]);
                }
            }
        }
    }
}
