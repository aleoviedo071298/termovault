<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('inspecciones')
            ->where('estado', 'borrador')
            ->update(['estado' => 'enviada']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inspecciones ALTER COLUMN estado SET DEFAULT 'enviada'");
        }

        Schema::dropIfExists('comentarios');
        Schema::dropIfExists('historial_cambios');
        Schema::dropIfExists('sesiones');
    }

    public function down(): void
    {
        Schema::create('comentarios', function ($table): void {
            $table->id();
            $table->foreignId('inspeccion_id')->nullable()->constrained('inspecciones')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->text('contenido');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('historial_cambios', function ($table): void {
            $table->id();
            $table->string('tabla', 50);
            $table->integer('registro_id');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->string('accion', 20);
            $table->jsonb('datos_antes')->nullable();
            $table->jsonb('datos_despues')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tabla', 'registro_id'], 'idx_historial_tabla_reg');
        });

        Schema::create('sesiones', function ($table): void {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('login_at')->useCurrent();
            $table->timestamp('logout_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inspecciones ALTER COLUMN estado SET DEFAULT 'borrador'");
        }
    }
};
