<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Trait for running SQL seed files from the root database/ directory.
 *
 * ARCHITECTURE NOTE:
 * This trait loads SQL files from the root-level database/ directory
 * (not backend/database/). It uses base_path('database/...') to be
 * directory-structure agnostic — if the backend/ folder is moved,
 * this still works as long as database/ remains at project root.
 *
 * Used by: DatabaseSeeder for bulk inserts (empresas, roles, etc.)
 */
trait RunsRootSqlSeed
{
    private function runRootSqlSeed(string $filename): void
    {
        // base_path() returns project root, database/ is at root level
        $path = base_path('database/'.$filename);

        if (! file_exists($path)) {
            throw new RuntimeException("Seed SQL not found: {$path}");
        }

        DB::unprepared(file_get_contents($path));
    }
}
