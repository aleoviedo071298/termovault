<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Yacimiento;
use App\Models\TipoElemento;
use App\Models\Criticidad;
use App\Models\NivelTension;
use App\Models\Elemento;
use App\Models\Inspeccion;
use App\Models\Novedad;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;
    private Yacimiento $yacimiento;
    private TipoElemento $tipoElemento;
    private NivelTension $nivelTension;
    private Criticidad $criticidad;

    protected function setUp(): void
    {
        parent::setUp();

        // Flush cache to ensure we start from a clean state
        Cache::flush();

        // Create initial setup
        $this->yacimiento = Yacimiento::factory()->create(['nombre' => 'PAE', 'activo' => true]);
        $this->tipoElemento = TipoElemento::factory()->create(['nombre' => 'Subestacion', 'codigo' => 'subestacion', 'activo' => true]);
        $this->nivelTension = NivelTension::factory()->create(['kv' => 13.2, 'etiqueta' => '13.2 kV', 'activo' => true]);
        $this->criticidad = Criticidad::factory()->create(['nivel' => 2, 'nombre' => 'Media', 'color' => '#ffcc00']);

        // Create admin user
        $this->admin = Usuario::factory()->admin()->create();

        // Associate user to yacimiento
        DB::table('usuario_yacimientos')->insert([
            'usuario_id' => $this->admin->id,
            'yacimiento_id' => $this->yacimiento->id,
        ]);
    }

    public function test_catalog_endpoint_uses_caching_and_reduces_queries(): void
    {
        // First request: loads everything and caches static parts
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response1 = $this->actingAs($this->admin)->getJson('/api/catalogos');
        $response1->assertOk();
        $queryCount1 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Verify that the cache key was populated
        $this->assertTrue(Cache::has('catalogs.static'));

        // Second request: should hit the cache for static elements
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response2 = $this->actingAs($this->admin)->getJson('/api/catalogos');
        $response2->assertOk();
        $queryCount2 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // The query count of the second request should be significantly lower than the first,
        // and specifically should be < 10 queries.
        $this->assertLessThan($queryCount1, $queryCount2);
        $this->assertLessThan(10, $queryCount2);

        // Verify response contents are identical
        $this->assertEquals($response1->json(), $response2->json());
    }

    public function test_catalog_cache_is_invalidated_when_static_data_changes(): void
    {
        // Populate cache
        $this->actingAs($this->admin)->getJson('/api/catalogos');
        $this->assertTrue(Cache::has('catalogs.static'));

        // Update criticidad -> should clear catalogs.static
        $this->criticidad->update(['nombre' => 'Media Alta']);
        $this->assertFalse(Cache::has('catalogs.static'));

        // Populate cache again
        $this->actingAs($this->admin)->getJson('/api/catalogos');
        $this->assertTrue(Cache::has('catalogs.static'));

        // Create new TipoElemento -> should clear catalogs.static
        TipoElemento::factory()->create(['nombre' => 'Transformador', 'codigo' => 'trafo', 'activo' => true]);
        $this->assertFalse(Cache::has('catalogs.static'));

        // Populate cache again
        $this->actingAs($this->admin)->getJson('/api/catalogos');
        $this->assertTrue(Cache::has('catalogs.static'));

        // Delete NivelTension -> should clear catalogs.static
        $this->nivelTension->delete();
        $this->assertFalse(Cache::has('catalogs.static'));
    }

    public function test_dashboard_overview_uses_caching(): void
    {
        // Create elements and inspections to populate dashboard data
        $elemento = Elemento::factory()->create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipoElemento->id,
            'criticidad_id' => $this->criticidad->id,
            'nivel_tension_id' => $this->nivelTension->id,
        ]);

        $inspeccion = Inspeccion::factory()->create([
            'elemento_id' => $elemento->id,
            'tecnico_id' => $this->admin->id,
            'estado' => Inspeccion::ESTADO_ENVIADA,
        ]);

        // First request to overview (cold)
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response1 = $this->actingAs($this->admin)->getJson('/api/dashboard/overview');
        $response1->assertOk();
        $queryCount1 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Second request (warm)
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response2 = $this->actingAs($this->admin)->getJson('/api/dashboard/overview');
        $response2->assertOk();
        $queryCount2 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Assert query count is significantly reduced
        $this->assertLessThan($queryCount1, $queryCount2);
        // The cached request should execute less than 10 queries (mostly for auth / scope resolving, while complex aggregations are cached)
        $this->assertLessThan(10, $queryCount2);

        // Assert JSON contents are identical
        $this->assertEquals($response1->json(), $response2->json());
    }

    public function test_dashboard_cache_invalidates_on_model_events(): void
    {
        // Populate cache
        $this->actingAs($this->admin)->getJson('/api/dashboard/overview');
        $initialVersion = Cache::get('dashboard.version');
        $this->assertNotNull($initialVersion);

        // Save Element -> should increment version / invalidate cache
        $elemento = Elemento::factory()->create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipoElemento->id,
            'criticidad_id' => $this->criticidad->id,
            'nivel_tension_id' => $this->nivelTension->id,
        ]);
        $versionAfterElement = Cache::get('dashboard.version');
        $this->assertNotEquals($initialVersion, $versionAfterElement);

        // Save Inspeccion -> should increment version / invalidate cache
        $inspeccion = Inspeccion::factory()->create([
            'elemento_id' => $elemento->id,
            'tecnico_id' => $this->admin->id,
            'estado' => Inspeccion::ESTADO_ENVIADA,
        ]);
        $versionAfterInspeccion = Cache::get('dashboard.version');
        $this->assertNotEquals($versionAfterElement, $versionAfterInspeccion);

        // Save Novedad -> should increment version / invalidate cache
        $novedad = Novedad::create([
            'inspeccion_id' => $inspeccion->id,
            'criticidad_id' => $this->criticidad->id,
            'titulo' => 'Falla detectada',
            'descripcion' => 'Prueba hallazgo',
            'accion_recomendada' => 'Ninguna',
            'estado' => 'abierta',
        ]);
        $versionAfterNovedad = Cache::get('dashboard.version');
        $this->assertNotEquals($versionAfterInspeccion, $versionAfterNovedad);
    }
}
