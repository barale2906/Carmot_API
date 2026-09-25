<?php

namespace Tests\Feature\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use App\Services\Academico\Documentacion\DocPlantillaVersionResolverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de la resolución de la versión de plantilla aplicable.
 *
 * Es el núcleo del requisito de que un documento firmado conserve el contenido
 * vigente en su fecha de referencia aunque después se publiquen versiones nuevas.
 */
class DocPlantillaVersionResolverTest extends TestCase
{
    use RefreshDatabase;

    private DocPlantillaVersionResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(DocPlantillaVersionResolverService::class);
    }

    /** @test */
    public function resuelve_la_version_vigente_en_la_fecha_de_referencia(): void
    {
        $tipo = DocTipoDocumento::factory()->conformaMatricula()->create();

        $v2024 = DocPlantilla::factory()->historica('2024-01-01', '2024-12-31')
            ->create(['tipo_documento_id' => $tipo->id, 'version' => 1]);
        $v2025 = DocPlantilla::factory()->historica('2025-01-01', '2025-12-31')
            ->create(['tipo_documento_id' => $tipo->id, 'version' => 2]);
        $vigente = DocPlantilla::factory()->activa('2026-01-01')
            ->create(['tipo_documento_id' => $tipo->id, 'version' => 3]);

        $this->assertSame(
            $v2024->id,
            $this->resolver->resolver($tipo, Carbon::parse('2024-06-15'))->id
        );
        $this->assertSame(
            $v2025->id,
            $this->resolver->resolver($tipo, Carbon::parse('2025-03-01'))->id
        );
        $this->assertSame(
            $vigente->id,
            $this->resolver->resolver($tipo, Carbon::parse('2026-09-24'))->id
        );
    }

    /** @test */
    public function un_tipo_que_no_conforma_matricula_usa_la_version_vigente_hoy(): void
    {
        $tipo = DocTipoDocumento::factory()->sinFecha()->create();

        DocPlantilla::factory()->historica('2024-01-01', '2024-12-31')
            ->create(['tipo_documento_id' => $tipo->id, 'version' => 1]);
        $vigente = DocPlantilla::factory()->activa('2025-01-01')
            ->create(['tipo_documento_id' => $tipo->id, 'version' => 2]);

        $this->assertSame(
            $vigente->id,
            $this->resolver->resolver($tipo, Carbon::parse('2024-06-15'))->id
        );
    }

    /** @test */
    public function no_resuelve_versiones_en_proceso_ni_aprobadas(): void
    {
        $tipo = DocTipoDocumento::factory()->conformaMatricula()->create();

        DocPlantilla::factory()->create(['tipo_documento_id' => $tipo->id, 'version' => 1]);
        DocPlantilla::factory()->aprobada()->create(['tipo_documento_id' => $tipo->id, 'version' => 2]);

        $this->assertNull($this->resolver->resolver($tipo, Carbon::today()));
    }

    /** @test */
    public function no_resuelve_fuera_de_la_ventana_de_vigencia(): void
    {
        $tipo = DocTipoDocumento::factory()->conformaMatricula()->create();

        DocPlantilla::factory()->activa('2026-01-01')->create(['tipo_documento_id' => $tipo->id, 'version' => 1]);

        $this->assertNull($this->resolver->resolver($tipo, Carbon::parse('2025-12-31')));
    }

    /** @test */
    public function la_fecha_de_referencia_se_lee_del_campo_configurado(): void
    {
        $tipo = DocTipoDocumento::factory()->conformaMatricula()->create();

        $matricula                  = new Matricula();
        $matricula->fecha_matricula = '2024-05-20';

        $this->assertSame(
            '2024-05-20',
            $this->resolver->fechaReferencia($tipo, $matricula)->toDateString()
        );
    }

    /** @test */
    public function un_tipo_sin_campo_de_fecha_usa_la_fecha_de_impresion(): void
    {
        $tipo = DocTipoDocumento::factory()->create([
            'conforma_matricula'     => true,
            'campo_fecha_referencia' => null,
        ]);

        $this->assertSame(
            Carbon::today()->toDateString(),
            $this->resolver->fechaReferencia($tipo, null)->toDateString()
        );
    }

    /** @test */
    public function un_tipo_que_no_conforma_matricula_no_tiene_fecha_de_referencia(): void
    {
        $tipo = DocTipoDocumento::factory()->sinFecha()->create();

        $this->assertNull($this->resolver->fechaReferencia($tipo, new Matricula()));
    }
}
