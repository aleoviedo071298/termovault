<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection to use when executing this migration.
     *
     * @var string
     */
    protected $connection = 'pgsql';

    public function up(): void
    {
        // Drop foreign key dependencies first if they exist
        Schema::table('sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('sessions', 'user_id')) {
                $table->dropForeignIdFor(\App\Models\User::class, 'user_id');
            }
        });

        // Drop the unused 'users' table from Laravel scaffold
        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        // Restore 'users' table if needed (minimal schema)
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
