<?php

namespace Tests\Feature\Services;

use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Services\Academico\Documentacion\DatosInstitutoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los documentos muestran la dirección y el teléfono de la sede del registro (decisión B17).
 */
class DatosInstitutoServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function toma_direccion_y_telefono_de_la_sede_de_la_matricula(): void
    {
        $sede      = Sede::factory()->create(['direccion' => 'Cra 13 # 60-20', 'telefono' => '6015550000']);
        $matricula = Matricula::factory()->create(['sede_id' => $sede->id, 'status' => 0]);

        $datos = app(DatosInstitutoService::class)->paraEntidad($matricula);

        $this->assertSame('Cra 13 # 60-20', $datos['direccion']);
        $this->assertSame('6015550000', $datos['telefono']);
        $this->assertSame(config('documentacion.instituto.nombre'), $datos['nombre']);
    }

    /** @test */
    public function sin_registro_usa_los_datos_de_respaldo(): void
    {
        config(['documentacion.instituto.direccion' => 'Respaldo 123']);

        $this->assertSame('Respaldo 123', app(DatosInstitutoService::class)->paraEntidad(null)['direccion']);
    }
}
