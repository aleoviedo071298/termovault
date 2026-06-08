<?php

namespace Tests\Feature;

use App\Models\Elemento;
use App\Models\Empresa;
use App\Models\TipoElemento;
use App\Models\Usuario;
use App\Models\Yacimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrato de comportamiento del alcance (scope) de un SUPERVISOR.
 *
 * Documenta y fija la política intencional (revisada a raíz del hallazgo [012],
 * cerrado como FALSO POSITIVO — no es escalada cross-empresa):
 *
 *  1. Supervisor CON yacimientos asignados → ve SOLO los asignados.
 *  2. Supervisor SIN asignación explícita → fallback: ve los yacimientos de SU
 *     PROPIA empresa (no es least-privilege, es by-design para operación).
 *  3. En NINGÚN caso ve yacimientos/elementos de OTRA empresa (aislamiento
 *     multi-tenant garantizado por AccessScopeResolver::applyElementScope).
 *
 * Si algún cambio futuro rompe estas aserciones, es una decisión consciente de
 * política y debe revisarse explícitamente.
 */
class SupervisorScopePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'supervisor', 'tecnico'] as $code) {
            \App\Models\Role::firstOrCreate(['codigo' => $code], ['nombre' => $code]);
        }
    }

    private function elemento(Yacimiento $y, string $codigo): Elemento
    {
        $tipo = TipoElemento::firstOrCreate(['codigo' => 'subestacion'], ['nombre' => 'Subestacion']);
        return Elemento::factory()->create([
            'yacimiento_id' => $y->id,
            'tipo_elemento_id' => $tipo->id,
            'codigo' => $codigo,
            'nombre' => 'EL-' . $codigo,
        ]);
    }

    /**
     * @return string[] códigos de elemento visibles para el usuario en GET /elementos
     */
    private function visibleCodes(Usuario $user): array
    {
        $resp = $this->actingAs($user)->getJson('/api/elementos')->assertOk();
        return collect($resp->json())->pluck('codigo')->sort()->values()->all();
    }

    public function test_supervisor_sin_asignacion_ve_su_empresa_completa_pero_nunca_otra(): void
    {
        $capsa = Empresa::factory()->create(['nombre' => 'CAPSA']);
        $otra  = Empresa::factory()->create(['nombre' => 'OTRA']);

        $this->elemento(Yacimiento::factory()->create(['empresa_id' => $capsa->id, 'codigo' => 'C1']), 'EC1');
        $this->elemento(Yacimiento::factory()->create(['empresa_id' => $capsa->id, 'codigo' => 'C2']), 'EC2');
        $this->elemento(Yacimiento::factory()->create(['empresa_id' => $otra->id, 'codigo' => 'O1']), 'EO1');

        $sup = Usuario::factory()->supervisor()->create(['empresa_id' => $capsa->id]);

        $this->assertSame(['EC1', 'EC2'], $this->visibleCodes($sup));
    }

    public function test_supervisor_con_asignacion_queda_restringido_a_lo_asignado(): void
    {
        $capsa = Empresa::factory()->create(['nombre' => 'CAPSA']);

        $yC1 = Yacimiento::factory()->create(['empresa_id' => $capsa->id, 'codigo' => 'C1']);
        $this->elemento($yC1, 'EC1');
        $this->elemento(Yacimiento::factory()->create(['empresa_id' => $capsa->id, 'codigo' => 'C2']), 'EC2');

        $sup = Usuario::factory()->supervisor()->create(['empresa_id' => $capsa->id]);
        $sup->yacimientos()->attach($yC1->id);

        $this->assertSame(['EC1'], $this->visibleCodes($sup));
    }

    public function test_supervisor_de_contratista_sin_yacimientos_propios_ve_vacio(): void
    {
        // Una contratista que no POSEE yacimientos: el fallback por empresa
        // devuelve vacío (fail-closed), no expone nada de los operadores.
        $operadora   = Empresa::factory()->create(['nombre' => 'OPERADORA']);
        $contratista = Empresa::factory()->create(['nombre' => 'CONTRATISTA']);

        $this->elemento(Yacimiento::factory()->create(['empresa_id' => $operadora->id, 'codigo' => 'OP1']), 'EOP1');

        $sup = Usuario::factory()->supervisor()->create(['empresa_id' => $contratista->id]);

        $this->assertSame([], $this->visibleCodes($sup));
    }
}
