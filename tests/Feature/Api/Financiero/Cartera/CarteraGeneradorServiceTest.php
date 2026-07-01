<?php

namespace Tests\Feature\Api\Financiero\Cartera;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\Lp\LpPrecioProducto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de CarteraGeneradorService: fechas de vencimiento según ciclo.
 */
class CarteraGeneradorServiceTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Crea un LpPrecioProducto con valores fijos y plan a cuotas.
     */
    private function crearLpCuotas(int $numeroCuotas = 3): LpPrecioProducto
    {
        return LpPrecioProducto::factory()->conValores(
            precioContado: 1600000,
            precioTotal: 1200000,
            matricula: 400000,
            numeroCuotas: $numeroCuotas
        )->create();
    }

    /**
     * Crea un LpPrecioProducto de contado (sin cuotas).
     */
    private function crearLpContado(): LpPrecioProducto
    {
        return LpPrecioProducto::factory()->noFinanciable()->create([
            'precio_contado' => 800000,
        ]);
    }

    /**
     * Crea una matrícula con LP adjunto y la carga para que el hook
     * dispare CarteraGeneradorService, retornando las carteras generadas.
     *
     * @return \Illuminate\Database\Eloquent\Collection<Cartera>
     */
    private function matricularConCiclo(LpPrecioProducto $lp, Ciclo $ciclo, string $fechaMatricula): \Illuminate\Database\Eloquent\Collection
    {
        $matricula = Matricula::factory()->create([
            'lp_precio_producto_id' => null,    // evitar doble disparo
            'ciclo_id'              => $ciclo->id,
            'fecha_matricula'       => $fechaMatricula,
            'status'                => 1,
        ]);

        // Asignar LP y guardar para disparar el hook created del servicio
        $matricula->lp_precio_producto_id = $lp->id;
        $matricula->load('lpPrecioProducto', 'ciclo');
        app(\App\Services\Financiero\CarteraGeneradorService::class)->generarParaMatricula($matricula);

        return Cartera::where('matricula_id', $matricula->id)
            ->orderBy('numero_cuota')
            ->get();
    }

    // ─── Contado ──────────────────────────────────────────────────────────────

    /** @test */
    public function pago_contado_genera_una_sola_cuota_en_fecha_matricula(): void
    {
        $lp    = $this->crearLpContado();
        $ciclo = Ciclo::factory()->create(['fecha_inicio' => '2026-09-01']);

        $carteras = $this->matricularConCiclo($lp, $ciclo, '2026-06-30');

        $this->assertCount(1, $carteras);
        $this->assertEquals(0, $carteras[0]->numero_cuota);
        $this->assertEquals('2026-06-30', $carteras[0]->fecha_vencimiento->toDateString());
    }

    // ─── Ciclo aún no ha iniciado ─────────────────────────────────────────────

    /** @test */
    public function cuota_matricula_usa_fecha_matricula_cuando_ciclo_no_ha_iniciado(): void
    {
        $lp    = $this->crearLpCuotas(3);
        $ciclo = Ciclo::factory()->create(['fecha_inicio' => '2026-09-01']);

        $carteras = $this->matricularConCiclo($lp, $ciclo, '2026-06-30');

        // Cuota 0: cargo de matrícula → siempre en fecha_matricula
        $this->assertEquals(0, $carteras[0]->numero_cuota);
        $this->assertEquals('2026-06-30', $carteras[0]->fecha_vencimiento->toDateString());
    }

    /** @test */
    public function mensualidades_usan_fecha_inicio_ciclo_cuando_ciclo_no_ha_iniciado(): void
    {
        $lp    = $this->crearLpCuotas(3);
        $ciclo = Ciclo::factory()->create(['fecha_inicio' => '2026-09-01']);

        $carteras = $this->matricularConCiclo($lp, $ciclo, '2026-06-30');

        $this->assertCount(4, $carteras); // cuota 0 + cuotas 1,2,3

        // Cuota 1: primera mensualidad → fecha inicio ciclo
        $this->assertEquals(1, $carteras[1]->numero_cuota);
        $this->assertEquals('2026-09-01', $carteras[1]->fecha_vencimiento->toDateString());

        // Cuota 2: un mes después del inicio del ciclo
        $this->assertEquals(2, $carteras[2]->numero_cuota);
        $this->assertEquals('2026-10-01', $carteras[2]->fecha_vencimiento->toDateString());

        // Cuota 3: dos meses después del inicio del ciclo
        $this->assertEquals(3, $carteras[3]->numero_cuota);
        $this->assertEquals('2026-11-01', $carteras[3]->fecha_vencimiento->toDateString());
    }

    // ─── Ciclo ya inició ──────────────────────────────────────────────────────

    /** @test */
    public function primera_mensualidad_usa_fecha_matricula_cuando_ciclo_ya_inicio(): void
    {
        $lp    = $this->crearLpCuotas(3);
        // Ciclo que inició hace un mes
        $ciclo = Ciclo::factory()->create(['fecha_inicio' => '2026-05-01']);

        $carteras = $this->matricularConCiclo($lp, $ciclo, '2026-06-30');

        $this->assertCount(4, $carteras);

        // Cuota 0: siempre en fecha_matricula
        $this->assertEquals('2026-06-30', $carteras[0]->fecha_vencimiento->toDateString());

        // Cuota 1: el ciclo ya inició → se cobra en la fecha de matrícula
        $this->assertEquals(1, $carteras[1]->numero_cuota);
        $this->assertEquals('2026-06-30', $carteras[1]->fecha_vencimiento->toDateString());
    }

    /** @test */
    public function cuotas_restantes_siguen_calendario_ciclo_cuando_ciclo_ya_inicio(): void
    {
        $lp    = $this->crearLpCuotas(3);
        $ciclo = Ciclo::factory()->create(['fecha_inicio' => '2026-05-01']);

        $carteras = $this->matricularConCiclo($lp, $ciclo, '2026-06-30');

        // Cuota 2: ciclo.fecha_inicio + 1 mes
        $this->assertEquals(2, $carteras[2]->numero_cuota);
        $this->assertEquals('2026-06-01', $carteras[2]->fecha_vencimiento->toDateString());

        // Cuota 3: ciclo.fecha_inicio + 2 meses
        $this->assertEquals(3, $carteras[3]->numero_cuota);
        $this->assertEquals('2026-07-01', $carteras[3]->fecha_vencimiento->toDateString());
    }

    // ─── Ciclo sin fecha_inicio (fallback) ───────────────────────────────────

    /** @test */
    public function ciclo_sin_fecha_inicio_usa_fecha_matricula_como_base(): void
    {
        $lp    = $this->crearLpCuotas(2);
        // Ciclo sin fecha de inicio definida → fallback a fecha_matricula
        $ciclo = Ciclo::factory()->create(['fecha_inicio' => null]);

        $carteras = $this->matricularConCiclo($lp, $ciclo, '2026-06-30');

        $this->assertCount(3, $carteras); // cuota 0 + cuotas 1,2

        // Cuota 0 y cuota 1: ambas en fecha_matricula (sin base de ciclo)
        $this->assertEquals('2026-06-30', $carteras[0]->fecha_vencimiento->toDateString());
        $this->assertEquals('2026-06-30', $carteras[1]->fecha_vencimiento->toDateString());

        // Cuota 2: fecha_matricula + 1 mes (base = fecha_matricula)
        $this->assertEquals('2026-07-30', $carteras[2]->fecha_vencimiento->toDateString());
    }

    // ─── Ciclo que inicia exactamente hoy ─────────────────────────────────────

    /** @test */
    public function ciclo_que_inicia_hoy_se_trata_como_ya_iniciado(): void
    {
        $hoy   = now()->toDateString();
        $lp    = $this->crearLpCuotas(2);
        $ciclo = Ciclo::factory()->create(['fecha_inicio' => $hoy]);

        $carteras = $this->matricularConCiclo($lp, $ciclo, $hoy);

        // Cuota 1: ciclo inicia hoy = fecha_matricula, por tanto se cobra hoy
        $this->assertEquals(1, $carteras[1]->numero_cuota);
        $this->assertEquals($hoy, $carteras[1]->fecha_vencimiento->toDateString());
    }
}
