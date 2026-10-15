<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Services\Academico\Documentacion\DocVariableResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La matrícula guarda el número de cuotas de su precio de lista, para que la variable
 * {{numero_cuotas}} del contrato no salga vacía (F08).
 */
class MatriculaNumeroCuotasTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function toma_el_numero_de_cuotas_del_precio_de_lista_y_lo_muestra_en_el_contrato(): void
    {
        $precio    = LpPrecioProducto::factory()->financiable()->create(['numero_cuotas' => 2]);
        $matricula = Matricula::factory()->create(['lp_precio_producto_id' => $precio->id, 'status' => 0]);

        $this->assertSame(2, $matricula->fresh()->numero_cuotas);

        $valores = app(DocVariableResolverService::class)->resolver(['numero_cuotas'], $matricula->fresh());
        $this->assertSame('2', $valores['numero_cuotas']);
    }

    /** @test */
    public function sin_precio_de_lista_no_tiene_numero_de_cuotas(): void
    {
        $matricula = Matricula::factory()->create(['lp_precio_producto_id' => null, 'status' => 0]);

        $this->assertNull($matricula->fresh()->numero_cuotas);
    }
}
