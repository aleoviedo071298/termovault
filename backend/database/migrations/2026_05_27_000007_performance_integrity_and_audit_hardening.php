<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elementos', function (Blueprint $table): void {
            if (! Schema::hasColumn('elementos', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('usuarios')->nullOnDelete();
            }
            if (! Schema::hasColumn('elementos', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('usuarios')->nullOnDelete();
            }
        });

        Schema::table('inspecciones', function (Blueprint $table): void {
            if (! Schema::hasColumn('inspecciones', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('usuarios')->nullOnDelete();
            }
            if (! Schema::hasColumn('inspecciones', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('usuarios')->nullOnDelete();
            }
        });

        // Composite indexes for dashboard/reporting queries.
        Schema::table('inspecciones', function (Blueprint $table): void {
            $table->index(['tecnico_id', 'fecha_inspeccion'], 'idx_inspecciones_tecnico_fecha');
            $table->index(['estado', 'fecha_inspeccion'], 'idx_inspecciones_estado_fecha');
        });

        Schema::table('elementos', function (Blueprint $table): void {
            $table->index(['yacimiento_id', 'tipo_elemento_id'], 'idx_elementos_yacimiento_tipo');
        });

        Schema::table('novedades', function (Blueprint $table): void {
            $table->index(['inspeccion_id', 'criticidad_id'], 'idx_novedades_inspeccion_criticidad');
        });

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("UPDATE usuarios SET email = LOWER(TRIM(email))");
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS idx_usuarios_email_lower_unique ON usuarios ((LOWER(email)))");

            DB::statement("
                DO $$
                BEGIN
                  IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'chk_inspecciones_estado'
                  ) THEN
                    ALTER TABLE inspecciones
                    ADD CONSTRAINT chk_inspecciones_estado
                    CHECK (estado IN ('enviada','revisada','cerrada'));
                  END IF;
                END $$;
            ");

            DB::statement("
                DO $$
                BEGIN
                  IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'chk_novedades_estado'
                  ) THEN
                    ALTER TABLE novedades
                    ADD CONSTRAINT chk_novedades_estado
                    CHECK (estado IN ('abierta','resuelta'));
                  END IF;
                END $$;
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_usuarios_email_lower_unique');
            DB::statement('ALTER TABLE inspecciones DROP CONSTRAINT IF EXISTS chk_inspecciones_estado');
            DB::statement('ALTER TABLE novedades DROP CONSTRAINT IF EXISTS chk_novedades_estado');
        }

        Schema::table('novedades', function (Blueprint $table): void {
            $table->dropIndex('idx_novedades_inspeccion_criticidad');
        });

        Schema::table('elementos', function (Blueprint $table): void {
            $table->dropIndex('idx_elementos_yacimiento_tipo');
        });

        Schema::table('inspecciones', function (Blueprint $table): void {
            $table->dropIndex('idx_inspecciones_tecnico_fecha');
            $table->dropIndex('idx_inspecciones_estado_fecha');
        });

        Schema::table('inspecciones', function (Blueprint $table): void {
            if (Schema::hasColumn('inspecciones', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('inspecciones', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }
        });

        Schema::table('elementos', function (Blueprint $table): void {
            if (Schema::hasColumn('elementos', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('elementos', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }
        });
    }
};
