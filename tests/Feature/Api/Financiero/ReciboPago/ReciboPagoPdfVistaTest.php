<?php

namespace Tests\Feature\Api\Financiero\ReciboPago;

use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de la plantilla del PDF del recibo de pago (recibos-pago.pdf):
 * encabezado sin estado, datos del estudiante compactos, sin firmas y
 * responsable debajo de los medios de pago.
 */
class ReciboPagoPdfVistaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Renderiza la vista del PDF para un recibo académico con sus relaciones.
     */
    private function renderizar(ReciboPago $recibo): string
    {
        $recibo->load(['sede', 'estudiante', 'cajero', 'matricula.curso', 'conceptosPago', 'mediosPago']);

        return view('recibos-pago.pdf', [
            'recibo'          => $recibo,
            'logoBase64'      => null,
            'itemsInventario' => collect(),
        ])->render();
    }

    private function crearRecibo(): ReciboPago
    {
        $estudiante = User::factory()->create(['documento' => '1098765432']);
        $cajero     = User::factory()->create();
        $curso      = Curso::factory()->create(['nombre' => 'Electricidad Automotriz Básica']);
        $matricula  = Matricula::factory()->create([
            'estudiante_id'         => $estudiante->id,
            'curso_id'              => $curso->id,
            'lp_precio_producto_id' => null,
        ]);

        return ReciboPago::factory()->create([
            'sede_id'       => Sede::factory()->create(['codigo_academico' => 'TEST'])->id,
            'estudiante_id' => $estudiante->id,
            'cajero_id'     => $cajero->id,
            'matricula_id'  => $matricula->id,
            'origen'        => ReciboPago::ORIGEN_ACADEMICO,
        ]);
    }

    public function test_muestra_documento_del_estudiante_y_curso(): void
    {
        $recibo = $this->crearRecibo();

        $html = $this->renderizar($recibo);

        $this->assertStringContainsString($recibo->estudiante->name, $html);
        $this->assertStringContainsString('Doc. 1098765432', $html);
        $this->assertStringContainsString('Curso', $html);
        $this->assertStringContainsString('Electricidad Automotriz Básica', $html);
    }

    public function test_no_muestra_estado_fecha_de_transaccion_ni_firmas(): void
    {
        $html = $this->renderizar($this->crearRecibo());

        $this->assertStringNotContainsString('Estado:', $html);
        $this->assertStringNotContainsString('Fecha de transacción', $html);
        $this->assertStringNotContainsString('Firma del estudiante', $html);
        $this->assertStringNotContainsString('Responsable de caja', $html);
    }

    public function test_muestra_responsable_despues_de_los_medios_de_pago(): void
    {
        $recibo = $this->crearRecibo();

        $html = $this->renderizar($recibo);

        $posMedios       = strpos($html, 'Medios de pago</div>');
        $posResponsable  = strpos($html, 'Responsable: <strong>' . e($recibo->cajero->name));

        $this->assertNotFalse($posMedios);
        $this->assertNotFalse($posResponsable);
        $this->assertGreaterThan($posMedios, $posResponsable);
    }

    public function test_omite_documento_cuando_el_estudiante_no_lo_tiene(): void
    {
        $recibo = $this->crearRecibo();
        $recibo->estudiante->forceFill(['documento' => ''])->saveQuietly();

        $html = $this->renderizar($recibo->fresh());

        $this->assertStringNotContainsString('Doc.', $html);
    }
}
