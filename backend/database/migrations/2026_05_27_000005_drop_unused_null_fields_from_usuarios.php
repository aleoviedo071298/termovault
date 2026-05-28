<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unused columns from usuarios table.
 *
 * NOTE: This migration uses Laravel's Schema builder dropColumn() method.
 * - Works on SQLite and MySQL without issues.
 * - May fail on PostgreSQL if columns have constraints or dependent foreign keys.
 * - See migration 2026_05_27_000006 for PostgreSQL-specific fallback using raw SQL.
 *
 * Columns removed (all unused for years):
 * - legajo: Employee ID (legacy field, not part of auth model)
 * - telefono: Phone number (not required by business logic)
 * - ultimo_login: Last login timestamp (can use JWT iat/exp claims instead)
 */
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
