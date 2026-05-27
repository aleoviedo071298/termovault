<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE usuarios DROP COLUMN IF EXISTS legajo');
        DB::statement('ALTER TABLE usuarios DROP COLUMN IF EXISTS telefono');
        DB::statement('ALTER TABLE usuarios DROP COLUMN IF EXISTS ultimo_login');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS legajo VARCHAR(50) NULL');
        DB::statement('ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS telefono VARCHAR(30) NULL');
        DB::statement('ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ultimo_login TIMESTAMP NULL');
    }
};
