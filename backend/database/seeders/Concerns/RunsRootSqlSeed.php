<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

trait RunsRootSqlSeed
{
    private function runRootSqlSeed(string $filename): void
    {
        $path = base_path('../database/'.$filename);

        if (! file_exists($path)) {
            throw new RuntimeException("Seed SQL not found: {$path}");
        }

        DB::unprepared(file_get_contents($path));
    }
}
