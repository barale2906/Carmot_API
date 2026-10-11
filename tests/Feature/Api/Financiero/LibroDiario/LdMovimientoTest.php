<?php

namespace Tests\Feature\Api\Financiero\LibroDiario;

use App\Models\Configuracion\Banco;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\LibroDiario\LdImpuesto;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\Financiero\LibroDiario\LdTurnoEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de movimientos manuales del libro diario: registro con impuestos,
 * validación del total, edición en turno abierto y anulación (incluso tras el cierre).
 */
class LdMovimientoTest extends TestCase
{
    use PreparaLibroDiario, RefreshDatabase;

    private User $cajero;

    private LdTurnoCaja $turno;

    private LdTipoMovimiento $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararLibroDiario();
        $this->cajero = $this->usuarioCon(['fin_libroDiario', 'fin_ldRegistroCrear', 'fin_ldRegistroEditar', 'fin_ldTurnoCaja']);
        $this->turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $this->tipo = LdTipoMovimiento::factory()->create(['nombre' => 'Arriendo']);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'tipo_movimiento_id' => $this->tipo->id,
            'tercero_tipo_identificacion' => 'NIT',
            'tercero_identificacion' => '900123456',
            'tercero_nombre' => 'Inmobiliaria Central',
            'documento_tipo' => 'factura',
            'documento_numero' => 'FE-1001',
            'subtotal' => 100000,
            'impuestos' => [],
            'valor_total' => 100000,
            'medio_pago' => 'efectivo',
        ], $override);
    }

    /** @test */
    public function registra_egreso_con_iva_y_retencion(): void
    {
        $iva = LdImpuesto::factory()->create(['porcentaje' => 19]);
        $rete = LdImpuesto::factory()->retencion(4)->create();

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload([
                'impuestos' => [
                    ['impuesto_id' => $iva->id, 'porcentaje' => 19, 'valor' => 19000],
                    ['impuesto_id' => $rete->id, 'porcentaje' => 4, 'valor' => 4000],
                ],
                'valor_total' => 115000,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.clase', LdTipoMovimiento::CLASE_EGRESO)
            ->assertJsonPath('data.total_impuestos', 19000)
            ->assertJsonPath('data.total_retenciones', 4000)
            ->assertJsonPath('data.valor_total', 115000)
            ->assertJsonPath('data.turno_caja_id', $this->turno->id)
            ->assertJsonPath('data.sede_id', $this->sede->id)
            ->assertJsonCount(2, 'data.impuestos');

        $this->assertDatabaseHas('ld_movimiento_impuesto', ['impuesto_id' => $iva->id, 'nombre' => $iva->nombre, 'valor' => 19000]);
    }

    /** @test */
    public function rechaza_total_que_no_cuadra(): void
    {
        $iva = LdImpuesto::factory()->create();

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload([
                'impuestos' => [['impuesto_id' => $iva->id, 'porcentaje' => 19, 'valor' => 19000]],
                'valor_total' => 100000,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valor_total');
    }

    /** @test */
    public function valida_campos_obligatorios_impuesto_repetido_y_tipo_inactivo(): void
    {
        $iva = LdImpuesto::factory()->create();
        $inactivo = LdTipoMovimiento::factory()->inactivo()->create();

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), [])
            ->assertJsonValidationErrors(['tipo_movimiento_id', 'tercero_nombre', 'tercero_identificacion', 'documento_tipo', 'subtotal', 'valor_total']);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload([
                'tipo_movimiento_id' => $inactivo->id,
                'impuestos' => [
                    ['impuesto_id' => $iva->id, 'porcentaje' => 19, 'valor' => 0],
                    ['impuesto_id' => $iva->id, 'porcentaje' => 19, 'valor' => 0],
                ],
            ]))
            ->assertJsonValidationErrors(['tipo_movimiento_id', 'impuestos.0.impuesto_id']);
    }

    /** @test */
    public function sin_medio_de_pago_se_registra_en_efectivo(): void
    {
        $payload = $this->payload();
        unset($payload['medio_pago']);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.medio_pago', 'efectivo');
    }

    /** @test */
    public function el_encargado_de_caja_solo_registra_en_efectivo(): void
    {
        $banco = Banco::factory()->create();

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload(['medio_pago' => 'transferencia', 'banco_id' => $banco->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('medio_pago');

        $movimiento = LdMovimiento::factory()->create(['turno_caja_id' => $this->turno->id]);
        $this->actingAs($this->cajero)
            ->putJson(route('libro-diario.movimientos.update', $movimiento), $this->payload(['medio_pago' => 'tarjeta_debito']))
            ->assertJsonValidationErrors('medio_pago');
    }

    /** @test */
    public function con_permiso_registra_otros_medios_y_la_transferencia_exige_banco(): void
    {
        $this->cajero->givePermissionTo('fin_ldMedioPagoOtros');
        $banco = Banco::factory()->create();

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload(['medio_pago' => 'transferencia']))
            ->assertJsonValidationErrors('banco_id')
            ->assertJsonMissingValidationErrors('medio_pago');

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload(['medio_pago' => 'transferencia', 'banco_id' => $banco->id]))
            ->assertCreated()
            ->assertJsonPath('data.medio_pago', 'transferencia');
    }

    /** @test */
    public function sin_turno_abierto_no_registra(): void
    {
        $this->turno->update(['status' => LdTurnoCaja::STATUS_CERRADO]);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('turno');
    }

    /** @test */
    public function sin_permiso_no_registra(): void
    {
        $this->actingAs($this->usuarioCon(['fin_libroDiario']))
            ->postJson(route('libro-diario.movimientos.store'), $this->payload())
            ->assertForbidden();
    }

    /** @test */
    public function edita_movimiento_de_turno_abierto_y_reemplaza_impuestos(): void
    {
        $movimiento = LdMovimiento::factory()->create(['turno_caja_id' => $this->turno->id, 'tipo_movimiento_id' => $this->tipo->id]);
        $iva = LdImpuesto::factory()->create(['porcentaje' => 5]);

        $this->actingAs($this->cajero)
            ->putJson(route('libro-diario.movimientos.update', $movimiento), $this->payload([
                'subtotal' => 200000,
                'impuestos' => [['impuesto_id' => $iva->id, 'porcentaje' => 5, 'valor' => 10000]],
                'valor_total' => 210000,
            ]))
            ->assertOk()
            ->assertJsonPath('data.valor_total', 210000)
            ->assertJsonCount(1, 'data.impuestos');
    }

    /** @test */
    public function no_edita_movimiento_de_turno_cerrado(): void
    {
        $movimiento = LdMovimiento::factory()->create(['turno_caja_id' => $this->turno->id]);
        $this->turno->update(['status' => LdTurnoCaja::STATUS_CERRADO]);

        $this->actingAs($this->cajero)
            ->putJson(route('libro-diario.movimientos.update', $movimiento), $this->payload())
            ->assertUnprocessable();
    }

    /** @test */
    public function anula_movimiento_de_turno_abierto(): void
    {
        $movimiento = LdMovimiento::factory()->create(['turno_caja_id' => $this->turno->id]);
        $supervisor = $this->usuarioCon(['fin_ldRegistroAnular']);

        $this->actingAs($supervisor)
            ->postJson(route('libro-diario.movimientos.anular', $movimiento), ['motivo' => 'Registro duplicado'])
            ->assertOk()
            ->assertJsonPath('data.status', LdMovimiento::STATUS_ANULADO)
            ->assertJsonPath('data.motivo_anulacion', 'Registro duplicado');

        $this->actingAs($supervisor)
            ->postJson(route('libro-diario.movimientos.anular', $movimiento), ['motivo' => 'Otra vez'])
            ->assertUnprocessable();
    }

    /** @test */
    public function anular_en_turno_cerrado_exige_permiso_especial_y_ajusta_el_cierre(): void
    {
        $movimiento = LdMovimiento::factory()->create(['turno_caja_id' => $this->turno->id, 'valor_total' => 30000, 'subtotal' => 30000]);
        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.turnos.cerrar', $this->turno), ['efectivo_contado' => 70000])
            ->assertOk();
        $this->assertEquals(0, $this->turno->fresh()->diferencia);

        $this->actingAs($this->usuarioCon(['fin_ldRegistroAnular']))
            ->postJson(route('libro-diario.movimientos.anular', $movimiento), ['motivo' => 'Pago no realizado'])
            ->assertForbidden();

        $this->actingAs($this->usuarioCon(['fin_ldRegistroAnular', 'fin_ldAnularCerrado']))
            ->postJson(route('libro-diario.movimientos.anular', $movimiento), ['motivo' => 'Pago no realizado'])
            ->assertOk();

        $turno = $this->turno->fresh();
        $this->assertTrue($turno->ajustado);
        $this->assertEquals(100000, $turno->efectivo_esperado);
        $this->assertEquals(-30000, $turno->diferencia);
        $this->assertDatabaseHas('ld_turno_eventos', ['turno_caja_id' => $turno->id, 'accion' => LdTurnoEvento::ACCION_ANULACION]);
    }

    /** @test */
    public function lista_solo_movimientos_de_sedes_asignadas(): void
    {
        LdMovimiento::factory()->create(['turno_caja_id' => $this->turno->id]);
        $ajeno = LdMovimiento::factory()->create(['turno_caja_id' => LdTurnoCaja::factory()->create(['sede_id' => Sede::factory()])]);

        $this->actingAs($this->cajero)->getJson(route('libro-diario.movimientos.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->cajero)->getJson(route('libro-diario.movimientos.show', $ajeno))->assertForbidden();
    }
}
