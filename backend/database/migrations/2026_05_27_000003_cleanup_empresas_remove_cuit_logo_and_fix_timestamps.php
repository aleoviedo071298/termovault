<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('empresas')
            ->whereRaw('LOWER(nombre) = ?', ['pecom'])
            ->whereNull('created_at')
            ->update([
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'cuit')) {
                $table->dropColumn('cuit');
            }
            if (Schema::hasColumn('empresas', 'logo_url')) {
                $table->dropColumn('logo_url');
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'cuit')) {
                $table->string('cuit', 20)->nullable()->unique();
            }
            if (! Schema::hasColumn('empresas', 'logo_url')) {
                $table->string('logo_url', 500)->nullable();
            }
        });
    }
};
