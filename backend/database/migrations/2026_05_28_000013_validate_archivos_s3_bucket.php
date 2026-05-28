<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivos', function (Blueprint $table) {
            $table->string('s3_bucket', 100)->nullable(false)->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                DO $$
                BEGIN
                  IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'chk_archivos_s3_bucket_valido'
                  ) THEN
                    ALTER TABLE archivos
                    ADD CONSTRAINT chk_archivos_s3_bucket_valido
                    CHECK (s3_bucket IS NOT NULL AND s3_bucket <> 'local');
                  END IF;
                END $$;
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE archivos DROP CONSTRAINT IF EXISTS chk_archivos_s3_bucket_valido');
        }

        Schema::table('archivos', function (Blueprint $table) {
            $table->string('s3_bucket', 100)->nullable()->change();
        });
    }
};
