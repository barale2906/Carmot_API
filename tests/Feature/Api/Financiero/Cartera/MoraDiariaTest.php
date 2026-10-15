<?php

namespace Tests\Feature\Api\Financiero\Cartera;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\Descuento\Descuento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mora diaria (financiero:aplicar-mora-diaria): se cobra sobre las cuotas vencidas con
 * saldo, una vez por día, y nunca sobre las de costo cero (N11).
 */
class MoraDiariaTest extends TestCase
{
    use RefreshDatabase;

    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();

        Descuento::factory()->sobrecargoMora()->vigente()->create(['valor' => 1]);
        $this->matricula = Matricula::factory()->create(['status' => 1]);
    }

    private function cuotaVencida(int $numeroCuota, float $valor): Cartera
    {
        return Cartera::factory()->create([
            'matricula_id'      => $this->matricula->id,
            'sede_id'           => $this->matricula->sede_id,
            'estudiante_id'     => $this->matricula->estudiante_id,
            'numero_cuota'      => $numeroCuota,
            'valor'             => $valor,
            'saldo'             => $valor,
            'abono'             => 0,
            'descuento'         => 0,
            'mora_acumulada'    => 0,
            'fecha_vencimiento' => now()->subDays(3)->toDateString(),
            'status'            => Cartera::getStatusKey('Activa'),
        ]);
    }

    /** @test */
    public function cobra_mora_a_las_cuotas_vencidas_una_vez_por_dia(): void
    {
        $cuota = $this->cuotaVencida(1, 400000);

        $this->artisan('financiero:aplicar-mora-diaria')->assertSuccessful();
        $this->artisan('financiero:aplicar-mora-diaria')->assertSuccessful();

        $this->assertEquals(4000, $cuota->fresh()->mora_acumulada);
    }

    /** @test */
    public function no_cobra_mora_a_una_cuota_con_descuento_del_100(): void
    {
        // Promoción de matrícula del 100 %: la cuota 0 no cuesta nada
        Descuento::factory()->vigente()->promocionMatricula()->create([
            'tipo'       => Descuento::TIPO_PORCENTUAL,
            'valor'      => 100,
            'aplicacion' => Descuento::APLICACION_MATRICULA,
        ]);
        $matricula = $this->cuotaVencida(0, 200000);
        $cuota1    = $this->cuotaVencida(1, 400000);

        $this->artisan('financiero:aplicar-mora-diaria')->assertSuccessful();

        $this->assertEquals(0, $matricula->fresh()->mora_acumulada);
        $this->assertEquals(4000, $cuota1->fresh()->mora_acumulada);
    }
}
