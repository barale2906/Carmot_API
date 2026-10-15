<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Permisos base por rol de una instalación limpia.
 */
class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function el_cajero_puede_cobrar_recibos_y_ver_la_cartera(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cajero = Role::findByName('cajero');

        foreach (['fin_recibos_pago', 'fin_reciboPagoCrear', 'fin_reciboPagoPDF', 'fin_carteras', 'fin_descuentoAplicar', 'co_bancos', 'fin_conceptos_pago'] as $permiso) {
            $this->assertTrue($cajero->hasPermissionTo($permiso), "cajero sin {$permiso}");
        }
        // Anular y aprobar siguen siendo de supervisión
        $this->assertFalse($cajero->hasPermissionTo('fin_reciboPagoAnular'));
        $this->assertFalse($cajero->hasPermissionTo('fin_reciboPagoAprobar'));
    }

    /** @test */
    public function solo_el_superusuario_crea_o_elimina_sedes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['co_sedeCrear', 'co_sedeInactivar'] as $permiso) {
            $this->assertTrue(Role::findByName('superusuario')->hasPermissionTo($permiso));
            $this->assertFalse(Role::findByName('coordinador')->hasPermissionTo($permiso));
            $this->assertFalse(Role::findByName('financiero')->hasPermissionTo($permiso));
        }
    }
}
