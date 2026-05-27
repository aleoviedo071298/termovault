<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspecciones', function (Blueprint $table): void {
            if (! Schema::hasColumn('inspecciones', 'cerrada_por')) {
                $table->foreignId('cerrada_por')->nullable()->after('revisada_por')->constrained('usuarios')->nullOnDelete();
            }
            if (! Schema::hasColumn('inspecciones', 'fecha_cierre')) {
                $table->timestamp('fecha_cierre')->nullable()->after('fecha_revision');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inspecciones', function (Blueprint $table): void {
            if (Schema::hasColumn('inspecciones', 'cerrada_por')) {
                $table->dropConstrainedForeignId('cerrada_por');
            }
            if (Schema::hasColumn('inspecciones', 'fecha_cierre')) {
                $table->dropColumn('fecha_cierre');
            }
        });
    }
};

