<?php

namespace Tests\Feature\Api\Financiero\LibroDiario;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\LibroDiario\LdConsignacion;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\Financiero\LibroDiario\LdTurnoEvento;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Pruebas de turnos de caja: apertura, arqueo, cierre, bloqueo del día,
 * autorización para reabrir y reversión del cierre.
 */
class LdTurnoCajaTest extends TestCase
{
    use PreparaLibroDiario, RefreshDatabase;

    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararLibroDiario();
        $this->cajero = $this->usuarioCon(['fin_ldTurnoCaja', 'fin_libroDiario']);
    }

    private function abrir(array $datos = [], ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->cajero)->postJson(route('libro-diario.turnos.store'), array_merge([
            'sede_id' => $this->sede->id,
            'base_inicial' => 200000,
        ], $datos));
    }

    // ─── Apertura ─────────────────────────────────────────────────────────────

    /** @test */
    public function abre_turno_con_base_inicial(): void
    {
        $this->abrir()
            ->assertCreated()
            ->assertJsonPath('data.status', LdTurnoCaja::STATUS_ABIERTO)
            ->assertJsonPath('data.base_inicial', 200000)
            // N26: antes del cierre aprobado el cajero no ve nada que mueva efectivo
            ->assertJsonMissingPath('data.resumen.base_inicial')
            ->assertJsonMissingPath('data.resumen.efectivo_esperado');

        $this->assertDatabaseHas('ld_turno_eventos', ['accion' => LdTurnoEvento::ACCION_APERTURA, 'usuario_id' => $this->cajero->id]);
    }

    /** @test */
    public function abre_turno_con_base_cero_cuando_no_recibe_dinero(): void
    {
        $this->abrir(['base_inicial' => 0])
            ->assertCreated()
            ->assertJsonPath('data.base_inicial', 0);
    }

    /** @test */
    public function no_abre_turno_en_sede_no_asignada(): void
    {
        $otraSede = Sede::factory()->create();

        $this->abrir(['sede_id' => $otraSede->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sede_id');
    }

    /** @test */
    public function no_abre_dos_turnos_a_la_vez_en_la_misma_sede(): void
    {
        $this->abrir()->assertCreated();

        $this->abrir()->assertUnprocessable()->assertJsonValidationErrors('turno');
    }

    /** @test */
    public function con_una_sola_sede_abre_turno_registrando_solo_la_base(): void
    {
        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.turnos.store'), ['base_inicial' => 50000])
            ->assertCreated()
            ->assertJsonPath('data.sede_id', $this->sede->id);
    }

    /** @test */
    public function con_varias_sedes_debe_indicar_la_sede(): void
    {
        $this->cajero->sedes()->attach(Sede::factory()->create()->id);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.turnos.store'), ['base_inicial' => 50000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sede_id');
    }

    /** @test */
    public function abre_caja_a_la_vez_en_varias_de_sus_sedes(): void
    {
        $otraSede = Sede::factory()->create();
        $this->cajero->sedes()->attach($otraSede->id);

        $primero = $this->abrir()->assertCreated()->json('data.id');
        $segundo = $this->abrir(['sede_id' => $otraSede->id, 'base_inicial' => 0])->assertCreated()->json('data.id');

        $respuesta = $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.actual'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $primero)
            ->assertJsonPath('data.1.id', $segundo)
            ->assertJsonPath('meta.puede_abrir', false)
            ->assertJsonPath('meta.motivo_no_puede', null);

        $sedes = collect($respuesta->json('meta.sedes_disponibles'))->keyBy('id');
        $this->assertSame($primero, $sedes[$this->sede->id]['turno_abierto_id']);
        $this->assertSame($segundo, $sedes[$otraSede->id]['turno_abierto_id']);
        $this->assertFalse($sedes[$otraSede->id]['puede_abrir']);
    }

    /** @test */
    public function valida_base_inicial_obligatoria_y_no_negativa(): void
    {
        $this->abrir(['base_inicial' => null])->assertJsonValidationErrors('base_inicial');
        $this->abrir(['base_inicial' => -1])->assertJsonValidationErrors('base_inicial');
    }

    /** @test */
    public function sin_permiso_no_abre_turno(): void
    {
        $this->abrir([], $this->usuarioCon(['fin_libroDiario']))->assertForbidden();
    }

    /** @test */
    public function turno_actual_indica_si_puede_abrir(): void
    {
        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.actual'))
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.puede_abrir', true)
            ->assertJsonPath('meta.sedes_disponibles.0.id', $this->sede->id)
            ->assertJsonPath('meta.sedes_disponibles.0.puede_abrir', true)
            ->assertJsonPath('meta.sedes_disponibles.0.turno_abierto_id', null);

        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);

        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.actual'))
            ->assertOk()
            ->assertJsonPath('data.0.cajero_id', $this->cajero->id)
            ->assertJsonPath('meta.puede_abrir', false)
            ->assertJsonPath('meta.sedes_disponibles.0.turno_abierto_id', $turno->id);
    }

    /** @test */
    public function turno_actual_explica_por_que_no_puede_abrir(): void
    {
        LdTurnoCaja::factory()->cerrado()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);

        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.actual'))
            ->assertOk()
            ->assertJsonPath('meta.puede_abrir', false)
            ->assertJsonPath('meta.motivo_no_puede', 'Ya cerró caja hoy en esta sede. Para abrir un nuevo turno necesita la autorización de un supervisor.');
    }

    // ─── Resumen y cierre ─────────────────────────────────────────────────────

    /** @test */
    public function el_cajero_cierra_a_ciegas_y_el_cierre_queda_por_aprobar(): void
    {
        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id, 'base_inicial' => 100000]);

        $recibo = $this->reciboPagado(80000, 'efectivo', ['turno_caja_id' => $turno->id, 'cajero_id' => $this->cajero->id]);
        $this->reciboPagado(50000, 'tarjeta_debito', ['turno_caja_id' => $turno->id, 'origen' => ReciboPago::ORIGEN_INVENTARIOS]);
        $this->reciboPagado(999999, 'efectivo', ['turno_caja_id' => $turno->id, 'status' => ReciboPago::STATUS_ANULADO]);
        $pendiente = $this->reciboPagado(40000, 'transferencia', ['turno_caja_id' => $turno->id, 'status' => ReciboPago::STATUS_PENDIENTE_APROBACION]);
        LdMovimiento::factory()->create(['turno_caja_id' => $turno->id, 'valor_total' => 30000, 'subtotal' => 30000]);
        LdMovimiento::factory()->create([
            'turno_caja_id' => $turno->id,
            'tipo_movimiento_id' => LdTipoMovimiento::factory()->ingreso(),
            'valor_total' => 10000,
            'subtotal' => 10000,
        ]);
        LdConsignacion::factory()->create(['turno_caja_id' => $turno->id, 'valor' => 60000]);

        // esperado = 100.000 + 80.000 + 10.000 - 30.000 - 60.000 = 100.000
        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.turnos.cerrar', $turno), ['efectivo_contado' => 95000, 'observaciones' => 'Faltante'])
            ->assertOk()
            ->assertJsonPath('data.status', LdTurnoCaja::STATUS_POR_APROBAR)
            ->assertJsonPath('data.efectivo_contado', 95000)
            ->assertJsonMissingPath('data.efectivo_esperado')
            ->assertJsonMissingPath('data.diferencia')
            ->assertJsonMissingPath('data.resumen.efectivo_esperado')
            // N26: solo los medios distintos al efectivo; sin base, totales ni consignaciones
            ->assertJsonPath('data.resumen.restringido', true)
            ->assertJsonMissingPath('data.resumen.base_inicial')
            ->assertJsonMissingPath('data.resumen.ingresos')
            ->assertJsonMissingPath('data.resumen.egresos')
            ->assertJsonMissingPath('data.resumen.consignaciones')
            ->assertJsonMissingPath('data.resumen.por_medio_pago.efectivo')
            ->assertJsonPath('data.resumen.por_medio_pago.tarjeta_debito.inventario', 50000)
            ->assertJsonPath('data.resumen.transferencias_pendientes.cantidad', 1);

        $this->assertSame(ReciboPago::STATUS_CERRADO, $recibo->fresh()->status);
        $this->assertSame($turno->id, $recibo->fresh()->cierre);
        $this->assertSame(ReciboPago::STATUS_PENDIENTE_APROBACION, $pendiente->fresh()->status);

        // Quien aprueba sí ve el arqueo
        $this->actingAs($this->usuarioCon(['fin_ldCierreAprobar']))
            ->getJson(route('libro-diario.turnos.show', $turno))
            ->assertOk()
            ->assertJsonPath('data.efectivo_esperado', 100000)
            ->assertJsonPath('data.diferencia', -5000)
            ->assertJsonPath('data.descuadre', true)
            ->assertJsonPath('data.resumen.efectivo_esperado', 100000)
            // Todos los medios de pago, con ingresos separados por origen
            ->assertJsonPath('data.resumen.por_medio_pago.efectivo.academico', 80000)
            ->assertJsonPath('data.resumen.por_medio_pago.efectivo.otros', 10000)
            ->assertJsonPath('data.resumen.por_medio_pago.efectivo.egresos', 30000)
            ->assertJsonPath('data.resumen.por_medio_pago.tarjeta_debito.inventario', 50000)
            ->assertJsonPath('data.resumen.por_medio_pago.transferencia.academico', 0)
            ->assertJsonPath('data.resumen.por_medio_pago.cheque.ingresos', 0);
    }

    /** @test */
    public function un_cierre_cuadrado_no_enciende_la_alarma(): void
    {
        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id, 'base_inicial' => 100000]);
        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.turnos.cerrar', $turno), ['efectivo_contado' => 100000])
            ->assertOk()
            ->assertJsonMissingPath('data.descuadre');

        $this->actingAs($this->usuarioCon(['fin_ldCierreAprobar']))
            ->getJson(route('libro-diario.turnos.index'))
            ->assertJsonPath('data.0.descuadre', false)
            ->assertJsonPath('data.0.diferencia', 0);
    }

    /** @test */
    public function no_cierra_un_turno_ya_cerrado(): void
    {
        $turno = LdTurnoCaja::factory()->cerrado()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.turnos.cerrar', $turno), ['efectivo_contado' => 0])
            ->assertUnprocessable();
    }

    /** @test */
    public function no_cierra_turno_ajeno_sin_permiso_de_supervision(): void
    {
        $turno = LdTurnoCaja::factory()->create(['sede_id' => $this->sede->id]);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.turnos.cerrar', $turno), ['efectivo_contado' => 0])
            ->assertForbidden();

        $supervisor = $this->usuarioCon(['fin_ldTurnos']);
        $this->actingAs($supervisor)
            ->postJson(route('libro-diario.turnos.cerrar', $turno), ['efectivo_contado' => 100000])
            ->assertOk();
    }

    // ─── Aprobación del cierre ────────────────────────────────────────────────

    /** @test */
    public function el_turno_actual_no_muestra_el_efectivo_esperado_al_cajero(): void
    {
        LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);

        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.actual'))
            ->assertOk()
            ->assertJsonPath('data.0.resumen.restringido', true)
            ->assertJsonMissingPath('data.0.resumen.efectivo_esperado')
            ->assertJsonMissingPath('data.0.efectivo_esperado');
    }

    /** @test */
    public function el_supervisor_tampoco_ve_el_esperado_de_su_propia_caja(): void
    {
        $this->cajero->givePermissionTo(['fin_ldTurnos', 'fin_ldCierreAprobar']);
        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);

        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.actual'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $turno->id)
            ->assertJsonMissingPath('data.0.resumen.efectivo_esperado');

        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.show', $turno))
            ->assertOk()
            ->assertJsonMissingPath('data.efectivo_esperado')
            ->assertJsonMissingPath('data.resumen.efectivo_esperado');

        // Otro supervisor de la sede sí lo ve
        $this->actingAs($this->usuarioCon(['fin_ldTurnos']))->getJson(route('libro-diario.turnos.show', $turno))
            ->assertJsonPath('data.resumen.efectivo_esperado', 100000);
    }

    /** @test */
    public function aprueba_el_cierre_con_observaciones(): void
    {
        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $this->actingAs($this->cajero)->postJson(route('libro-diario.turnos.cerrar', $turno), ['efectivo_contado' => 100000])->assertOk();

        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.actual'))
            ->assertJsonPath('meta.cierres_por_aprobar.0.id', $turno->id)
            ->assertJsonPath('meta.cierres_por_aprobar.0.sede', $this->sede->nombre);

        $aprobador = $this->usuarioCon(['fin_ldCierreAprobar']);
        $this->actingAs($aprobador)
            ->postJson(route('libro-diario.turnos.aprobar', $turno), ['observaciones' => 'Cuadra'])
            ->assertOk()
            ->assertJsonPath('data.status', LdTurnoCaja::STATUS_CERRADO)
            ->assertJsonPath('data.aprobado_por.id', $aprobador->id)
            ->assertJsonPath('data.observaciones_aprobacion', 'Cuadra');

        $this->assertDatabaseHas('ld_turno_eventos', ['turno_caja_id' => $turno->id, 'accion' => LdTurnoEvento::ACCION_APROBACION]);

        // N26: con el cierre aprobado, el cajero ya ve el detalle completo y el arqueo
        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.show', $turno))
            ->assertOk()
            ->assertJsonPath('data.resumen.base_inicial', fn ($v) => (float) $v === (float) $turno->base_inicial)
            ->assertJsonPath('data.diferencia', fn ($v) => (float) $v === 100000 - (float) $turno->base_inicial)
            ->assertJsonPath('data.resumen.por_medio_pago.efectivo.ingresos', 0);
    }

    /** @test */
    public function nadie_aprueba_su_propio_cierre(): void
    {
        $this->cajero->givePermissionTo('fin_ldCierreAprobar');
        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $this->actingAs($this->cajero)->postJson(route('libro-diario.turnos.cerrar', $turno), ['efectivo_contado' => 0])->assertOk();

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.turnos.aprobar', $turno))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No puede aprobar ni rechazar un cierre de caja propio.');
    }

    /** @test */
    public function aprobar_exige_permiso_sede_y_cierre_pendiente(): void
    {
        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);

        $this->actingAs($this->usuarioCon(['fin_ldTurnos']))
            ->postJson(route('libro-diario.turnos.aprobar', $turno))
            ->assertForbidden();

        $this->actingAs($this->usuarioCon(['fin_ldCierreAprobar'], Sede::factory()->create()))
            ->postJson(route('libro-diario.turnos.aprobar', $turno))
            ->assertForbidden();

        $this->actingAs($this->usuarioCon(['fin_ldCierreAprobar']))
            ->postJson(route('libro-diario.turnos.aprobar', $turno))
            ->assertUnprocessable();
    }

    /** @test */
    public function rechaza_el_cierre_y_el_turno_vuelve_a_abrirse(): void
    {
        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $recibo = $this->reciboPagado(80000, 'efectivo', ['turno_caja_id' => $turno->id]);
        $this->actingAs($this->cajero)->postJson(route('libro-diario.turnos.cerrar', $turno), ['efectivo_contado' => 1000])->assertOk();

        $this->actingAs($this->usuarioCon(['fin_ldCierreAprobar']))
            ->postJson(route('libro-diario.turnos.rechazar', $turno), ['motivo' => 'Vuelva a contar el efectivo'])
            ->assertOk()
            ->assertJsonPath('data.status', LdTurnoCaja::STATUS_ABIERTO);

        $this->assertSame(ReciboPago::STATUS_CREADO, $recibo->fresh()->status);
        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.actual'))
            ->assertJsonPath('data.0.id', $turno->id)
            ->assertJsonPath('meta.cierres_rechazados.0.turno_id', $turno->id)
            ->assertJsonPath('meta.cierres_rechazados.0.motivo', 'Vuelva a contar el efectivo');
    }

    /** @test */
    public function un_cierre_por_aprobar_no_se_reversa_ni_admite_pagos(): void
    {
        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $this->actingAs($this->cajero)->postJson(route('libro-diario.turnos.cerrar', $turno), ['efectivo_contado' => 0])->assertOk();

        $this->actingAs($this->usuarioCon(['fin_ldTurnoReversar']))
            ->postJson(route('libro-diario.turnos.reversar', $turno), ['motivo' => 'Corrección'])
            ->assertUnprocessable();

        $this->assertFalse(LdTurnoCaja::abiertoDe($this->cajero->id)->exists());
        $this->abrir()->assertUnprocessable()->assertJsonValidationErrors('turno');
    }

    // ─── Bloqueo del día y autorización ───────────────────────────────────────

    /** @test */
    public function tras_cerrar_caja_no_abre_otro_turno_hoy_sin_autorizacion(): void
    {
        LdTurnoCaja::factory()->cerrado()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);

        $this->abrir()->assertUnprocessable()->assertJsonValidationErrors('turno');
    }

    /** @test */
    public function con_autorizacion_abre_otro_turno_y_la_consume(): void
    {
        LdTurnoCaja::factory()->cerrado()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $supervisor = $this->usuarioCon(['fin_ldTurnoAutorizar']);

        $this->actingAs($supervisor)
            ->postJson(route('libro-diario.autorizaciones.store'), ['cajero_id' => $this->cajero->id, 'motivo' => 'Jornada nocturna'])
            ->assertCreated();

        $id = $this->abrir()->assertCreated()->json('data.id');

        $this->assertDatabaseHas('ld_autorizaciones_turno', ['cajero_id' => $this->cajero->id, 'turno_caja_id' => $id]);

        // La autorización es de un solo uso
        $this->actingAs($this->cajero)->postJson(route('libro-diario.turnos.cerrar', $id), ['efectivo_contado' => 200000])->assertOk();
        $this->abrir()->assertUnprocessable()->assertJsonValidationErrors('turno');
    }

    /** @test */
    public function cerrar_caja_en_una_sede_no_bloquea_ni_cierra_las_otras(): void
    {
        $otraSede = Sede::factory()->create();
        $this->cajero->sedes()->attach($otraSede->id);
        $enSede = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $enOtra = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $otraSede->id]);

        $this->actingAs($this->cajero)->postJson(route('libro-diario.turnos.cerrar', $enSede), ['efectivo_contado' => 100000])->assertOk();

        $this->assertSame(LdTurnoCaja::STATUS_ABIERTO, $enOtra->fresh()->status);
        $this->abrir()->assertUnprocessable()->assertJsonValidationErrors('turno');

        // Cierre propio de la otra sede: también queda por aprobar
        $this->actingAs($this->cajero)->postJson(route('libro-diario.turnos.cerrar', $enOtra), ['efectivo_contado' => 100000])
            ->assertOk()
            ->assertJsonPath('data.status', LdTurnoCaja::STATUS_POR_APROBAR);

        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.actual'))
            ->assertJsonPath('data', [])
            ->assertJsonCount(2, 'meta.cierres_por_aprobar');
    }

    /** @test */
    public function haber_cerrado_hoy_en_otra_sede_no_impide_abrir_en_esta(): void
    {
        $otraSede = Sede::factory()->create();
        $this->cajero->sedes()->attach($otraSede->id);
        LdTurnoCaja::factory()->cerrado()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $otraSede->id]);

        $this->abrir()->assertCreated();
    }

    /** @test */
    public function un_turno_cerrado_ayer_no_bloquea_hoy(): void
    {
        LdTurnoCaja::factory()->cerrado()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id, 'fecha' => today()->subDay()]);

        $this->abrir()->assertCreated();
    }

    /** @test */
    public function no_autoriza_cajero_de_otra_sede(): void
    {
        $ajeno = $this->usuarioCon(['fin_ldTurnoCaja'], Sede::factory()->create());
        $supervisor = $this->usuarioCon(['fin_ldTurnoAutorizar']);

        $this->actingAs($supervisor)
            ->postJson(route('libro-diario.autorizaciones.store'), ['cajero_id' => $ajeno->id, 'motivo' => 'Jornada nocturna'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cajero_id');
    }

    /** @test */
    public function sin_permiso_no_autoriza(): void
    {
        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.autorizaciones.store'), ['cajero_id' => $this->cajero->id, 'motivo' => 'Quiero abrir'])
            ->assertForbidden();
    }

    // ─── Reversión ────────────────────────────────────────────────────────────

    /** @test */
    public function reversa_el_cierre_y_reabre_recibos(): void
    {
        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $recibo = $this->reciboPagado(80000, 'efectivo', ['turno_caja_id' => $turno->id]);
        $this->actingAs($this->cajero)->postJson(route('libro-diario.turnos.cerrar', $turno), ['efectivo_contado' => 180000])->assertOk();
        $this->actingAs($this->usuarioCon(['fin_ldCierreAprobar']))->postJson(route('libro-diario.turnos.aprobar', $turno))->assertOk();

        $supervisor = $this->usuarioCon(['fin_ldTurnoReversar']);
        $this->actingAs($supervisor)
            ->postJson(route('libro-diario.turnos.reversar', $turno), ['motivo' => 'Recibo mal digitado'])
            ->assertOk()
            ->assertJsonPath('data.status', LdTurnoCaja::STATUS_ABIERTO)
            ->assertJsonPath('data.efectivo_contado', null);

        $this->assertSame(ReciboPago::STATUS_CREADO, $recibo->fresh()->status);
        $this->assertNull($recibo->fresh()->cierre);
        $evento = LdTurnoEvento::where('accion', LdTurnoEvento::ACCION_REVERSION)->first();
        $this->assertSame('Recibo mal digitado', $evento->motivo);
        $this->assertEquals(180000, $evento->datos['efectivo_contado']);
    }

    /** @test */
    public function no_reversa_si_el_cajero_tiene_otro_turno_abierto(): void
    {
        $cerrado = LdTurnoCaja::factory()->cerrado()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id, 'fecha' => today()->subDay()]);
        LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);

        $this->actingAs($this->usuarioCon(['fin_ldTurnoReversar']))
            ->postJson(route('libro-diario.turnos.reversar', $cerrado), ['motivo' => 'Corrección'])
            ->assertUnprocessable();
    }

    /** @test */
    public function reversa_aunque_el_cajero_tenga_caja_abierta_en_otra_sede(): void
    {
        $otraSede = Sede::factory()->create();
        $cerrado = LdTurnoCaja::factory()->cerrado()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id, 'fecha' => today()->subDay()]);
        LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $otraSede->id]);

        $this->actingAs($this->usuarioCon(['fin_ldTurnoReversar']))
            ->postJson(route('libro-diario.turnos.reversar', $cerrado), ['motivo' => 'Corrección'])
            ->assertOk()
            ->assertJsonPath('data.status', LdTurnoCaja::STATUS_ABIERTO);
    }

    /** @test */
    public function reversar_exige_permiso_y_motivo(): void
    {
        $turno = LdTurnoCaja::factory()->cerrado()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.turnos.reversar', $turno), ['motivo' => 'Corrección'])
            ->assertForbidden();

        $this->actingAs($this->usuarioCon(['fin_ldTurnoReversar']))
            ->postJson(route('libro-diario.turnos.reversar', $turno), [])
            ->assertJsonValidationErrors('motivo');
    }

    // ─── Consulta ─────────────────────────────────────────────────────────────

    /** @test */
    public function el_cajero_solo_lista_sus_turnos_y_el_supervisor_los_de_sus_sedes(): void
    {
        LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        LdTurnoCaja::factory()->create(['sede_id' => $this->sede->id]);
        LdTurnoCaja::factory()->create(); // otra sede

        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.index'))
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->actingAs($this->usuarioCon(['fin_ldTurnos']))->getJson(route('libro-diario.turnos.index'))
            ->assertOk()->assertJsonPath('meta.total', 2);
    }

    /** @test */
    public function muestra_turno_con_bitacora_y_descarga_pdf(): void
    {
        $id = $this->abrir()->json('data.id');

        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.show', $id))
            ->assertOk()
            ->assertJsonPath('data.eventos.0.accion', LdTurnoEvento::ACCION_APERTURA);

        $fecha = LdTurnoCaja::findOrFail($id)->fecha->format('Y-m-d');

        $this->actingAs($this->cajero)->get(route('libro-diario.turnos.pdf', $id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload("cierre-caja-{$fecha}-{$id}.pdf");
    }

    /** @test */
    public function no_muestra_turno_de_otro_cajero(): void
    {
        $turno = LdTurnoCaja::factory()->create(['sede_id' => $this->sede->id]);

        $this->actingAs($this->cajero)->getJson(route('libro-diario.turnos.show', $turno))->assertForbidden();
    }

    // ─── Excel ────────────────────────────────────────────────────────────────

    /** @test */
    public function excel_detalla_los_cierres_filtrados_con_el_arqueo_para_el_supervisor(): void
    {
        $turno = LdTurnoCaja::factory()->create([
            'cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id, 'base_inicial' => 100000, 'fecha' => '2026-10-20',
        ]);
        $this->reciboPagado(80000, 'efectivo', ['turno_caja_id' => $turno->id]);
        $this->reciboPagado(50000, 'tarjeta_debito', ['turno_caja_id' => $turno->id, 'origen' => ReciboPago::ORIGEN_INVENTARIOS]);
        LdConsignacion::factory()->create(['turno_caja_id' => $turno->id, 'valor' => 60000]);
        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.turnos.cerrar', $turno), ['efectivo_contado' => 115000])
            ->assertOk();
        LdTurnoCaja::factory()->create(['sede_id' => $this->sede->id, 'fecha' => '2026-09-01']); // fuera del lapso
        LdTurnoCaja::factory()->create(['fecha' => '2026-10-20']); // otra sede

        $response = $this->actingAs($this->usuarioCon(['fin_ldCierreAprobar']))
            ->get(route('libro-diario.turnos.excel', ['fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-31']));

        $response->assertOk()->assertDownload('cierres-caja-'.now()->format('Y-m-d').'.xlsx');
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));

        $libro = $this->leerExcel($response->streamedContent());
        $cierres = $libro->getSheetByName('Cierres')->toArray(null, true, false);
        $this->assertCount(2, $cierres);
        $this->assertSame('Efectivo esperado', $cierres[0][18]);
        $fila = array_combine($cierres[0], $cierres[1]);
        $this->assertEquals($turno->id, $fila['N.° turno']);
        $this->assertSame('Por aprobar', $fila['Estado']);
        $this->assertEquals(130000, $fila['Total ingresos']);
        $this->assertEquals(60000, $fila['Consignaciones']);
        // esperado = 100.000 + 80.000 - 60.000 = 120.000
        $this->assertEquals(120000, $fila['Efectivo esperado']);
        $this->assertEquals(115000, $fila['Efectivo contado']);
        $this->assertEquals(-5000, $fila['Diferencia']);

        $medios = $libro->getSheetByName('Por medio de pago')->toArray(null, true, false);
        $this->assertSame(['Efectivo', 'Tarjeta débito'], array_column(array_slice($medios, 1), 5));
        $this->assertEquals(50000, $medios[2][7]);
    }

    /** @test */
    public function excel_respeta_el_conteo_ciego_y_los_turnos_propios_del_cajero(): void
    {
        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id, 'base_inicial' => 100000]);
        $this->reciboPagado(80000, 'efectivo', ['turno_caja_id' => $turno->id]);
        $this->reciboPagado(50000, 'tarjeta_debito', ['turno_caja_id' => $turno->id]);
        LdTurnoCaja::factory()->create(['sede_id' => $this->sede->id]); // de otro cajero

        $libro = $this->leerExcel($this->actingAs($this->cajero)
            ->get(route('libro-diario.turnos.excel'))
            ->assertOk()
            ->streamedContent());

        $cierres = $libro->getSheetByName('Cierres')->toArray(null, true, false);
        $this->assertCount(2, $cierres);
        $fila = array_combine($cierres[0], $cierres[1]);
        $this->assertSame('Abierto', $fila['Estado']);
        $this->assertNull($fila['Base inicial']);
        $this->assertNull($fila['Total ingresos']);
        $this->assertNull($fila['Efectivo esperado']);
        $this->assertNull($fila['Diferencia']);

        $medios = $libro->getSheetByName('Por medio de pago')->toArray(null, true, false);
        $this->assertSame(['Tarjeta débito'], array_column(array_slice($medios, 1), 5));
    }

    /** @test */
    public function excel_aplica_el_filtro_de_estado(): void
    {
        LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $supervisor = $this->usuarioCon(['fin_ldTurnos']);

        $filtrado = $this->leerExcel($this->actingAs($supervisor)
            ->get(route('libro-diario.turnos.excel', ['status' => LdTurnoCaja::STATUS_CERRADO]))
            ->assertOk()
            ->streamedContent());

        $this->assertCount(1, $filtrado->getSheetByName('Cierres')->toArray());
    }

    /** @test */
    public function excel_valida_los_filtros_y_exige_permiso(): void
    {
        $this->actingAs($this->cajero)
            ->getJson(route('libro-diario.turnos.excel', ['fecha_desde' => '2026-10-20', 'fecha_hasta' => '2026-10-10', 'status' => 9]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_hasta', 'status']);

        $this->actingAs(User::factory()->create())
            ->getJson(route('libro-diario.turnos.excel'))
            ->assertForbidden();
    }

    /** Libro de un xlsx descargado. */
    private function leerExcel(string $contenido): Spreadsheet
    {
        $ruta = tempnam(sys_get_temp_dir(), 'turnos').'.xlsx';
        file_put_contents($ruta, $contenido);
        $libro = IOFactory::load($ruta);
        unlink($ruta);

        return $libro;
    }
}
