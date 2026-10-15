<?php

namespace Tests\Feature\Services;

use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Services\Financiero\AjusteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El medio de pago con recargo registra lo realmente cobrado (base + recargo),
 * para que el libro diario y el cierre de caja cuadren con el recibo (B30 / F10).
 */
class AjusteServiceSobrecargoTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function el_recargo_se_suma_al_valor_del_medio_de_pago(): void
    {
        [$recibo, $medio] = $this->reciboConTarjeta(1050000);
        $recargo = Descuento::factory()->sobrecargoPorMedioPago()->porcentual()->create(['valor' => 3]);

        $registro = app(AjusteService::class)->aplicarSobrecargo($recargo, $recibo, $medio);

        $this->assertEquals(1050000, $registro->valor_base);
        $this->assertEquals(31500, $registro->valor_sobrecargo);
        $this->assertEquals(1081500, $medio->fresh()->valor);
    }

    /** @test */
    public function dos_recargos_sobre_el_mismo_medio_se_calculan_sobre_la_base_sin_encadenar(): void
    {
        [$recibo, $medio] = $this->reciboConTarjeta(100000);
        $porcentual = Descuento::factory()->sobrecargoPorMedioPago()->porcentual()->create(['valor' => 3]);
        $fijo       = Descuento::factory()->sobrecargoPorMedioPago()->valorFijo()->create(['valor' => 2000]);

        $servicio = app(AjusteService::class);
        $servicio->aplicarSobrecargo($porcentual, $recibo, $medio);
        $segundo = $servicio->aplicarSobrecargo($fijo, $recibo, $medio->fresh());

        $this->assertEquals(100000, $segundo->valor_base);
        $this->assertEquals(105000, $medio->fresh()->valor);
    }

    /**
     * @return array{0: ReciboPago, 1: ReciboPagoMedioPago}
     */
    private function reciboConTarjeta(float $valor): array
    {
        $recibo = ReciboPago::factory()->academico()->create();
        $medio  = ReciboPagoMedioPago::create([
            'recibo_pago_id' => $recibo->id,
            'medio_pago'     => 'tarjeta_credito',
            'valor'          => $valor,
        ]);

        return [$recibo, $medio];
    }
}
