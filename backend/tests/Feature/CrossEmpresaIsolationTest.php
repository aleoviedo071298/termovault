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
 * FIX [004] — Aislamiento multi-tenant: un supervisor de la empresa A no puede
 * enumerar ni acceder a yacimientos/elementos de la empresa B.
 *
 * El hallazgo [004] señalaba que la exposición de `yacimiento_id` en las
 * respuestas permitiría enumerar yacimientos ajenos. Estos tests verifican que
 * el AccessScopeResolver scopea TODAS las respuestas por los yacimientos
 * asignados, de modo que ningún identificador de otra empresa se filtra.
 */
class CrossEmpresaIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Yacimiento $yacimientoA;
    private Yacimiento $yacimientoB;
    private Elemento $elementoA;
    private Elemento $elementoB;
    private Usuario $supervisorA;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'supervisor', 'tecnico'] as $code) {
            \App\Models\Role::firstOrCreate(['codigo' => $code], ['nombre' => $code]);
        }

        $tipo = TipoElemento::factory()->create(['codigo' => 'subestacion']);

        $empresaA = Empresa::factory()->create(['nombre' => 'Empresa A']);
        $empresaB = Empresa::factory()->create(['nombre' => 'Empresa B']);

        $this->yacimientoA = Yacimiento::factory()->create([
            'empresa_id' => $empresaA->id,
            'codigo' => 'YAC-A',
            'permite_supervisor_elementos' => true,
        ]);
        $this->yacimientoB = Yacimiento::factory()->create([
            'empresa_id' => $empresaB->id,
            'codigo' => 'YAC-B',
            'permite_supervisor_elementos' => true,
        ]);

        $this->elementoA = Elemento::factory()->create([
            'yacimiento_id' => $this->yacimientoA->id,
            'tipo_elemento_id' => $tipo->id,
            'nombre' => 'Elemento A',
            'codigo' => 'ELM-A',
        ]);
        $this->elementoB = Elemento::factory()->create([
            'yacimiento_id' => $this->yacimientoB->id,
            'tipo_elemento_id' => $tipo->id,
            'nombre' => 'Elemento B',
            'codigo' => 'ELM-B',
        ]);

        // Supervisor de la empresa A, asignado únicamente al yacimiento A.
        $this->supervisorA = Usuario::factory()->supervisor()->create(['empresa_id' => $empresaA->id]);
        $this->supervisorA->yacimientos()->attach($this->yacimientoA->id);
    }

    public function test_list_elements_does_not_leak_other_empresa(): void
    {
        $response = $this->actingAs($this->supervisorA)->getJson('/api/elementos');
        $response->assertOk();

        $elementos = collect($response->json());
        $ids = $elementos->pluck('id')->all();

        $this->assertContains($this->elementoA->id, $ids, 'Debe ver su propio elemento');
        $this->assertNotContains($this->elementoB->id, $ids, 'No debe ver el elemento de otra empresa');

        // Ningún identificador del yacimiento B debe aparecer en la respuesta serializada.
        $raw = $response->getContent();
        $this->assertStringNotContainsString('"yacimiento":"' . $this->yacimientoB->nombre . '"', $raw);
        $this->assertStringNotContainsString('Elemento B', $raw);
    }

    public function test_show_other_empresa_element_returns_404(): void
    {
        // No debe revelar yacimiento_id del elemento ajeno: respuesta 404 idéntica
        // a la de un recurso inexistente (sin distinción de "existe pero prohibido").
        $response = $this->actingAs($this->supervisorA)
            ->getJson("/api/elementos/{$this->elementoB->id}");

        $response->assertStatus(404);
        $this->assertStringNotContainsString((string) $this->yacimientoB->id, $response->getContent());
    }

    public function test_catalogos_only_returns_assigned_yacimientos(): void
    {
        $response = $this->actingAs($this->supervisorA)->getJson('/api/catalogos');
        $response->assertOk();

        $yacimientoIds = collect($response->json('yacimientos'))->pluck('id')->all();

        $this->assertContains($this->yacimientoA->id, $yacimientoIds);
        $this->assertNotContains($this->yacimientoB->id, $yacimientoIds, 'No debe listar yacimientos de otra empresa');
    }

    public function test_element_yacimiento_options_excludes_other_empresa(): void
    {
        $response = $this->actingAs($this->supervisorA)->getJson('/api/elementos/yacimientos');
        $response->assertOk();

        $ids = collect($response->json())->pluck('id')->all();

        $this->assertNotContains($this->yacimientoB->id, $ids);
    }
}
