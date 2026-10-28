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
        foreach (['crm_referidos', 'aca_asistencias', 'aca_notas', 'act_activos', 'act_categorias', 'act_activoRecibir', 'ac_biblioteca', 'co_roles'] as $nombre) {
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
    public function el_alumno_no_ve_asistencia_ni_calificaciones_aunque_tenga_los_permisos_de_consulta(): void
    {
        \Spatie\Permission\Models\Role::create(['name' => User::ROL_ALUMNO])->givePermissionTo(['aca_asistencias', 'aca_notas']);
        $alumno = User::factory()->create();
        $alumno->assignRole(User::ROL_ALUMNO);

        $this->assertSame([], $this->opciones($alumno, 'academico'));
    }

    /** @test */
    public function quien_solo_consulta_la_biblioteca_la_ve_en_academico(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('ac_biblioteca');

        $this->assertSame(['academico-biblioteca'], $this->opciones($user, 'academico'));
    }

    /** @test */
    public function roles_y_permisos_aparece_con_el_permiso_co_roles(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('co_roles');

        $this->assertSame(['configuracion-roles-permisos'], $this->opciones($user, 'configuracion'));
    }

    /** @test */
    public function el_modulo_de_activos_muestra_sus_opciones_segun_permiso(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('act_activos');
        $this->assertSame(['activos-inventario', 'activos-traslados'], $this->opciones($user, 'activos'));

        $user->givePermissionTo('act_categorias');
        $this->assertSame(['activos-inventario', 'activos-traslados', 'activos-categorias'], $this->opciones($user, 'activos'));

        // Quien solo recibe traslados ve únicamente Traslados
        $receptor = User::factory()->create();
        $receptor->givePermissionTo('act_activoRecibir');
        $this->assertSame(['activos-traslados'], $this->opciones($receptor, 'activos'));
    }
}
