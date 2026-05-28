<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the unused 'users' table from Laravel scaffold
        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        // No-op
    }
};
