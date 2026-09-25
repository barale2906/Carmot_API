<?php

namespace Tests\Feature\Api\Academico\Documentacion;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Documentacion\DocPlantilla;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Academico\NotaEstudiante;
use App\Models\Academico\TipoNotaEsquema;
use App\Models\User;
use App\Services\Academico\Documentacion\DocGeneracionService;
use App\Services\Academico\SabanaNotasService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del bloque de sábana de notas y del servicio que lo alimenta,
 * compartido con el endpoint de sábana por estudiante.
 */
class DocBloqueSabanaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private DocTipoDocumento $tipo;

    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_notas', 'descripcion' => 'ver notas']);
        Permission::create(['name' => 'aca_docPlantillas', 'descripcion' => 'ver plantillas']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['aca_notas', 'aca_docPlantillas']);

        $this->tipo = DocTipoDocumento::factory()->conformaMatricula()->create([
            'nombre'         => 'Certificado de notas',
        ]);

        $this->matricula = $this->matriculaConNotas();
    }

    /**
     * Arma una matrícula con un ciclo, un grupo, su esquema y dos notas registradas.
     *
     * @return Matricula
     */
    private function matriculaConNotas(): Matricula
    {
        $matricula = Matricula::factory()->create([
            'fecha_matricula' => '2024-06-15',
            'status'          => 1,
        ]);

        $grupo = Grupo::factory()->create(['status' => 1]);
        $ciclo = Ciclo::find($matricula->ciclo_id);
        $ciclo->grupos()->attach($grupo->id, ['orden' => 1]);

        $esquema = EsquemaCalificacion::create([
            'modulo_id'      => $grupo->modulo_id,
            'grupo_id'       => $grupo->id,
            'profesor_id'    => $grupo->profesor_id,
            'nombre_esquema' => 'Esquema Estándar',
            'status'         => 1,
        ]);

        $tipos = collect([
            ['nombre_tipo' => 'Parcial', 'peso' => 40, 'orden' => 1, 'nota' => 4.0],
            ['nombre_tipo' => 'Examen final', 'peso' => 60, 'orden' => 2, 'nota' => 4.5],
        ])->map(function (array $definicion) use ($esquema) {
            $tipo = TipoNotaEsquema::create([
                'esquema_calificacion_id' => $esquema->id,
                'nombre_tipo'             => $definicion['nombre_tipo'],
                'peso'                    => $definicion['peso'],
                'orden'                   => $definicion['orden'],
                'nota_minima'             => 0,
                'nota_maxima'             => 5,
            ]);

            return ['tipo' => $tipo, 'nota' => $definicion['nota'], 'peso' => $definicion['peso']];
        });

        foreach ($tipos as $registro) {
            NotaEstudiante::create([
                'estudiante_id'           => $matricula->estudiante_id,
                'grupo_id'                => $grupo->id,
                'modulo_id'               => $grupo->modulo_id,
                'esquema_calificacion_id' => $esquema->id,
                'tipo_nota_esquema_id'    => $registro['tipo']->id,
                'nota'                    => $registro['nota'],
                'nota_ponderada'          => $registro['nota'] * $registro['peso'] / 100,
                'fecha_registro'          => '2024-08-01',
                'registrado_por_id'       => $this->usuario->id,
                'status'                  => 1,
            ]);
        }

        return $matricula;
    }

    /** @test */
    public function el_servicio_calcula_la_sabana_de_la_matricula(): void
    {
        $sabana = app(SabanaNotasService::class)->porMatricula($this->matricula);

        $this->assertNotNull($sabana);
        $this->assertCount(1, $sabana['modulos']);
        $this->assertSame(4.3, $sabana['modulos'][0]['nota_final']);
        $this->assertTrue($sabana['modulos'][0]['completo']);
        $this->assertSame(4.3, $sabana['promedio_general']);
    }

    /** @test */
    public function el_endpoint_de_sabana_sigue_respondiendo(): void
    {
        $response = $this->actingAs($this->usuario)
            ->getJson(route('notas-estudiantes.sabana-estudiante', $this->matricula->estudiante_id));

        $response->assertOk()
            ->assertJsonPath('data.promedio_general', 4.3)
            ->assertJsonCount(1, 'data.modulos');
    }

    /** @test */
    public function el_endpoint_de_sabana_responde_404_sin_matricula_activa(): void
    {
        $sinMatricula = User::factory()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('notas-estudiantes.sabana-estudiante', $sinMatricula->id))
            ->assertNotFound();
    }

    /** @test */
    public function el_bloque_imprime_la_sabana_en_el_documento(): void
    {
        $plantilla = DocPlantilla::factory()->activa('2020-01-01')->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => '<p>Resultados:</p>{{ bloque.sabana_notas }}',
        ]);
        $plantilla->bloques()->create([
            'bloque_key'      => 'sabana_notas',
            'columnas'        => ['modulo', 'detalle_notas', 'nota_final'],
            'mostrar_resumen' => true,
        ]);

        $contenido = app(DocGeneracionService::class)->renderizar($plantilla, $this->matricula, $this->usuario);

        $this->assertStringContainsString('<table class="bloque-tabla">', $contenido);
        $this->assertStringContainsString('Parcial: 4,00', $contenido);
        $this->assertStringContainsString('Examen final: 4,50', $contenido);
        $this->assertStringContainsString('4,3', $contenido);
        $this->assertStringContainsString('Promedio general', $contenido);
        $this->assertStringNotContainsString('Esquema de calificación', $contenido);
    }
}
