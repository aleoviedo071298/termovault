<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment(['local', 'testing'])) {
            $this->dropTermoVaultTables();
        }

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 150);
            $table->string('cuit', 20)->unique()->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('plan', 30)->default('basico');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('yacimientos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('codigo', 50);
            $table->string('zona', 100)->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['empresa_id', 'codigo']);
            $table->index('empresa_id', 'idx_yacimientos_empresa');
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 50);
        });

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('rol_id')->constrained('roles');
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('email', 150)->unique();
            $table->string('password_hash');
            $table->string('legajo', 50)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_login')->nullable();
            $table->timestamps();

            $table->index('empresa_id', 'idx_usuarios_empresa');
        });

        Schema::create('usuario_yacimientos', function (Blueprint $table): void {
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('yacimiento_id')->constrained('yacimientos')->cascadeOnDelete();
            $table->primary(['usuario_id', 'yacimiento_id']);
        });

        Schema::create('tipos_elemento', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->string('prefijo_archivo', 20)->nullable();
            $table->boolean('requiere_tension')->default(false);
            $table->boolean('activo')->default(true);
        });

        Schema::create('niveles_tension', function (Blueprint $table): void {
            $table->id();
            $table->decimal('kv', 6, 2);
            $table->string('etiqueta', 20);
            $table->boolean('activo')->default(true);
        });

        Schema::create('criticidades', function (Blueprint $table): void {
            $table->id();
            $table->integer('nivel')->unique();
            $table->string('nombre', 30);
            $table->string('color', 7)->nullable();
        });

        Schema::create('elementos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('yacimiento_id')->constrained('yacimientos')->restrictOnDelete();
            $table->foreignId('tipo_elemento_id')->constrained('tipos_elemento');
            $table->string('funcion', 20)->nullable();
            $table->foreignId('nivel_tension_id')->nullable()->constrained('niveles_tension');
            $table->string('nombre', 150);
            $table->string('codigo', 50);
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('n_serie', 100)->nullable();
            $table->foreignId('criticidad_id')->nullable()->constrained('criticidades');
            $table->string('estado_operativo', 30)->default('operativo');
            $table->text('observaciones_generales')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['yacimiento_id', 'codigo']);
            $table->index('yacimiento_id', 'idx_elementos_yacimiento');
            $table->index('tipo_elemento_id', 'idx_elementos_tipo');
            $table->index('funcion', 'idx_elementos_funcion');
            $table->index('nivel_tension_id', 'idx_elementos_tension');
        });

        Schema::create('inspecciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('elemento_id')->constrained('elementos')->restrictOnDelete();
            $table->foreignId('tecnico_id')->constrained('usuarios');
            $table->timestamp('fecha_inspeccion');
            $table->string('cuadrilla', 100)->nullable();
            $table->text('integrantes')->nullable();
            $table->string('empresa_contratista', 150)->nullable();
            $table->decimal('temperatura_ambiente', 4, 1)->nullable();
            $table->decimal('humedad_relativa', 4, 1)->nullable();
            $table->decimal('carga_pct', 4, 1)->nullable();
            $table->string('condiciones_clima', 50)->nullable();
            $table->text('resumen')->nullable();
            $table->string('estado', 20)->default('borrador');
            $table->foreignId('revisada_por')->nullable()->constrained('usuarios');
            $table->timestamp('fecha_revision')->nullable();
            $table->text('observaciones_revisor')->nullable();
            $table->timestamps();

            $table->index('elemento_id', 'idx_inspecciones_elemento');
            $table->index('tecnico_id', 'idx_inspecciones_tecnico');
            $table->index('fecha_inspeccion', 'idx_inspecciones_fecha');
        });

        Schema::create('archivos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspeccion_id')->constrained('inspecciones')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('nombre_original')->nullable();
            $table->string('s3_bucket', 100)->nullable();
            $table->string('s3_key', 500);
            $table->bigInteger('tamano_bytes')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('usuarios');
            $table->timestamp('created_at')->useCurrent();

            $table->index('inspeccion_id', 'idx_archivos_inspeccion');
        });

        Schema::create('novedades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspeccion_id')->constrained('inspecciones')->cascadeOnDelete();
            $table->foreignId('criticidad_id')->nullable()->constrained('criticidades');
            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->string('ubicacion_dentro_elemento', 200)->nullable();
            $table->decimal('temperatura_detectada', 5, 2)->nullable();
            $table->text('accion_recomendada')->nullable();
            $table->string('estado', 20)->default('abierta');
            $table->timestamp('fecha_resolucion')->nullable();
            $table->foreignId('resuelta_en_inspeccion_id')->nullable()->constrained('inspecciones');
            $table->timestamps();

            $table->index('inspeccion_id', 'idx_novedades_inspeccion');
            $table->index('estado', 'idx_novedades_estado');
            $table->index('criticidad_id', 'idx_novedades_criticidad');
        });

        Schema::create('comentarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspeccion_id')->nullable()->constrained('inspecciones')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->text('contenido');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('historial_cambios', function (Blueprint $table): void {
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

        Schema::create('sesiones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('login_at')->useCurrent();
            $table->timestamp('logout_at')->nullable();
        });
    }

    public function down(): void
    {
        $this->dropTermoVaultTables();
    }

    private function dropTermoVaultTables(): void
    {
        Schema::dropIfExists('sesiones');
        Schema::dropIfExists('historial_cambios');
        Schema::dropIfExists('comentarios');
        Schema::dropIfExists('novedades');
        Schema::dropIfExists('archivos');
        Schema::dropIfExists('inspecciones');
        Schema::dropIfExists('elementos');
        Schema::dropIfExists('criticidades');
        Schema::dropIfExists('niveles_tension');
        Schema::dropIfExists('tipos_elemento');
        Schema::dropIfExists('usuario_yacimientos');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('yacimientos');
        Schema::dropIfExists('empresas');
    }
};
