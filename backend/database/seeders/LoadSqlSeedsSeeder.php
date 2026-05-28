<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LoadSqlSeedsSeeder extends Seeder
{
    public function run(): void
    {
        $basePath = base_path('../database');
        $files = [
            $basePath . '/seed-roles.sql',
            $basePath . '/seed-empresas.sql',
            $basePath . '/seed-tipos-elemento.sql',
            $basePath . '/seed-niveles-tension.sql',
            $basePath . '/seed-criticidades.sql',
            $basePath . '/seed-elementos.sql',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                $sql = file_get_contents($file);
                DB::unprepared($sql);
                $this->command->info('✓ Loaded: ' . basename($file));
            } else {
                $this->command->warn('✗ Not found: ' . $file);
            }
        }
    }
}
