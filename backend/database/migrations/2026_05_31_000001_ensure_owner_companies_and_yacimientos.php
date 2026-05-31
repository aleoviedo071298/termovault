<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $paeId = $this->ensureEmpresa('PAE');
        $capsaId = $this->ensureEmpresa('CAPSA');

        $this->ensureYacimiento($paeId, 'PAE', 'YAC-PAE');
        $this->ensureYacimiento($capsaId, 'CAPSA', 'YAC-CAPSA');
    }

    public function down(): void
    {
        // Data-only migration. Do not delete production companies/yacimientos on rollback.
    }

    private function ensureEmpresa(string $nombre): int
    {
        $existingId = DB::table('empresas')
            ->whereRaw('UPPER(nombre) = ?', [mb_strtoupper($nombre)])
            ->value('id');

        if ($existingId) {
            DB::table('empresas')
                ->where('id', (int) $existingId)
                ->update([
                    'nombre' => $nombre,
                    'activo' => true,
                    'updated_at' => now(),
                ]);

            return (int) $existingId;
        }

        return (int) DB::table('empresas')->insertGetId([
            'nombre' => $nombre,
            'plan' => 'basico',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureYacimiento(int $empresaId, string $nombre, string $codigo): void
    {
        $existingId = DB::table('yacimientos')
            ->whereRaw('UPPER(codigo) = ?', [mb_strtoupper($codigo)])
            ->value('id');

        $payload = [
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'codigo' => $codigo,
            'activo' => true,
            'permite_supervisor_elementos' => true,
        ];

        if ($existingId) {
            DB::table('yacimientos')
                ->where('id', (int) $existingId)
                ->update($payload);
            return;
        }

        DB::table('yacimientos')->insert([
            ...$payload,
            'created_at' => now(),
        ]);
    }
};
