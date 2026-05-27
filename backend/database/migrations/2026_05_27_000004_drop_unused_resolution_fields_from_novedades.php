<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('novedades', function (Blueprint $table): void {
            if (Schema::hasColumn('novedades', 'resuelta_en_inspeccion_id')) {
                $table->dropConstrainedForeignId('resuelta_en_inspeccion_id');
            }
            if (Schema::hasColumn('novedades', 'fecha_resolucion')) {
                $table->dropColumn('fecha_resolucion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('novedades', function (Blueprint $table): void {
            if (! Schema::hasColumn('novedades', 'fecha_resolucion')) {
                $table->timestamp('fecha_resolucion')->nullable();
            }
            if (! Schema::hasColumn('novedades', 'resuelta_en_inspeccion_id')) {
                $table->foreignId('resuelta_en_inspeccion_id')->nullable()->constrained('inspecciones');
            }
        });
    }
};
