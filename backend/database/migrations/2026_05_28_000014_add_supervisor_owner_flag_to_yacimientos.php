<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('yacimientos', 'permite_supervisor_elementos')) {
            Schema::table('yacimientos', function (Blueprint $table): void {
                $table->boolean('permite_supervisor_elementos')->default(false);
            });
        }

        // Backfill to preserve previous behavior where YAC-PAE was owner-scoped.
        DB::table('yacimientos')
            ->whereRaw('UPPER(codigo) = ?', ['YAC-PAE'])
            ->update(['permite_supervisor_elementos' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('yacimientos', 'permite_supervisor_elementos')) {
            Schema::table('yacimientos', function (Blueprint $table): void {
                $table->dropColumn('permite_supervisor_elementos');
            });
        }
    }
};

