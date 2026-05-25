<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\RunsRootSqlSeed;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    use RunsRootSqlSeed;

    public function run(): void
    {
        $this->runRootSqlSeed('seed-roles.sql');
    }
}
