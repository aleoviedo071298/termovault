<?php

namespace Tests\Feature;

use App\Models\Elemento;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\TipoElemento;
use App\Models\Yacimiento;
use App\Services\CognitoJwtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class InspeccionManagementTest extends TestCase
{
    use RefreshDatabase;

    private $empresa;
    private $yacimiento;
    private $tipo;
    private $adminClaims;
    private $techClaims;
    private $admin2Claims;
    private $otherEmpresaClaims;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        $this->empresa = Empresa::create(['nombre' => 'PECOM', 'cuit' => '30-12345678-0']);
        $this->yacimiento = Yacimiento::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'PAE',
            'codigo' => 'YAC-PAE'
        ]);
        $this->tipo = TipoElemento::create([
            'codigo' => 'subestacion',
            'nombre' => 'Subestación'
        ]);

        $adminRole = Role::create(['codigo' => 'admin', 'nombre' => 'Administrador']);
        $techRole = Role::create(['codigo' => 'tecnico', 'nombre' => 'Técnico']);

        \DB::table('criticidades')->insert(['id' => 3, 'nivel' => 3, 'nombre' => 'Alta', 'color' => '#E53E3E']);

        // Insert database users
        \DB::table('usuarios')->insert([
            'empresa_id' => $this->empresa->id,
            'rol_id' => $adminRole->id,
            'nombre' => 'Admin',
            'apellido' => 'User',
            'email' => 'admin@example.com',
            'password_hash' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('usuarios')->insert([
            'empresa_id' => $this->empresa->id,
            'rol_id' => $adminRole->id,
            'nombre' => 'Admin',
            'apellido' => 'Dos',
            'email' => 'admin2@example.com',
            'password_hash' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('usuarios')->insert([
            'empresa_id' => $this->empresa->id,
            'rol_id' => $techRole->id,
            'nombre' => 'Tech',
            'apellido' => 'User',
            'email' => 'tech@example.com',
            'password_hash' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->adminClaims = [
            'sub' => 'admin-123',
            'email' => 'admin@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['admin'],
        ];

        $this->techClaims = [
            'sub' => 'tech-123',
            'email' => 'tech@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['tecnico'],
        ];
        $this->admin2Claims = [
            'sub' => 'admin-456',
            'email' => 'admin2@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['admin'],
        ];

        // Another company user for multi-tenancy verification
        $otherEmpresa = Empresa::create(['nombre' => 'YPF', 'cuit' => '30-99999999-0']);
        \DB::table('usuarios')->insert([
            'empresa_id' => $otherEmpresa->id,
            'rol_id' => $techRole->id,
            'nombre' => 'Other',
            'apellido' => 'User',
            'email' => 'other@example.com',
            'password_hash' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->otherEmpresaClaims = [
            'sub' => 'other-123',
            'email' => 'other@example.com',
            'token_use' => 'access',
            'cognito:groups' => ['tecnico'],
        ];
    }

    private function mockVerifier($claims, $token = 'valid-token')
    {
        $mockVerifier = Mockery::mock(CognitoJwtVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->andReturn($claims);
        $this->app->instance(CognitoJwtVerifier::class, $mockVerifier);
    }

    public function test_technician_can_upload_inspection(): void
    {
        config()->set('cognito.required', true);
        $this->mockVerifier($this->techClaims);

        $elemento = Elemento::create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'Subestacion Test Tech',
            'codigo' => 'SET-TECH-TEST',
        ]);

        $reporte = UploadedFile::fake()->create('reporte.docx', 100);
        $imagenes = UploadedFile::fake()->create('imagenes.zip', 500);

        $novedades = [
            [
                'criticidad_id' => 3,
                'titulo' => 'Bushing sobrecalentado',
                'descripcion' => 'Se observa punto caliente',
                'ubicacion_dentro_elemento' => 'Fase S',
                'temperatura_detectada' => 65.5,
                'accion_recomendada' => 'Ajustar bornas',
            ]
        ];

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => '2026-05-26',
                'cuadrilla' => 'Cuadrilla 1',
                'integrantes' => 'A. Perez',
                'reporte' => $reporte,
                'imagenes' => $imagenes,
                'novedades' => json_encode($novedades),
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inspecciones', [
            'elemento_id' => $elemento->id,
            'cuadrilla' => 'Cuadrilla 1',
        ]);

        $this->assertDatabaseHas('novedades', [
            'titulo' => 'Bushing sobrecalentado',
            'temperatura_detectada' => 65.5,
        ]);

        $inspeccionId = (int) $response->json('inspeccion.id');
        $reporteArchivo = \DB::table('archivos')->where('tipo', 'informe_word')->first();
        $imagenesArchivo = \DB::table('archivos')->where('tipo', 'pack_imagenes_zip')->first();

        Storage::disk('s3')->assertExists($reporteArchivo->s3_key);
        Storage::disk('s3')->assertExists($imagenesArchivo->s3_key);
        $this->assertSame('termovault-dev', $reporteArchivo->s3_bucket);
        $this->assertSame("inspecciones/{$inspeccionId}/reports/{$reporteArchivo->id}-reporte.docx", $reporteArchivo->s3_key);
        $this->assertSame('termovault-dev', $imagenesArchivo->s3_bucket);
        $this->assertSame("inspecciones/{$inspeccionId}/images/{$imagenesArchivo->id}-imagenes.zip", $imagenesArchivo->s3_key);
    }

    public function test_cannot_upload_inspection_for_other_company_element(): void
    {
        config()->set('cognito.required', true);
        // Login as other company user
        $this->mockVerifier($this->otherEmpresaClaims);

        // This element belongs to PECOM (Alejandro's company)
        $elemento = Elemento::create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'Subestacion PECOM',
            'codigo' => 'SET-PECOM-SEC',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => '2026-05-26',
            ]);

        // Should fail due to multi-tenant scoping check in InspeccionController
        $response->assertStatus(422);
    }

    public function test_closing_inspection_resolves_open_findings(): void
    {
        config()->set('cognito.required', true);
        $this->mockVerifier($this->techClaims);

        $elemento = Elemento::create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'Subestacion Cierre',
            'codigo' => 'SET-CIERRE-TEST',
        ]);

        $createResponse = $this->withHeader('Authorization', 'Bearer valid-token')
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => '2026-05-26',
                'novedades' => json_encode([
                    [
                        'criticidad_id' => 3,
                        'titulo' => 'Punto caliente',
                        'descripcion' => 'Hallazgo inicial',
                    ],
                ]),
            ]);

        $createResponse->assertStatus(201);
        $inspeccionId = (int) $createResponse->json('inspeccion.id');

        $this->assertDatabaseHas('novedades', [
            'inspeccion_id' => $inspeccionId,
            'estado' => 'abierta',
        ]);

        $this->mockVerifier($this->adminClaims);
        $closeResponse = $this->withHeader('Authorization', 'Bearer valid-token')
            ->patchJson("/api/inspecciones/{$inspeccionId}/estado", [
                'estado' => 'cerrada',
            ]);

        $closeResponse->assertOk();
        $this->assertDatabaseHas('inspecciones', [
            'id' => $inspeccionId,
            'estado' => 'cerrada',
        ]);
        $this->assertDatabaseHas('novedades', [
            'inspeccion_id' => $inspeccionId,
            'estado' => 'resuelta',
        ]);
    }

    public function test_closing_by_another_supervisor_or_admin_keeps_reviewer_and_sets_closer(): void
    {
        config()->set('cognito.required', true);
        $this->mockVerifier($this->techClaims);

        $elemento = Elemento::create([
            'yacimiento_id' => $this->yacimiento->id,
            'tipo_elemento_id' => $this->tipo->id,
            'nombre' => 'Subestacion Trazabilidad',
            'codigo' => 'SET-TRAZA-TEST',
        ]);

        $createResponse = $this->withHeader('Authorization', 'Bearer valid-token')
            ->postJson('/api/inspecciones', [
                'elemento_id' => $elemento->id,
                'fecha_inspeccion' => '2026-05-26',
            ]);
        $createResponse->assertStatus(201);
        $inspeccionId = (int) $createResponse->json('inspeccion.id');

        $reviewerId = (int) \DB::table('usuarios')->where('email', 'admin@example.com')->value('id');
        $closerId = (int) \DB::table('usuarios')->where('email', 'admin2@example.com')->value('id');

        $this->mockVerifier($this->adminClaims);
        $reviewResponse = $this->withHeader('Authorization', 'Bearer valid-token')
            ->patchJson("/api/inspecciones/{$inspeccionId}/estado", [
                'estado' => 'revisada',
            ]);
        $reviewResponse->assertOk();

        $this->mockVerifier($this->admin2Claims);
        $closeResponse = $this->withHeader('Authorization', 'Bearer valid-token')
            ->patchJson("/api/inspecciones/{$inspeccionId}/estado", [
                'estado' => 'cerrada',
            ]);
        $closeResponse->assertOk();

        $this->assertDatabaseHas('inspecciones', [
            'id' => $inspeccionId,
            'estado' => 'cerrada',
            'revisada_por' => $reviewerId,
            'cerrada_por' => $closerId,
        ]);
    }
}
