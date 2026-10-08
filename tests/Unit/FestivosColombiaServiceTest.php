<?php

namespace Tests\Unit;

use App\Services\Configuracion\FestivosColombiaService;
use PHPUnit\Framework\TestCase;

/**
 * Verifica el cálculo de festivos de Colombia contra el calendario oficial.
 */
class FestivosColombiaServiceTest extends TestCase
{
    private FestivosColombiaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = new FestivosColombiaService();
    }

    /** @test */
    public function festivos_2026_coinciden_con_el_calendario_oficial(): void
    {
        $this->assertSame([
            '2026-01-01', '2026-01-12', '2026-03-23', '2026-04-02', '2026-04-03',
            '2026-05-01', '2026-05-18', '2026-06-08', '2026-06-15', '2026-06-29',
            '2026-07-20', '2026-08-07', '2026-08-17', '2026-10-12', '2026-11-02',
            '2026-11-16', '2026-12-08', '2026-12-25',
        ], array_column($this->servicio->festivos(2026), 'fecha'));
    }

    /** @test */
    public function festivos_2027_coinciden_con_el_calendario_oficial(): void
    {
        $this->assertSame([
            '2027-01-01', '2027-01-11', '2027-03-22', '2027-03-25', '2027-03-26',
            '2027-05-01', '2027-05-10', '2027-05-31', '2027-06-07', '2027-07-05',
            '2027-07-20', '2027-08-07', '2027-08-16', '2027-10-18', '2027-11-01',
            '2027-11-15', '2027-12-08', '2027-12-25',
        ], array_column($this->servicio->festivos(2027), 'fecha'));
    }

    /** @test */
    public function domingo_de_pascua_de_anios_conocidos(): void
    {
        $this->assertSame('2024-03-31', $this->servicio->domingoDePascua(2024)->toDateString());
        $this->assertSame('2025-04-20', $this->servicio->domingoDePascua(2025)->toDateString());
        $this->assertSame('2026-04-05', $this->servicio->domingoDePascua(2026)->toDateString());
        $this->assertSame('2027-03-28', $this->servicio->domingoDePascua(2027)->toDateString());
    }

    /** @test */
    public function trasladables_que_caen_en_lunes_no_se_mueven_y_los_fijos_nunca_se_mueven(): void
    {
        $festivos2026 = array_column($this->servicio->festivos(2026), 'nombre', 'fecha');

        // 29-jun-2026 y 12-oct-2026 ya son lunes
        $this->assertSame('San Pedro y San Pablo', $festivos2026['2026-06-29']);
        $this->assertSame('Día de la Raza', $festivos2026['2026-10-12']);

        // 7-ago-2027 cae sábado, pero es fijo: no se traslada
        $festivos2027 = array_column($this->servicio->festivos(2027), 'nombre', 'fecha');
        $this->assertSame('Batalla de Boyacá', $festivos2027['2027-08-07']);
    }
}
