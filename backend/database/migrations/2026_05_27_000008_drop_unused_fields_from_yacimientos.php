<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yacimientos', function (Blueprint $table) {
            if (Schema::hasColumn('yacimientos', 'zona')) {
                $table->dropColumn('zona');
            }
            if (Schema::hasColumn('yacimientos', 'descripcion')) {
                $table->dropColumn('descripcion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('yacimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('yacimientos', 'zona')) {
                $table->string('zona', 100)->nullable();
            }
            if (! Schema::hasColumn('yacimientos', 'descripcion')) {
                $table->text('descripcion')->nullable();
            }
        });
    }
};

