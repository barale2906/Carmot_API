<?php

namespace Tests\Feature\Api\Dashboard;

use App\Models\Dashboard\Kpi;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KPI de conteo sin campo y KPI incompleto (B40): el conteo cuenta registros, las demás
 * operaciones exigen campo al guardar y un KPI incompleto responde 422, nunca 500.
 * N27: los registros anulados no cuentan salvo que se filtre por estado.
 */
class KpiConteoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /** @test */
    public function un_kpi_de_conteo_sin_campo_se_guarda_y_se_calcula(): void
    {
        $id = $this->postJson('/api/dashboard/kpis', [
            'name' => 'Grupos', 'code' => 'GRUPOS_CONTEO', 'numerator_model' => 1, 'numerator_operation' => 'count',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/dashboard/kpis/{$id}/compute")->assertOk();
    }

    /** @test */
    public function una_suma_sin_campo_se_rechaza_al_guardar(): void
    {
        $this->postJson('/api/dashboard/kpis', [
            'name' => 'Suma', 'code' => 'SUMA_SIN_CAMPO', 'numerator_model' => 1, 'numerator_operation' => 'sum',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('numerator_field');
    }

    /** @test */
    public function un_kpi_incompleto_responde_422_al_calcular(): void
    {
        $kpi = Kpi::create([
            'name' => 'Roto', 'code' => 'ROTO', 'is_active' => true,
            'numerator_model' => 1, 'numerator_operation' => 'sum', 'numerator_field' => null,
        ]);

        $this->getJson("/api/dashboard/kpis/{$kpi->id}/compute")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El KPI no está completamente configurado.');
    }

    /** @test */
    public function el_catalogo_incluye_matriculas_recibos_cartera_asistencias_y_notas(): void
    {
        $nombres = collect($this->getJson('/api/dashboard/kpis/models')->assertOk()->json('data'))->pluck('display_name');

        foreach (['Matrículas', 'Recibos de pago', 'Cartera', 'Asistencias', 'Notas'] as $nombre) {
            $this->assertContains($nombre, $nombres);
        }
    }

    /** @test */
    public function un_kpi_de_suma_de_recibos_se_calcula(): void
    {
        $id = $this->postJson('/api/dashboard/kpis', [
            'name' => 'Recaudo', 'code' => 'RECAUDO', 'numerator_model' => 5,
            'numerator_operation' => 'sum', 'numerator_field' => 'valor_total', 'date_field' => 'fecha_recibo',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/dashboard/kpis/{$id}/compute")->assertOk();
    }

    /** @test */
    public function el_rango_por_defecto_incluye_lo_registrado_hoy(): void
    {
        $kpi = Kpi::create([
            'name' => 'Grupos hoy', 'code' => 'GRUPOS_HOY', 'is_active' => true, 'period_type' => 'daily',
            'numerator_model' => 1, 'numerator_operation' => 'count',
        ]);

        $rango = $kpi->getDefaultTimeRange();

        // B42: antes terminaba ayer
        $this->assertTrue($rango['start']->isToday());
        $this->assertTrue($rango['end']->isToday());
        $this->assertSame('23:59:59', $rango['end']->format('H:i:s'));
    }

    /** @test */
    public function el_recaudo_no_suma_recibos_anulados_ni_pendientes_salvo_que_se_filtre_el_estado(): void
    {
        $recibo = fn (int $status, int $valor) => ReciboPago::factory()->create([
            'status' => $status, 'valor_total' => $valor, 'fecha_recibo' => today(),
        ]);
        $recibo(ReciboPago::STATUS_CREADO, 100000);
        $recibo(ReciboPago::STATUS_CERRADO, 20000);
        $recibo(ReciboPago::STATUS_ANULADO, 50000);
        $recibo(ReciboPago::STATUS_PENDIENTE_APROBACION, 30000);

        $kpi = Kpi::create([
            'name' => 'Recaudo hoy', 'code' => 'RECAUDO_HOY', 'is_active' => true, 'period_type' => 'daily',
            'numerator_model' => 5, 'numerator_operation' => 'sum', 'numerator_field' => 'valor_total', 'date_field' => 'fecha_recibo',
        ]);

        $this->getJson("/api/dashboard/kpis/{$kpi->id}/compute")
            ->assertOk()->assertJsonPath('data.numerator', 120000);

        $this->getJson("/api/dashboard/kpis/{$kpi->id}/compute?filters[status]=" . ReciboPago::STATUS_ANULADO)
            ->assertOk()->assertJsonPath('data.numerator', 50000);
    }
}
