<?php

namespace Tests\Feature\Seeders;

use App\Models\Financiero\ConceptoPago\ConceptoPago;
use App\Models\User;
use Database\Seeders\AddConceptoTrasladoSeeder;
use Database\Seeders\AddTrasladosPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Seeders del módulo de traslados: permisos por rol y concepto de pago con la tarifa del traslado en 0.
 */
class AddTrasladosSeedersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function los_permisos_se_reparten_y_el_pago_acordado_es_solo_del_superusuario(): void
    {
        $this->seed(AddTrasladosPermissionsSeeder::class);

        $superusuario = Role::findByName('superusuario');
        $coordinador = Role::findByName('coordinador');

        foreach (['tra_traslados', 'tra_trasladoCrear', 'tra_trasladoPagoAcordado', 'tra_trasladoNivelacion'] as $permiso) {
            $this->assertTrue($superusuario->hasPermissionTo($permiso), "superusuario sin {$permiso}");
        }
        foreach (['tra_traslados', 'tra_trasladoCrear', 'tra_trasladoNivelacion'] as $permiso) {
            $this->assertTrue($coordinador->hasPermissionTo($permiso), "coordinador sin {$permiso}");
        }
        $this->assertFalse($coordinador->hasPermissionTo('tra_trasladoPagoAcordado'));
    }

    /** @test */
    public function un_superusuario_de_instalacion_limpia_puede_listar_traslados(): void
    {
        $this->seed(AddTrasladosPermissionsSeeder::class);

        $usuario = User::factory()->create();
        $usuario->assignRole('superusuario');

        $this->actingAs($usuario, 'sanctum')->getJson('/api/traslados')->assertOk();
    }

    /** @test */
    public function el_concepto_traslado_nace_en_cero_y_no_pisa_una_tarifa_configurada(): void
    {
        $this->seed(AddConceptoTrasladoSeeder::class);

        $concepto = ConceptoPago::porNombre(ConceptoPago::TRASLADO);
        $this->assertNotNull($concepto);
        $this->assertEquals(0, (float) $concepto->valor);
        $this->assertSame(0, $concepto->tipo);

        $concepto->update(['valor' => 40000]);
        $this->seed(AddConceptoTrasladoSeeder::class);

        $this->assertSame(1, ConceptoPago::where('nombre', ConceptoPago::TRASLADO)->count());
        $this->assertEquals(40000, (float) $concepto->fresh()->valor);
    }

    /** @test */
    public function se_pueden_ejecutar_varias_veces_sin_duplicar_permisos(): void
    {
        $this->seed(AddTrasladosPermissionsSeeder::class);
        $this->seed(AddTrasladosPermissionsSeeder::class);

        $this->assertSame(4, Permission::where('name', 'like', 'tra_%')->count());
    }
}
