<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Pruebas del grupo "Facturación" del menú de Inventario.
 */
class InvMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['inv_ventas', 'fin_recibos_pago', 'fin_reciboPagoAprobar'] as $nombre) {
            Permission::create(['name' => $nombre, 'descripcion' => $nombre]);
        }
    }

    /**
     * IDs de todas las opciones del módulo Inventario visibles para el usuario.
     *
     * @param User $user
     * @return array<int, string>
     */
    private function opcionesInventario(User $user): array
    {
        $inventario = collect($this->actingAs($user)->getJson('/api/menu')->assertOk()->json('data'))
            ->firstWhere('id', 'inventario');

        $ids   = [];
        $pila  = $inventario['children'] ?? [];
        while ($nodo = array_pop($pila)) {
            $ids[] = $nodo['id'];
            array_push($pila, ...($nodo['children'] ?? []));
        }

        return $ids;
    }

    /** @test */
    public function muestra_recibos_y_transferencias_con_sus_permisos(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['inv_ventas', 'fin_recibos_pago', 'fin_reciboPagoAprobar']);

        $opciones = $this->opcionesInventario($user);

        $this->assertContains('inv-grupo-facturacion', $opciones);
        $this->assertContains('inv-recibos-pago', $opciones);
        $this->assertContains('inv-transferencias', $opciones);
    }

    /** @test */
    public function oculta_el_grupo_sin_permisos_financieros(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inv_ventas');

        $opciones = $this->opcionesInventario($user);

        $this->assertContains('inv-ventas', $opciones);
        $this->assertNotContains('inv-grupo-facturacion', $opciones);
        $this->assertNotContains('inv-recibos-pago', $opciones);
    }

    /** @test */
    public function muestra_solo_recibos_sin_permiso_de_aprobar(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['inv_ventas', 'fin_recibos_pago']);

        $opciones = $this->opcionesInventario($user);

        $this->assertContains('inv-recibos-pago', $opciones);
        $this->assertNotContains('inv-transferencias', $opciones);
    }
}
