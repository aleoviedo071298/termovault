<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspecciones', function (Blueprint $table): void {
            $table->dropColumn(['temperatura_ambiente', 'humedad_relativa', 'carga_pct']);
        });
    }

    public function down(): void
    {
        Schema::table('inspecciones', function (Blueprint $table): void {
            $table->decimal('temperatura_ambiente', 4, 1)->nullable();
            $table->decimal('humedad_relativa', 4, 1)->nullable();
            $table->decimal('carga_pct', 4, 1)->nullable();
        });
    }
};
