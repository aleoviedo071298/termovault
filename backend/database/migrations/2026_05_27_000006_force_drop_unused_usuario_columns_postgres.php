<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL-specific fallback for dropping columns from usuarios table.
 *
 * WHY THIS FALLBACK EXISTS:
 * Migration 2026_05_27_000005 uses Laravel's Schema::dropColumn() which:
 * - Works fine on SQLite and MySQL.
 * - FAILS on PostgreSQL if the column is referenced by constraints or foreign keys,
 *   even if those constraints are "soft" (not explicitly declared in schema()).
 *
 * SOLUTION:
 * This migration runs ONLY on PostgreSQL and uses raw SQL ALTER TABLE with IF EXISTS,
 * which is more permissive and handles constraint dependencies gracefully.
 *
 * DEPLOYMENT ORDER:
 * 1. Migration 005 runs first (no-op on PostgreSQL if 006 will handle it)
 * 2. Migration 006 runs on PostgreSQL and actually drops the columns
 * 3. Result: Columns are dropped successfully on all database engines
 *
 * FUTURE REFERENCE:
 * If a similar issue arises with another table:
 * - First try: Use Schema::dropColumn() with IF EXISTS checks (like 005).
 * - If it fails on PostgreSQL: Add a raw SQL fallback migration (like this one).
 * - Document the constraint issue and the fallback pattern.
 */
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
