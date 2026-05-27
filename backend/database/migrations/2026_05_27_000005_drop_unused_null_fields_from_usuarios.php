<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $drops = [];
            if (Schema::hasColumn('usuarios', 'legajo')) {
                $drops[] = 'legajo';
            }
            if (Schema::hasColumn('usuarios', 'telefono')) {
                $drops[] = 'telefono';
            }
            if (Schema::hasColumn('usuarios', 'ultimo_login')) {
                $drops[] = 'ultimo_login';
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            if (! Schema::hasColumn('usuarios', 'legajo')) {
                $table->string('legajo', 50)->nullable();
            }
            if (! Schema::hasColumn('usuarios', 'telefono')) {
                $table->string('telefono', 30)->nullable();
            }
            if (! Schema::hasColumn('usuarios', 'ultimo_login')) {
                $table->timestamp('ultimo_login')->nullable();
            }
        });
    }
};
