<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            if (Schema::hasColumn('usuarios', 'password_hash')) {
                $table->dropColumn('password_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            if (! Schema::hasColumn('usuarios', 'password_hash')) {
                $table->string('password_hash')->nullable();
            }
        });
    }
};

