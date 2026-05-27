<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) created_by: por defecto, el técnico que cargó la inspección.
        DB::table('inspecciones')
            ->whereNull('created_by')
            ->whereNotNull('tecnico_id')
            ->update([
                'created_by' => DB::raw('tecnico_id'),
            ]);

        // 2) updated_by: si no existe, usar revisada_por cuando exista; si no, created_by.
        DB::table('inspecciones')
            ->whereNull('updated_by')
            ->whereNotNull('revisada_por')
            ->update([
                'updated_by' => DB::raw('revisada_por'),
            ]);

        DB::table('inspecciones')
            ->whereNull('updated_by')
            ->whereNotNull('created_by')
            ->update([
                'updated_by' => DB::raw('created_by'),
            ]);

        // 3) cerrada_por / fecha_cierre para inspecciones ya cerradas.
        DB::table('inspecciones')
            ->where('estado', 'cerrada')
            ->whereNull('cerrada_por')
            ->whereNotNull('revisada_por')
            ->update([
                'cerrada_por' => DB::raw('revisada_por'),
            ]);

        DB::table('inspecciones')
            ->where('estado', 'cerrada')
            ->whereNull('fecha_cierre')
            ->whereNotNull('fecha_revision')
            ->update([
                'fecha_cierre' => DB::raw('fecha_revision'),
            ]);
    }

    public function down(): void
    {
        // Backfill irreversible: no-op.
    }
};

