<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\RunsRootSqlSeed;
use Illuminate\Database\Seeder;

class TipoElementoSeeder extends Seeder
{
    use RunsRootSqlSeed;

    public function run(): void
    {
        $this->runRootSqlSeed('seed-tipos-elemento.sql');
    }
}
