<?php

namespace Tests\Unit\Academico\Documentacion;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Services\Academico\Documentacion\DocVariableResolverService;
use App\Services\Academico\Documentacion\NumeroALetrasService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * Pruebas del resolutor de variables y del conversor de números a letras.
 */
class DocVariableResolverServiceTest extends TestCase
{
    private DocVariableResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new DocVariableResolverService(new NumeroALetrasService());
    }

    /** @test */
    public function convierte_valores_a_letras(): void
    {
        $servicio = new NumeroALetrasService();

        $this->assertSame('CERO PESOS M/CTE', $servicio->convertir(0));
        $this->assertSame('CIEN PESOS M/CTE', $servicio->convertir(100));
        $this->assertSame('CIENTO UNO PESOS M/CTE', $servicio->convertir(101));
        $this->assertSame('MIL QUINIENTOS PESOS M/CTE', $servicio->convertir(1500));
        $this->assertSame('VEINTIUN MIL PESOS M/CTE', $servicio->convertir(21000));
        $this->assertSame('UN MILLÓN DE PESOS M/CTE', $servicio->convertir(1000000));
        $this->assertSame('DOS MILLONES DE PESOS M/CTE', $servicio->convertir(2000000));
        $this->assertSame('VEINTIDÓS MIL DIECISÉIS PESOS M/CTE', $servicio->convertir(22016));
        $this->assertSame(
            'UN MILLÓN DOSCIENTOS CINCUENTA MIL PESOS M/CTE',
            $servicio->convertir(1250000)
        );
        $this->assertSame(
            'DOS MILLONES QUINIENTOS MIL PESOS CON CINCUENTA CENTAVOS M/CTE',
            $servicio->convertir(2500000.50)
        );
    }

    /** @test */
    public function el_catalogo_incluye_las_variables_de_la_entidad_y_las_globales(): void
    {
        $catalogo = collect($this->resolver->catalogo(Matricula::class));

        $this->assertTrue($catalogo->contains(
            fn (array $v) => $v['clave'] === 'estudiante.documento' && $v['grupo'] === 'entidad'
        ));
        $this->assertTrue($catalogo->contains(
            fn (array $v) => $v['clave'] === 'instituto.nombre' && $v['grupo'] === 'global'
        ));
    }

    /** @test */
    public function el_catalogo_sin_entidad_solo_trae_variables_globales(): void
    {
        $grupos = collect($this->resolver->catalogo(null))->pluck('grupo')->unique();

        $this->assertEquals(['global'], $grupos->values()->all());
    }

    /** @test */
    public function resuelve_y_formatea_valores_de_la_entidad(): void
    {
        $matricula                  = new Matricula();
        $matricula->monto           = 1250000;
        $matricula->numero_cuotas   = 6;
        $matricula->fecha_matricula = '2026-01-15';

        $valores = $this->resolver->resolver(
            ['monto', 'monto_letras', 'numero_cuotas', 'fecha_matricula', 'fecha_matricula_larga'],
            $matricula
        );

        $this->assertSame('$ 1.250.000', $valores['monto']);
        $this->assertSame('UN MILLÓN DOSCIENTOS CINCUENTA MIL PESOS M/CTE', $valores['monto_letras']);
        $this->assertSame('6', $valores['numero_cuotas']);
        $this->assertSame('15/01/2026', $valores['fecha_matricula']);
        $this->assertSame('15 de enero de 2026', $valores['fecha_matricula_larga']);
    }

    /** @test */
    public function resuelve_variables_globales_desde_el_contexto(): void
    {
        $valores = $this->resolver->resolver(
            ['documento.fecha', 'documento.tipo', 'instituto.nombre'],
            null,
            ['documento' => ['fecha' => '2026-09-24', 'tipo' => 'Contrato de matrícula']]
        );

        $this->assertSame('24/09/2026', $valores['documento.fecha']);
        $this->assertSame('Contrato de matrícula', $valores['documento.tipo']);
        $this->assertSame(config('documentacion.instituto.nombre'), $valores['instituto.nombre']);
    }

    /** @test */
    public function sin_env_el_instituto_toma_el_nombre_y_nit_reales_y_no_el_de_la_app(): void
    {
        $valores = app(DocVariableResolverService::class)->resolver(['instituto.nombre', 'instituto.nit'], null);

        $this->assertSame(config('documentacion.instituto.nombre'), $valores['instituto.nombre']);
        $this->assertNotSame(config('app.name'), $valores['instituto.nombre']);
        $this->assertNotSame('', $valores['instituto.nit']);
    }

    /** @test */
    public function renderiza_sustituyendo_y_escapando_los_valores(): void
    {
        $html = $this->resolver->renderizar(
            '<p>Estudiante: {{ estudiante.name }}</p>',
            ['estudiante.name' => '<script>alert(1)</script>']
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /** @test */
    public function deja_intactas_las_variables_sin_valor_resuelto(): void
    {
        $html = $this->resolver->renderizar('<p>{{ variable.inexistente }}</p>', []);

        $this->assertSame('<p>{{ variable.inexistente }}</p>', $html);
    }

    /** @test */
    public function detecta_las_claves_usadas_en_un_contenido(): void
    {
        $claves = $this->resolver->clavesUsadas(
            '<p>{{ estudiante.name }} — {{curso.nombre}} — {{ estudiante.name }}</p>'
        );

        $this->assertEqualsCanonicalizing(['estudiante.name', 'curso.nombre'], $claves);
    }

    /**
     * Matrícula en memoria con el cuadro de cartera indicado.
     *
     * @param array<int, array{0: int, 1: string, 2?: string}> $cuotas [numero_cuota, fecha_vencimiento, estado]
     * @return Matricula
     */
    private function matriculaConCartera(array $cuotas): Matricula
    {
        $matricula = new Matricula();
        $matricula->setRelation('carteras', new Collection(array_map(
            fn (array $cuota) => new Cartera([
                'numero_cuota'      => $cuota[0],
                'fecha_vencimiento' => $cuota[1],
                'status'            => Cartera::getStatusKey($cuota[2] ?? 'Activa'),
            ]),
            $cuotas
        )));

        return $matricula;
    }

    /** @test */
    public function el_dia_de_pago_es_el_dia_en_que_vencen_las_mensualidades(): void
    {
        // Matriculado el 2 de octubre en un ciclo que inicia el 10.
        $matricula = $this->matriculaConCartera([
            [0, '2026-10-02'],
            [1, '2026-10-10'],
            [2, '2026-11-10'],
            [3, '2026-12-10'],
        ]);

        $valores = $this->resolver->resolver(['dia_pago', 'dia_pago_letras'], $matricula);

        $this->assertSame('10', $valores['dia_pago']);
        $this->assertSame('DIEZ', $valores['dia_pago_letras']);
    }

    /** @test */
    public function el_dia_de_pago_ignora_la_cuota_uno_cuando_vence_el_dia_de_la_matricula(): void
    {
        // El ciclo ya había iniciado: la cuota 1 vence el día de la matrícula.
        $matricula = $this->matriculaConCartera([
            [0, '2026-10-21'],
            [1, '2026-10-21'],
            [2, '2026-11-05'],
            [3, '2026-12-05'],
        ]);

        $this->assertSame(5, $matricula->dia_pago);
    }

    /** @test */
    public function el_dia_de_pago_conserva_los_dias_finales_de_mes_en_meses_cortos(): void
    {
        // Ciclo que inicia el 31 de enero: febrero se corre al 3 de marzo.
        $matricula = $this->matriculaConCartera([
            [1, '2027-01-31'],
            [2, '2027-03-03'],
            [3, '2027-03-31'],
        ]);

        $this->assertSame(31, $matricula->dia_pago);
    }

    /** @test */
    public function el_dia_de_pago_sigue_el_plan_vigente_tras_un_acuerdo_de_pago(): void
    {
        $matricula = $this->matriculaConCartera([
            [2, '2026-02-10', 'Cerrada'],
            [3, '2026-03-10', 'Cerrada'],
            [4, '2026-04-10', 'En Acuerdo'],
            [5, '2026-05-10', 'En Acuerdo'],
            [1, '2026-05-20'],
            [2, '2026-06-20'],
        ]);

        $this->assertSame(20, $matricula->dia_pago);
    }

    /** @test */
    public function el_dia_de_pago_queda_vacio_en_matriculas_de_contado(): void
    {
        $matricula = $this->matriculaConCartera([[0, '2026-10-02']]);

        $this->assertNull($matricula->dia_pago);
        $this->assertSame(
            ['dia_pago' => '', 'dia_pago_letras' => ''],
            $this->resolver->resolver(['dia_pago', 'dia_pago_letras'], $matricula)
        );
    }
}
