<?php

namespace Tests\Feature\Api\Traslados;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\ConceptoPago\ConceptoPago;
use App\Models\Traslados\MatriculaTraslado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Traslados\Concerns\CreaEscenarioTraslado;
use Tests\TestCase;

/**
 * Parte financiera del traslado: lo pagado no se traslada, lo pendiente de la sede de origen se
 * cancela y se genera cartera nueva en la sede de destino por la diferencia con el precio del
 * destino (sin devoluciones), con la tarifa opcional del traslado y el plan de pagos acordado.
 *
 * Escenario: precio de origen 1.000.000, pagado 400.000 (cuotas 0 y 1); pendientes las cuotas
 * 2, 3 y 4 de 200.000. El precio de la sede B es 1.300.000.
 */
class TrasladoFinancieroTest extends TestCase
{
    use CreaEscenarioTraslado, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEscenarioTraslado();
    }

    private function estado(string $nombre): int
    {
        return Cartera::getStatusKey($nombre);
    }

    // ─── Cambio de sede o de curso ────────────────────────────────────────────

    /** @test */
    public function si_el_destino_vale_mas_el_estudiante_paga_la_diferencia_en_la_nueva_sede(): void
    {
        $this->trasladar()
            ->assertCreated()
            ->assertJsonPath('data.financiero.valor_pagado', 400000)
            ->assertJsonPath('data.financiero.saldo_anulado', 600000)
            ->assertJsonPath('data.financiero.saldo_nuevo', 900000);

        // Lo pagado se queda en la sede de origen
        $origen = $this->carteras($this->sedeA->id, $this->estado('Cerrada'));
        $this->assertSame([0, 1], $origen->pluck('numero_cuota')->all());

        // Lo pendiente de la sede de origen se cancela
        $this->assertSame([2, 3, 4], $this->carteras($this->sedeA->id, $this->estado('Anulada'))->pluck('numero_cuota')->all());

        // La cartera nueva nace en la sede de destino: 1.300.000 - 400.000 = 900.000 en las 3 cuotas pendientes
        $nuevas = $this->carteras($this->sedeB->id);
        $this->assertSame([2, 3, 4], $nuevas->pluck('numero_cuota')->all());
        $this->assertEquals([300000, 300000, 300000], $nuevas->pluck('valor')->map(fn ($v) => (float) $v)->all());
        $this->assertTrue($nuevas->every(fn (Cartera $c) => $c->status === $this->estado('Activa') && (float) $c->saldo === (float) $c->valor));
        $this->assertSame('2027-03-01', $nuevas->first()->fecha_vencimiento->toDateString(), 'Conserva el vencimiento de la cuota que reemplaza.');

        $matricula = $this->matricula->fresh();
        $this->assertEquals(1300000, (float) $matricula->monto);
        $this->assertSame($this->lpBX->id, $matricula->lp_precio_producto_id);
    }

    /** @test */
    public function el_traslado_deja_el_resumen_de_lo_cancelado_y_lo_creado(): void
    {
        $this->trasladar()->assertCreated();

        $traslado = MatriculaTraslado::first();
        $this->assertEquals(400000, (float) $traslado->valor_pagado);
        $this->assertCount(3, $traslado->carteras['anuladas']);
        $this->assertCount(3, $traslado->carteras['creadas']);
        $this->assertSame([], $traslado->carteras['cerradas']);
        $this->assertSame($this->lpAX->id, $traslado->lp_origen_id);
        $this->assertSame($this->lpBX->id, $traslado->lp_destino_id);
    }

    /** @test */
    public function si_el_destino_vale_menos_no_hay_devolucion_ni_cartera_nueva(): void
    {
        $contado = $this->crearPrecio($this->sedeB, $this->cursoX, 300000, null, 0, null);

        $this->trasladar(['lp_precio_producto_id' => $contado->id])
            ->assertCreated()
            ->assertJsonPath('data.financiero.saldo_anulado', 600000)
            ->assertJsonPath('data.financiero.saldo_nuevo', 0);

        $this->assertCount(0, $this->carteras($this->sedeB->id));
        $this->assertSame([2, 3, 4], $this->carteras($this->sedeA->id, $this->estado('Anulada'))->pluck('numero_cuota')->all());
        $this->assertEquals(400000, (float) $this->matricula->fresh()->monto, 'El monto queda en lo ya pagado: no se devuelve nada.');
    }

    /** @test */
    public function con_el_mismo_precio_solo_se_mueve_lo_pendiente_a_la_nueva_sede(): void
    {
        $igual = $this->crearPrecio($this->sedeB, $this->cursoX, 1000000, 800000, 200000, 4);

        $this->trasladar(['lp_precio_producto_id' => $igual->id])->assertCreated()->assertJsonPath('data.financiero.saldo_nuevo', 600000);

        $this->assertEquals([200000, 200000, 200000], $this->carteras($this->sedeB->id)->pluck('valor')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals(1000000, (float) $this->matricula->fresh()->monto);
    }

    /** @test */
    public function una_cuota_con_abono_conserva_lo_pagado_en_origen_y_cancela_el_resto(): void
    {
        $this->carteras($this->sedeA->id)->firstWhere('numero_cuota', 2)->aplicarPago(100000);

        $this->trasladar()->assertCreated()
            ->assertJsonPath('data.financiero.valor_pagado', 500000)
            ->assertJsonPath('data.financiero.saldo_anulado', 500000)
            ->assertJsonPath('data.financiero.saldo_nuevo', 800000);

        $cuota2 = $this->carteras($this->sedeA->id)->firstWhere('numero_cuota', 2);
        $this->assertSame($this->estado('Cerrada'), $cuota2->status);
        $this->assertEquals(100000, (float) $cuota2->valor);
        $this->assertEquals(0, (float) $cuota2->saldo);

        // Había 3 cuotas pendientes: el saldo nuevo se reparte en 3. La cuota 2 sigue existiendo (cerrada
        // con lo abonado), así que la cartera nueva reutiliza el 3 y el 4 y sigue con el 5.
        $nuevas = $this->carteras($this->sedeB->id);
        $this->assertSame([3, 4, 5], $nuevas->pluck('numero_cuota')->all());
        $this->assertEquals([266666.67, 266666.67, 266666.66], $nuevas->pluck('valor')->map(fn ($v) => (float) $v)->all());
    }

    /** @test */
    public function el_reparto_en_cuotas_deja_el_redondeo_en_la_ultima(): void
    {
        $barato = $this->crearPrecio($this->sedeB, $this->cursoX, 900000, 700000, 200000, 4);

        $this->trasladar(['lp_precio_producto_id' => $barato->id])->assertCreated();

        $valores = $this->carteras($this->sedeB->id)->pluck('valor')->map(fn ($v) => (float) $v)->all();
        $this->assertSame(500000.0, array_sum($valores));
        $this->assertEquals([166666.67, 166666.67, 166666.66], $valores);
    }

    /** @test */
    public function simular_muestra_lo_que_se_cancela_y_lo_que_se_genera(): void
    {
        $this->simular()->assertOk()
            ->assertJsonPath('data.financiero.regenera_cartera', true)
            ->assertJsonPath('data.financiero.precio_destino', 1300000)
            ->assertJsonPath('data.financiero.saldo_nuevo', 900000)
            ->assertJsonCount(3, 'data.financiero.carteras_a_cancelar')
            ->assertJsonCount(3, 'data.financiero.cuotas_nuevas')
            ->assertJsonPath('data.financiero.carteras_a_cancelar.0.concepto', ConceptoPago::MENSUALIDAD);

        $this->assertCount(0, $this->carteras($this->sedeB->id));
    }

    // ─── Cambio de programación (misma sede y curso) ──────────────────────────

    /** @test */
    public function el_cambio_de_programacion_no_toca_la_cartera(): void
    {
        $antes = $this->carteras()->map->only(['id', 'status', 'valor', 'sede_id'])->all();

        $this->trasladar(['sede_id' => $this->sedeA->id, 'ciclo_id' => $this->cicloA2->id, 'lp_precio_producto_id' => null])
            ->assertCreated()
            ->assertJsonPath('data.financiero.saldo_nuevo', 0)
            ->assertJsonPath('data.financiero.costo_traslado', 0);

        $this->assertEquals($antes, $this->carteras()->map->only(['id', 'status', 'valor', 'sede_id'])->all());
    }

    // ─── Tarifa del traslado ──────────────────────────────────────────────────

    /** @test */
    public function sin_tarifa_configurada_el_traslado_no_se_cobra(): void
    {
        $this->trasladar()->assertCreated()->assertJsonPath('data.financiero.costo_traslado', 0);

        $this->assertSame(0, Cartera::where('concepto', ConceptoPago::TRASLADO)->count());
    }

    /** @test */
    public function la_tarifa_del_concepto_traslado_se_cobra_en_la_sede_de_destino(): void
    {
        ConceptoPago::create(['nombre' => ConceptoPago::TRASLADO, 'tipo' => 0, 'valor' => 50000]);

        $this->simular()->assertOk()->assertJsonPath('data.financiero.tarifa_traslado', 50000);

        $this->trasladar()->assertCreated()->assertJsonPath('data.financiero.costo_traslado', 50000);

        $costo = Cartera::where('concepto', ConceptoPago::TRASLADO)->sole();
        $this->assertSame($this->sedeB->id, $costo->sede_id);
        $this->assertSame(0, $costo->numero_cuota);
        $this->assertEquals(50000, (float) $costo->valor);
        $this->assertSame($this->estado('Activa'), $costo->status);
        $this->assertSame('Traslado', $costo->conceptoNombre());
    }

    /** @test */
    public function la_tarifa_tambien_aplica_al_cambio_de_programacion(): void
    {
        ConceptoPago::create(['nombre' => ConceptoPago::TRASLADO, 'tipo' => 0, 'valor' => 30000]);

        $this->trasladar(['sede_id' => $this->sedeA->id, 'ciclo_id' => $this->cicloA2->id, 'lp_precio_producto_id' => null])->assertCreated();

        $this->assertEquals(30000, (float) Cartera::where('concepto', ConceptoPago::TRASLADO)->sole()->valor);
    }

    /** @test */
    public function solo_quien_tiene_el_permiso_especial_puede_condonar_el_cobro(): void
    {
        ConceptoPago::create(['nombre' => ConceptoPago::TRASLADO, 'tipo' => 0, 'valor' => 50000]);

        $this->trasladar(['condonar_costo' => true])->assertUnprocessable()->assertJsonValidationErrors('condonar_costo');

        $this->trasladar(['condonar_costo' => true], $this->gerente)
            ->assertCreated()
            ->assertJsonPath('data.financiero.costo_traslado', 0);
        $this->assertSame(0, Cartera::where('concepto', ConceptoPago::TRASLADO)->count());
    }

    /** @test */
    public function el_cobro_de_un_traslado_anterior_no_se_cancela_ni_cuenta_como_pagado(): void
    {
        ConceptoPago::create(['nombre' => ConceptoPago::TRASLADO, 'tipo' => 0, 'valor' => 50000]);
        $this->trasladar()->assertCreated();

        // Vuelve a la sede A: el cobro pendiente del primer traslado sigue vigente
        $this->trasladar([
            'sede_id' => $this->sedeA->id, 'ciclo_id' => $this->cicloA2->id, 'lp_precio_producto_id' => $this->lpAX->id,
        ])->assertCreated()->assertJsonPath('data.financiero.valor_pagado', 400000);

        $primerCobro = Cartera::where('concepto', ConceptoPago::TRASLADO)->orderBy('id')->first();
        $this->assertSame($this->estado('Activa'), $primerCobro->fresh()->status);
        $this->assertSame(2, Cartera::where('concepto', ConceptoPago::TRASLADO)->count());
    }

    // ─── Plan de pagos acordado ───────────────────────────────────────────────

    /** @test */
    public function el_plan_de_pagos_acordado_reemplaza_al_propuesto(): void
    {
        $plan = [
            ['valor' => 500000, 'fecha_vencimiento' => '2027-03-15'],
            ['valor' => 250000, 'fecha_vencimiento' => '2027-04-15'],
        ];

        $this->trasladar(['cuotas' => $plan], $this->gerente)
            ->assertCreated()
            ->assertJsonPath('data.financiero.saldo_nuevo', 750000);

        $nuevas = $this->carteras($this->sedeB->id);
        $this->assertEquals([500000, 250000], $nuevas->pluck('valor')->map(fn ($v) => (float) $v)->all());
        $this->assertSame(['2027-03-15', '2027-04-15'], $nuevas->map(fn ($c) => $c->fecha_vencimiento->toDateString())->all());
        $this->assertEquals(1150000, (float) $this->matricula->fresh()->monto, 'Monto = lo pagado + el plan acordado.');
    }

    /** @test */
    public function un_plan_con_mas_cuotas_que_las_pendientes_sigue_la_numeracion(): void
    {
        $plan = array_map(fn ($mes) => ['valor' => 225000, 'fecha_vencimiento' => "2027-0{$mes}-15"], [3, 4, 5, 6]);

        $this->trasladar(['cuotas' => $plan], $this->gerente)->assertCreated();

        $this->assertSame([2, 3, 4, 5], $this->carteras($this->sedeB->id)->pluck('numero_cuota')->all());
    }

    /** @test */
    public function acordar_el_plan_exige_permiso_y_un_cambio_de_sede_o_curso(): void
    {
        $plan = [['valor' => 900000, 'fecha_vencimiento' => '2027-03-15']];

        $this->trasladar(['cuotas' => $plan])->assertUnprocessable()->assertJsonValidationErrors('cuotas');

        $this->trasladar(['sede_id' => $this->sedeA->id, 'ciclo_id' => $this->cicloA2->id, 'lp_precio_producto_id' => null, 'cuotas' => $plan], $this->gerente)
            ->assertUnprocessable()->assertJsonValidationErrors('cuotas');

        $this->trasladar(['cuotas' => [['valor' => 0, 'fecha_vencimiento' => '2027-03-15']]], $this->gerente)
            ->assertUnprocessable()->assertJsonValidationErrors('cuotas.0.valor');

        $this->assertDatabaseCount('matricula_traslados', 0);
    }

    // ─── Precio de lista del destino ──────────────────────────────────────────

    /** @test */
    public function al_cambiar_de_sede_o_curso_exige_el_precio_de_lista_del_destino(): void
    {
        $this->trasladar(['lp_precio_producto_id' => null])->assertUnprocessable()->assertJsonValidationErrors('lp_precio_producto_id');
    }

    /** @test */
    public function rechaza_un_precio_que_no_es_del_curso_ni_de_la_sede_de_destino(): void
    {
        // Precio de la sede A (otra población) y precio de otro curso
        foreach ([$this->lpAX, $this->lpBY] as $incorrecto) {
            $this->trasladar(['lp_precio_producto_id' => $incorrecto->id])
                ->assertUnprocessable()->assertJsonValidationErrors('lp_precio_producto_id');
        }
    }

    /** @test */
    public function rechaza_un_precio_de_una_lista_que_no_esta_vigente(): void
    {
        $this->lpBX->listaPrecio->update(['fecha_fin' => now()->subDay()->toDateString()]);

        $this->trasladar()->assertUnprocessable()->assertJsonValidationErrors('lp_precio_producto_id');
    }

    /** @test */
    public function una_matricula_sin_precio_ni_cartera_se_traslada_sin_movimientos_financieros(): void
    {
        $sinCartera = Matricula::withoutEvents(fn () => $this->crearMatricula(['lp_precio_producto_id' => null]));

        $this->trasladar(['matricula_id' => $sinCartera->id, 'lp_precio_producto_id' => null])
            ->assertCreated()
            ->assertJsonPath('data.financiero.saldo_nuevo', 0);

        $this->assertSame($this->sedeB->id, $sinCartera->fresh()->sede_id);
        $this->assertSame(0, Cartera::where('matricula_id', $sinCartera->id)->count());
    }
}
