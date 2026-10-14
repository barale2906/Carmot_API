<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Opciones del menú para Leads (CRM), Asistencia, Calificaciones y Activos fijos.
 */
class MenuCrmAcademicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['crm_referidos', 'aca_asistencias', 'aca_notas', 'act_activos', 'act_categorias'] as $nombre) {
            Permission::create(['name' => $nombre, 'descripcion' => $nombre]);
        }
    }

    /**
     * IDs de las opciones hijas de un módulo del menú.
     *
     * @return array<int, string>
     */
    private function opciones(User $user, string $modulo): array
    {
        $nodo = collect($this->actingAs($user)->getJson('/api/menu')->assertOk()->json('data'))->firstWhere('id', $modulo);

        return collect($nodo['children'] ?? [])->pluck('id')->all();
    }

    /** @test */
    public function el_permiso_de_leads_basta_para_ver_el_crm(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('crm_referidos');

        $this->assertSame(['crm-referidos'], $this->opciones($user, 'crm'));
    }

    /** @test */
    public function asistencia_y_calificaciones_aparecen_en_academico_segun_permiso(): void
    {
        $profesor = User::factory()->create();
        $profesor->givePermissionTo(['aca_asistencias', 'aca_notas']);

        $this->assertSame(['academico-asistencia', 'academico-calificaciones'], $this->opciones($profesor, 'academico'));

        $soloNotas = User::factory()->create();
        $soloNotas->givePermissionTo('aca_notas');
        $this->assertSame(['academico-calificaciones'], $this->opciones($soloNotas, 'academico'));
    }

    /** @test */
    public function el_modulo_de_activos_muestra_sus_opciones_segun_permiso(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('act_activos');
        $this->assertSame(['activos-inventario'], $this->opciones($user, 'activos'));

        $user->givePermissionTo('act_categorias');
        $this->assertSame(['activos-inventario', 'activos-categorias'], $this->opciones($user, 'activos'));
    }
}
