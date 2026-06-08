<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Usuario;
use App\Models\Yacimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * FIX [002] — Auditoría de cambios de usuario (PUT /admin/usuarios/{id}).
 *
 * Verifica que las escaladas de privilegio, reactivaciones y creación de admins
 * emiten una alerta de seguridad (audit.alert / WARNING), además del registro
 * enriquecido con estado previo y actual.
 */
class AdminUserAuditTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Yacimiento $yacimiento;

    protected function setUp(): void
    {
        parent::setUp();

        // El endpoint resuelve el rol por código contra la tabla `roles`;
        // garantizamos que los tres roles existan independientemente de qué
        // estado de factory se use en cada test.
        foreach (['admin', 'supervisor', 'tecnico'] as $code) {
            \App\Models\Role::firstOrCreate(['codigo' => $code], ['nombre' => $code]);
        }

        $this->empresa = Empresa::factory()->create(['nombre' => 'PAE']);
        $this->yacimiento = Yacimiento::factory()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'YAC-PAE',
        ]);
    }

    private function admin(): Usuario
    {
        return Usuario::factory()->admin()->create(['empresa_id' => $this->empresa->id]);
    }

    private function payloadFor(Usuario $target, string $rolCodigo, bool $activo = true): array
    {
        return [
            'nombre' => $target->nombre,
            'apellido' => $target->apellido,
            'email' => $target->email,
            'empresa_id' => $this->empresa->id,
            'rol_codigo' => $rolCodigo,
            'yacimientos' => [$this->yacimiento->id],
            'activo' => $activo,
        ];
    }

    public function test_role_escalation_emits_security_alert(): void
    {
        Log::spy();
        $admin = $this->admin();
        $target = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);

        $response = $this->actingAs($admin)
            ->putJson("/api/admin/usuarios/{$target->id}", $this->payloadFor($target, 'admin'));

        $response->assertOk();
        $this->assertSame('admin', $response->json('rol'));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => $message === 'audit.alert'
                && ($context['event'] ?? null) === 'usuario.privilege_escalated'
                && ($context['from_rol'] ?? null) === 'tecnico'
                && ($context['to_rol'] ?? null) === 'admin'
                && (int) ($context['usuario_id'] ?? 0) === $target->id)
            ->once();
    }

    public function test_role_de_escalation_does_not_emit_escalation_alert(): void
    {
        Log::spy();
        $admin = $this->admin();
        $target = Usuario::factory()->supervisor()->create(['empresa_id' => $this->empresa->id]);

        $response = $this->actingAs($admin)
            ->putJson("/api/admin/usuarios/{$target->id}", $this->payloadFor($target, 'tecnico'));

        $response->assertOk();
        $this->assertSame('tecnico', $response->json('rol'));

        // Bajar de supervisor → tecnico no es escalada: no debe emitirse
        // ninguna alerta de seguridad (WARNING).
        Log::shouldNotHaveReceived('warning');
    }

    public function test_update_records_enriched_before_after_audit(): void
    {
        Log::spy();
        $admin = $this->admin();
        $target = Usuario::factory()->tecnico()->create(['empresa_id' => $this->empresa->id]);

        $this->actingAs($admin)
            ->putJson("/api/admin/usuarios/{$target->id}", $this->payloadFor($target, 'supervisor'))
            ->assertOk();

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context = []) use ($target) {
                return $message === 'audit.trail'
                    && ($context['event'] ?? null) === 'usuario.updated'
                    && (int) ($context['usuario_id'] ?? 0) === $target->id
                    && ($context['previous']['rol'] ?? null) === 'tecnico'
                    && ($context['current']['rol'] ?? null) === 'supervisor';
            })
            ->once();
    }

    public function test_reactivation_emits_security_alert(): void
    {
        Log::spy();
        $admin = $this->admin();
        $target = Usuario::factory()->tecnico()->create([
            'empresa_id' => $this->empresa->id,
            'activo' => false,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/admin/usuarios/{$target->id}", $this->payloadFor($target, 'tecnico', true))
            ->assertOk();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => $message === 'audit.alert'
                && ($context['event'] ?? null) === 'usuario.reactivated'
                && (int) ($context['usuario_id'] ?? 0) === $target->id)
            ->once();
    }

    public function test_creating_admin_emits_security_alert(): void
    {
        Log::spy();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/usuarios', [
                'nombre' => 'Nuevo',
                'apellido' => 'Admin',
                'email' => 'nuevo.admin@example.com',
                'empresa_id' => $this->empresa->id,
                'rol_codigo' => 'admin',
                'yacimientos' => [$this->yacimiento->id],
            ])
            ->assertCreated();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => $message === 'audit.alert'
                && ($context['event'] ?? null) === 'usuario.created_with_admin_privilege')
            ->once();
    }

    public function test_creating_tecnico_does_not_emit_admin_alert(): void
    {
        Log::spy();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/usuarios', [
                'nombre' => 'Nuevo',
                'apellido' => 'Tecnico',
                'email' => 'nuevo.tecnico@example.com',
                'empresa_id' => $this->empresa->id,
                'rol_codigo' => 'tecnico',
                'yacimientos' => [$this->yacimiento->id],
            ])
            ->assertCreated();

        // Crear un técnico no es un evento sensible: sin alerta de seguridad.
        Log::shouldNotHaveReceived('warning');
    }
}
