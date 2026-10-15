<?php

namespace Tests\Feature\Api\Financiero\Lp;

use App\Models\Academico\Curso;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\Financiero\Lp\LpProductoReferencia;
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
    public function no_se_agregan_ni_editan_precios_en_una_lista_aprobada_o_activa(): void
    {
        $producto = $this->productoNoFinanciable();

        foreach ([LpListaPrecio::STATUS_APROBADA, LpListaPrecio::STATUS_ACTIVA] as $status) {
            $lista = LpListaPrecio::factory()->create(['status' => $status]);

            $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
                'lista_precio_id' => $lista->id,
                'producto_id' => $producto->id,
                'precio_contado' => 100000,
                'matricula' => 0,
            ])
                ->assertUnprocessable()
                ->assertJsonFragment([LpListaPrecio::MENSAJE_PRECIOS_BLOQUEADOS]);
        }

        $id = $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => ($lista = $this->listaPrecio())->id,
            'producto_id' => $producto->id,
            'precio_contado' => 100000,
            'matricula' => 0,
        ])->assertCreated()->json('data.id');

        $lista->update(['status' => LpListaPrecio::STATUS_ACTIVA]);

        $this->actingAs($this->usuario)->putJson(route('precios-producto.update', $id), ['precio_contado' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lista_precio_id');
    }

    /** @test */
    public function rechaza_un_segundo_precio_del_mismo_producto_en_la_lista(): void
    {
        // N04 (prueba integral 2): respondía 500 por el índice único uk_lista_producto
        $lista = $this->listaPrecio();
        $datos = [
            'lista_precio_id' => $lista->id,
            'producto_id' => $this->productoNoFinanciable()->id,
            'precio_contado' => 100000,
            'matricula' => 0,
        ];

        $id = $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), $datos)
            ->assertCreated()->json('data.id');

        $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('producto_id');

        // También cuenta el precio eliminado, que sigue ocupando el índice
        LpPrecioProducto::findOrFail($id)->delete();
        $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('producto_id');

        $this->assertSame(1, LpPrecioProducto::withTrashed()->where('lista_precio_id', $lista->id)->count());
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
    public function permite_cargar_el_valor_de_cada_cuota_de_forma_individual(): void
    {
        $response = $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => $this->listaPrecio()->id,
            'producto_id' => $this->productoFinanciable()->id,
            'precio_contado' => 600,
            'matricula' => 0,
            'precio_total' => 600,
            'numero_cuotas' => 3,
            'cuotas_detalle' => [150, 100, 350],
        ]);

        $response->assertCreated()->assertJsonPath('data.cuotas_detalle', [150, 100, 350]);
        $this->assertSame(100, (int) $response->json('data.cuotas_detalle.1'));
    }

    /** @test */
    public function rechaza_cuotas_individuales_que_no_suman_el_precio_total(): void
    {
        $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => $this->listaPrecio()->id,
            'producto_id' => $this->productoFinanciable()->id,
            'precio_contado' => 600,
            'matricula' => 0,
            'precio_total' => 600,
            'numero_cuotas' => 3,
            'cuotas_detalle' => [150, 100, 300],
        ])->assertUnprocessable()->assertJsonValidationErrors('cuotas_detalle');
    }

    /** @test */
    public function rechaza_cuotas_individuales_con_cantidad_distinta_al_numero_de_cuotas(): void
    {
        $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => $this->listaPrecio()->id,
            'producto_id' => $this->productoFinanciable()->id,
            'precio_contado' => 600,
            'matricula' => 0,
            'precio_total' => 600,
            'numero_cuotas' => 3,
            'cuotas_detalle' => [300, 300],
        ])->assertUnprocessable()->assertJsonValidationErrors('cuotas_detalle');
    }

    /** @test */
    public function rechaza_cuotas_individuales_en_producto_no_financiable(): void
    {
        $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => $this->listaPrecio()->id,
            'producto_id' => $this->productoNoFinanciable()->id,
            'precio_contado' => 600,
            'matricula' => 0,
            'cuotas_detalle' => [300, 300],
        ])->assertUnprocessable();
    }

    /** @test */
    public function al_actualizar_puede_volver_al_calculo_automatico_enviando_null(): void
    {
        $lista = $this->listaPrecio();
        $id = $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => $lista->id,
            'producto_id' => $this->productoFinanciable()->id,
            'precio_contado' => 600,
            'matricula' => 0,
            'precio_total' => 600,
            'numero_cuotas' => 3,
            'cuotas_detalle' => [150, 100, 350],
        ])->assertCreated()->json('data.id');

        // Cambiar el número de cuotas sin reenviar el detalle invalida el detalle previo
        $this->actingAs($this->usuario)->putJson(route('precios-producto.update', $id), ['numero_cuotas' => 4])
            ->assertUnprocessable()->assertJsonValidationErrors('cuotas_detalle');

        $this->actingAs($this->usuario)->putJson(route('precios-producto.update', $id), [
            'numero_cuotas' => 4,
            'cuotas_detalle' => null,
        ])->assertOk()
            ->assertJsonPath('data.cuotas_detalle', null)
            ->assertJsonPath('data.valor_cuota', 200);
    }

    /** @test */
    public function el_metodo_automatico_sigue_funcionando_sin_cuotas_detalle(): void
    {
        $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => $this->listaPrecio()->id,
            'producto_id' => $this->productoFinanciable()->id,
            'precio_contado' => 600,
            'matricula' => 0,
            'precio_total' => 600,
            'numero_cuotas' => 3,
        ])->assertCreated()
            ->assertJsonPath('data.cuotas_detalle', null)
            ->assertJsonPath('data.valor_cuota', 200);
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

    /** @test */
    public function al_crear_con_referencia_guarda_vinculo_en_lp_producto_referencias(): void
    {
        $lista   = $this->listaPrecio();
        $producto = $this->productoFinanciable();
        $curso   = Curso::factory()->create();

        $response = $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id'  => $lista->id,
            'producto_id'      => $producto->id,
            'precio_contado'   => 5000000,
            'matricula'        => 150000,
            'precio_total'     => 4850000,
            'numero_cuotas'    => 10,
            'referencia_id'    => $curso->id,
            'referencia_tipo'  => 'curso',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('lp_producto_referencias', [
            'lp_producto_id'  => $producto->id,
            'referencia_id'   => $curso->id,
            'referencia_tipo' => 'curso',
        ]);
    }

    /** @test */
    public function al_crear_con_referencia_duplicada_no_genera_error_ni_duplicado(): void
    {
        $lista    = $this->listaPrecio();
        $producto = $this->productoFinanciable();
        $curso    = Curso::factory()->create();

        // Referencia ya existe
        LpProductoReferencia::create([
            'lp_producto_id'  => $producto->id,
            'referencia_id'   => $curso->id,
            'referencia_tipo' => 'curso',
        ]);

        $response = $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id'  => $lista->id,
            'producto_id'      => $producto->id,
            'precio_contado'   => 5000000,
            'matricula'        => 150000,
            'precio_total'     => 4850000,
            'numero_cuotas'    => 10,
            'referencia_id'    => $curso->id,
            'referencia_tipo'  => 'curso',
        ]);

        $response->assertCreated();

        $this->assertSame(
            1,
            LpProductoReferencia::where('lp_producto_id', $producto->id)->count(),
            'No debe duplicar la referencia si ya existe.'
        );
    }

    /** @test */
    public function al_crear_sin_referencia_no_genera_vinculo(): void
    {
        $lista    = $this->listaPrecio();
        $producto = $this->productoFinanciable();

        $response = $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => $lista->id,
            'producto_id'     => $producto->id,
            'precio_contado'  => 5000000,
            'matricula'       => 150000,
            'precio_total'    => 4850000,
            'numero_cuotas'   => 10,
        ]);

        $response->assertCreated();

        $this->assertDatabaseMissing('lp_producto_referencias', [
            'lp_producto_id' => $producto->id,
        ]);
    }

    /** @test */
    public function rechaza_referencia_con_curso_inexistente(): void
    {
        $lista    = $this->listaPrecio();
        $producto = $this->productoFinanciable();

        $response = $this->actingAs($this->usuario)->postJson(route('precios-producto.store'), [
            'lista_precio_id' => $lista->id,
            'producto_id'     => $producto->id,
            'precio_contado'  => 5000000,
            'matricula'       => 150000,
            'precio_total'    => 4850000,
            'numero_cuotas'   => 10,
            'referencia_id'   => 9999,
            'referencia_tipo' => 'curso',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['referencia_id']);
    }
}
