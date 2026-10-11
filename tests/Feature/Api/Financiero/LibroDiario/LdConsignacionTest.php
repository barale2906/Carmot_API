<?php

namespace Tests\Feature\Api\Financiero\LibroDiario;

use App\Models\Configuracion\Banco;
use App\Models\Financiero\LibroDiario\LdConsignacion;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de consignaciones de efectivo: registro, tope por efectivo disponible,
 * edición y anulación.
 */
class LdConsignacionTest extends TestCase
{
    use PreparaLibroDiario, RefreshDatabase;

    private User $cajero;

    private LdTurnoCaja $turno;

    private Banco $banco;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararLibroDiario();
        $this->cajero = $this->usuarioCon(['fin_libroDiario', 'fin_ldRegistroCrear', 'fin_ldRegistroEditar', 'fin_ldRegistroAnular', 'fin_ldTurnoCaja']);
        $this->turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id, 'base_inicial' => 50000]);
        $this->banco = Banco::factory()->create();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'banco_id' => $this->banco->id,
            'valor' => 120000,
            'titular' => 'Instituto Carmot SAS',
            'numero_cuenta' => '123-456789-01',
            'numero_comprobante' => '000998877',
        ], $override);
    }

    /** @test */
    public function registra_consignacion_del_efectivo_recibido(): void
    {
        $this->reciboPagado(100000, 'efectivo', ['turno_caja_id' => $this->turno->id]);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.consignaciones.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.titular', 'Instituto Carmot SAS')
            ->assertJsonPath('data.banco.id', $this->banco->id)
            ->assertJsonPath('data.turno_caja_id', $this->turno->id);

        $this->actingAs($this->usuarioCon(['fin_ldTurnos']))->getJson(route('libro-diario.turnos.show', $this->turno))
            ->assertOk()
            ->assertJsonPath('data.resumen.efectivo_esperado', 30000);
    }

    /** @test */
    public function no_consigna_mas_del_efectivo_disponible(): void
    {
        $this->reciboPagado(100000, 'tarjeta_credito', ['turno_caja_id' => $this->turno->id]);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.consignaciones.store'), $this->payload(['valor' => 60000]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valor');
    }

    /** @test */
    public function valida_banco_valor_y_titular(): void
    {
        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.consignaciones.store'), [])
            ->assertJsonValidationErrors(['banco_id', 'valor', 'titular']);
    }

    /** @test */
    public function sin_turno_abierto_no_registra(): void
    {
        $this->turno->update(['status' => LdTurnoCaja::STATUS_CERRADO]);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.consignaciones.store'), $this->payload(['valor' => 1000]))
            ->assertJsonValidationErrors('turno');
    }

    /** @test */
    public function edita_considerando_su_propio_valor_en_el_disponible(): void
    {
        $consignacion = LdConsignacion::factory()->create(['turno_caja_id' => $this->turno->id, 'banco_id' => $this->banco->id, 'valor' => 50000]);

        // disponible = 50.000 base - 50.000 consignado + 50.000 propio
        $this->actingAs($this->cajero)
            ->putJson(route('libro-diario.consignaciones.update', $consignacion), $this->payload(['valor' => 50000, 'titular' => 'Otro titular']))
            ->assertOk()
            ->assertJsonPath('data.titular', 'Otro titular');

        $this->actingAs($this->cajero)
            ->putJson(route('libro-diario.consignaciones.update', $consignacion), $this->payload(['valor' => 50001]))
            ->assertJsonValidationErrors('valor');
    }

    /** @test */
    public function anula_consignacion_y_sale_del_resumen(): void
    {
        $consignacion = LdConsignacion::factory()->create(['turno_caja_id' => $this->turno->id, 'valor' => 20000]);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.consignaciones.anular', $consignacion), ['motivo' => 'Comprobante equivocado'])
            ->assertOk()
            ->assertJsonPath('data.status', LdConsignacion::STATUS_ANULADO);

        $this->actingAs($this->usuarioCon(['fin_ldTurnos']))->getJson(route('libro-diario.turnos.show', $this->turno))
            ->assertJsonPath('data.resumen.consignaciones', 0)
            ->assertJsonPath('data.resumen.efectivo_esperado', 50000);
    }

    /** @test */
    public function sin_permiso_no_anula(): void
    {
        $consignacion = LdConsignacion::factory()->create(['turno_caja_id' => $this->turno->id]);

        $this->actingAs($this->usuarioCon(['fin_libroDiario']))
            ->postJson(route('libro-diario.consignaciones.anular', $consignacion), ['motivo' => 'Comprobante equivocado'])
            ->assertForbidden();
    }
}
