<?php

namespace Tests\Feature\Api\Financiero;

use App\Models\Financiero\Descuento\Descuento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del sub-módulo Sobrecargo: CRUD, validaciones y consulta por medio de pago.
 */
class SobrecargoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'fin_descuentos',         'descripcion' => 'ver descuentos']);
        Permission::create(['name' => 'fin_descuentoCrear',     'descripcion' => 'crear descuento']);
        Permission::create(['name' => 'fin_descuentoEditar',    'descripcion' => 'editar descuento']);
        Permission::create(['name' => 'fin_descuentoInactivar', 'descripcion' => 'inactivar descuento']);
        Permission::create(['name' => 'fin_descuentoAprobar',   'descripcion' => 'aprobar descuento']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'fin_descuentos',
            'fin_descuentoCrear',
            'fin_descuentoEditar',
            'fin_descuentoInactivar',
            'fin_descuentoAprobar',
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function datosSobrecargo(array $overrides = []): array
    {
        return array_merge([
            'tipo_movimiento'  => Descuento::MOVIMIENTO_SOBRECARGO,
            'nombre'           => 'Recargo tarjeta crédito',
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 3.5,
            'aplicacion'       => Descuento::APLICACION_VALOR_RECIBO,
            'tipo_activacion'  => Descuento::ACTIVACION_MEDIO_PAGO,
            'medios_pago'      => ['tarjeta_credito'],
            'permite_acumulacion' => false,
            'fecha_inicio'     => now()->toDateString(),
            'fecha_fin'        => now()->addMonths(6)->toDateString(),
        ], $overrides);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function crea_un_sobrecargo_por_medio_de_pago(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosSobrecargo());

        $response->assertCreated()
            ->assertJsonPath('data.tipo_movimiento', Descuento::MOVIMIENTO_SOBRECARGO)
            ->assertJsonPath('data.es_sobrecargo', true)
            ->assertJsonPath('data.nombre', 'Recargo tarjeta crédito');

        $this->assertDatabaseHas('descuentos', [
            'nombre'          => 'Recargo tarjeta crédito',
            'tipo_movimiento' => Descuento::MOVIMIENTO_SOBRECARGO,
        ]);
    }

    /** @test */
    public function crea_un_sobrecargo_de_mora_automatica(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosSobrecargo([
                'nombre'          => 'Mora diaria',
                'valor'           => 0.5,
                'aplicacion'      => Descuento::APLICACION_SALDO_CARTERA,
                'tipo_activacion' => Descuento::ACTIVACION_MORA_AUTOMATICA,
                'medios_pago'     => null,
            ]));

        $response->assertCreated()
            ->assertJsonPath('data.tipo_movimiento', Descuento::MOVIMIENTO_SOBRECARGO)
            ->assertJsonPath('data.tipo_activacion', Descuento::ACTIVACION_MORA_AUTOMATICA);
    }

    /** @test */
    public function crea_sobrecargo_con_marca_tarjeta_especifica(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosSobrecargo([
                'marca_tarjeta' => ['Visa', 'Mastercard'],
            ]));

        $response->assertCreated()
            ->assertJsonPath('data.marca_tarjeta', ['Visa', 'Mastercard']);
    }

    // ─── Validaciones específicas de sobrecargo ────────────────────────────────

    /** @test */
    public function rechaza_sobrecargo_de_valor_fijo(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosSobrecargo([
                'tipo' => Descuento::TIPO_VALOR_FIJO,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo']);
    }

    /** @test */
    public function rechaza_sobrecargo_con_acumulacion(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosSobrecargo([
                'permite_acumulacion' => true,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permite_acumulacion']);
    }

    /** @test */
    public function rechaza_sobrecargo_con_aplicacion_incorrecta_para_mora(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosSobrecargo([
                'tipo_activacion' => Descuento::ACTIVACION_MORA_AUTOMATICA,
                'aplicacion'      => Descuento::APLICACION_VALOR_RECIBO, // debe ser saldo_cartera
                'medios_pago'     => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['aplicacion']);
    }

    /** @test */
    public function rechaza_sobrecargo_por_medio_de_pago_sin_medios_pago(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosSobrecargo([
                'medios_pago' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['medios_pago']);
    }

    /** @test */
    public function rechaza_sobrecargo_con_tipo_activacion_de_descuento(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosSobrecargo([
                'tipo_activacion' => Descuento::ACTIVACION_PAGO_ANTICIPADO,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_activacion']);
    }

    /** @test */
    public function rechaza_sobrecargo_con_aplicacion_de_descuento(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosSobrecargo([
                'aplicacion' => Descuento::APLICACION_CUOTA,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['aplicacion']);
    }

    // ─── index con filtro tipo_movimiento ──────────────────────────────────────

    /** @test */
    public function filtra_por_tipo_movimiento_sobrecargo(): void
    {
        Descuento::factory()->enProceso()->pagoAnticipado()->create(['tipo_movimiento' => Descuento::MOVIMIENTO_DESCUENTO]);
        Descuento::factory()->enProceso()->sobrecargoPorMedioPago()->create();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('descuentos.index', ['tipo_movimiento' => Descuento::MOVIMIENTO_SOBRECARGO]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(Descuento::MOVIMIENTO_SOBRECARGO, $data[0]['tipo_movimiento']);
    }

    // ─── sobrecargoPorMedioPago ────────────────────────────────────────────────

    /** @test */
    public function consulta_sobrecargos_por_medio_de_pago(): void
    {
        // Crear sobrecargo activo y vigente para tarjeta_credito
        Descuento::factory()
            ->activo()
            ->vigente()
            ->sobrecargoPorMedioPago(['tarjeta_credito'])
            ->create(['nombre' => 'Recargo Visa']);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('descuentos.sobrecargos.por-medio-pago', [
                'medio_pago' => 'tarjeta_credito',
                'valor_base' => 100000,
            ]));

        $response->assertOk()
            ->assertJsonStructure(['data' => [['descuento_id', 'nombre', 'porcentaje', 'valor_sobrecargo', 'valor_final']]]);
    }

    /** @test */
    public function no_retorna_sobrecargos_para_efectivo_si_solo_aplica_a_tarjeta(): void
    {
        Descuento::factory()
            ->activo()
            ->vigente()
            ->sobrecargoPorMedioPago(['tarjeta_credito'])
            ->create();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('descuentos.sobrecargos.por-medio-pago', [
                'medio_pago' => 'efectivo',
                'valor_base' => 100000,
            ]));

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    /** @test */
    public function valida_campos_requeridos_en_sobrecargo_por_medio_pago(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('descuentos.sobrecargos.por-medio-pago'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['medio_pago', 'valor_base']);
    }

    /** @test */
    public function rechaza_medio_pago_invalido_en_consulta_sobrecargos(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('descuentos.sobrecargos.por-medio-pago', [
                'medio_pago' => 'criptomoneda',
                'valor_base' => 100000,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['medio_pago']);
    }
}
