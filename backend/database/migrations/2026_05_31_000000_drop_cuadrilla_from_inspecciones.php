<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina la columna `cuadrilla` de `inspecciones`.
 *
 * Motivo: la columna nunca tuvo un input real en el frontend; el form la
 * hardcodeaba a "625" (código de prueba olvidado), por lo que todos los
 * registros quedaron con el mismo valor sin significado. La cuadrilla real
 * se captura en `integrantes`. Sin uso funcional → se elimina.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('inspecciones', 'cuadrilla')) {
            Schema::table('inspecciones', function (Blueprint $table): void {
                $table->dropColumn('cuadrilla');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('inspecciones', 'cuadrilla')) {
            Schema::table('inspecciones', function (Blueprint $table): void {
                $table->string('cuadrilla', 100)->nullable();
            });
        }
    }
};
