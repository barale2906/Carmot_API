<?php

namespace Tests\Feature\Api\Financiero\Lp;

use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del cálculo de valor_cuota en LpPrecioProducto.
 *
 * precio_total ya representa el saldo a financiar, neto de matrícula
 * (precio_contado = matricula + precio_total). Por eso valor_cuota debe
 * calcularse como precio_total / numero_cuotas, sin restar matricula otra vez.
 */
class LpPrecioProductoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'fin_lp_precioProductoCrear', 'descripcion' => 'crear precios producto']);
        Permission::create(['name' => 'fin_lp_precioProductoEditar', 'descripcion' => 'editar precios producto']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['fin_lp_precioProductoCrear', 'fin_lp_precioProductoEditar']);
    }

    private function listaPrecio(): LpListaPrecio
    {
        return LpListaPrecio::factory()->enProceso()->create();
    }

    private function productoFinanciable(): LpProducto
    {
        return LpProducto::factory()->activo()->curso()->create();
    }

    private function productoNoFinanciable(): LpProducto
    {
        return LpProducto::factory()->activo()->complementario()->create();
    }

    /** @test */
    public function al_crear_calcula_valor_cuota_sin_restar_dos_veces_la_matricula(): void
    {
        $lista = $this->listaPrecio();
        $producto = $this->productoFinanciable();

        $response = $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => $lista->id,
            'producto_id' => $producto->id,
            'precio_contado' => 5000000,
            'matricula' => 150000,
            'precio_total' => 4850000,
            'numero_cuotas' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.valor_cuota', 485000);

        $this->assertDatabaseHas('lp_precios_producto', [
            'id' => $response->json('data.id'),
            'valor_cuota' => 485000.00,
        ]);
    }

    /** @test */
    public function al_actualizar_recalcula_valor_cuota_sin_duplicar_el_descuento_de_matricula(): void
    {
        $precioProducto = LpPrecioProducto::factory()->create([
            'lista_precio_id' => $this->listaPrecio()->id,
            'producto_id' => $this->productoFinanciable()->id,
            'precio_contado' => 5000000,
            'matricula' => 150000,
            'precio_total' => 4850000,
            'numero_cuotas' => 10,
        ]);

        $response = $this->actingAs($this->usuario)->putJson(
            route('precios-producto.update', $precioProducto->id),
            ['observaciones' => 'Actualización de prueba']
        );

        $response->assertOk()->assertJsonPath('data.valor_cuota', 485000);

        $this->assertDatabaseHas('lp_precios_producto', [
            'id' => $precioProducto->id,
            'valor_cuota' => 485000.00,
        ]);
    }

    /** @test */
    public function redondea_el_valor_de_la_cuota_al_100_mas_cercano(): void
    {
        $lista = $this->listaPrecio();
        $producto = $this->productoFinanciable();

        // 3.380.000 / 6 = 563.333,33 -> redondeado al 100: 563.300
        $response = $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => $lista->id,
            'producto_id' => $producto->id,
            'precio_contado' => 3500000,
            'matricula' => 120000,
            'precio_total' => 3380000,
            'numero_cuotas' => 6,
        ]);

        $response->assertCreated()->assertJsonPath('data.valor_cuota', 563300);
    }

    /** @test */
    public function producto_no_financiable_no_calcula_valor_cuota(): void
    {
        $lista = $this->listaPrecio();
        $producto = $this->productoNoFinanciable();

        $response = $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => $lista->id,
            'producto_id' => $producto->id,
            'precio_contado' => 50000,
            'matricula' => 0,
        ]);

        $response->assertCreated()->assertJsonPath('data.valor_cuota', null);

        $this->assertDatabaseHas('lp_precios_producto', [
            'id' => $response->json('data.id'),
            'valor_cuota' => null,
        ]);
    }
}
