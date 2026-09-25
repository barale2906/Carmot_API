<?php

namespace Tests\Unit\Academico\Documentacion;

use App\Models\Academico\Matricula;
use App\Services\Academico\Documentacion\DocVariableResolverService;
use App\Services\Academico\Documentacion\NumeroALetrasService;
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
        $this->assertSame('UN MILLON PESOS M/CTE', $servicio->convertir(1000000));
        $this->assertSame(
            'UN MILLON DOSCIENTOS CINCUENTA MIL PESOS M/CTE',
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
        $this->assertSame('UN MILLON DOSCIENTOS CINCUENTA MIL PESOS M/CTE', $valores['monto_letras']);
        $this->assertSame('6', $valores['numero_cuotas']);
        $this->assertSame('15/01/2026', $valores['fecha_matricula']);
        $this->assertSame('15 de enero de 2026', $valores['fecha_matricula_larga']);
    }

    /** @test */
    public function resuelve_variables_globales_desde_el_contexto(): void
    {
        $valores = $this->resolver->resolver(
            ['documento.numero', 'instituto.nombre'],
            null,
            ['documento' => ['numero' => 'CONT-2026-000001']]
        );

        $this->assertSame('CONT-2026-000001', $valores['documento.numero']);
        $this->assertSame(config('documentacion.instituto.nombre'), $valores['instituto.nombre']);
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
}
