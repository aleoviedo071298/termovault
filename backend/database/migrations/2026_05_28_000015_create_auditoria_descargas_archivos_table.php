<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_descargas_archivos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('archivo_id')->constrained('archivos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('descargado_en')->useCurrent();

            $table->index(['archivo_id', 'descargado_en'], 'idx_aud_desc_archivo_fecha');
            $table->index(['usuario_id', 'descargado_en'], 'idx_aud_desc_usuario_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_descargas_archivos');
    }
};

