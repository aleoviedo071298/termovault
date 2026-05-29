<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->index('rol_id', 'idx_usuarios_rol');
        });

        Schema::table('elementos', function (Blueprint $table) {
            $table->index('criticidad_id', 'idx_elementos_criticidad');
        });

        Schema::table('inspecciones', function (Blueprint $table) {
            $table->index('estado', 'idx_inspecciones_estado');
            $table->index('revisada_por', 'idx_inspecciones_revisada_por');
            $table->index('cerrada_por', 'idx_inspecciones_cerrada_por');
        });

        Schema::table('archivos', function (Blueprint $table) {
            $table->index('subido_por', 'idx_archivos_subido_por');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropIndex('idx_usuarios_rol');
        });

        Schema::table('elementos', function (Blueprint $table) {
            $table->dropIndex('idx_elementos_criticidad');
        });

        Schema::table('inspecciones', function (Blueprint $table) {
            $table->dropIndex('idx_inspecciones_estado');
            $table->dropIndex('idx_inspecciones_revisada_por');
            $table->dropIndex('idx_inspecciones_cerrada_por');
        });

        Schema::table('archivos', function (Blueprint $table) {
            $table->dropIndex('idx_archivos_subido_por');
        });
    }
};
