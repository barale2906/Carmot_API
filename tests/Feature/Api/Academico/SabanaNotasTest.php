<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Academico\Modulo;
use App\Models\Academico\NotaEstudiante;
use App\Models\Academico\TipoNotaEsquema;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas de las sábanas de notas por estudiante y por grupo.
 *
 * Ambos endpoints resuelven el esquema activo con el scope activoParaModuloGrupo,
 * que devuelve un Builder: sin `->first()` el esquema nunca llegaba resuelto y la
 * petición fallaba. Estas pruebas cubren ese camino.
 */
class SabanaNotasTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Grupo $grupo;

    private EsquemaCalificacion $esquema;

    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_notas', 'descripcion' => 'ver notas']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo('aca_notas');

        $this->matricula = Matricula::factory()->create(['status' => 1]);

        $this->grupo = Grupo::factory()->create(['status' => 1]);
        Ciclo::find($this->matricula->ciclo_id)->grupos()->attach($this->grupo->id, ['orden' => 1]);

        $this->esquema = EsquemaCalificacion::create([
            'modulo_id'      => $this->grupo->modulo_id,
            'grupo_id'       => $this->grupo->id,
            'profesor_id'    => $this->grupo->profesor_id,
            'nombre_esquema' => 'Esquema Estándar',
            'status'         => 1,
        ]);

        $this->registrarNotas([
            ['nombre_tipo' => 'Parcial', 'peso' => 40, 'orden' => 1, 'nota' => 4.0],
            ['nombre_tipo' => 'Examen final', 'peso' => 60, 'orden' => 2, 'nota' => 4.5],
        ]);
    }

    /**
     * Crea los tipos de nota del esquema y registra la nota del estudiante en cada uno.
     *
     * @param array<int, array<string, mixed>> $definiciones
     * @return void
     */
    private function registrarNotas(array $definiciones): void
    {
        foreach ($definiciones as $definicion) {
            $tipo = TipoNotaEsquema::create([
                'esquema_calificacion_id' => $this->esquema->id,
                'nombre_tipo'             => $definicion['nombre_tipo'],
                'peso'                    => $definicion['peso'],
                'orden'                   => $definicion['orden'],
                'nota_minima'             => 0,
                'nota_maxima'             => 5,
            ]);

            NotaEstudiante::create([
                'estudiante_id'           => $this->matricula->estudiante_id,
                'grupo_id'                => $this->grupo->id,
                'modulo_id'               => $this->grupo->modulo_id,
                'esquema_calificacion_id' => $this->esquema->id,
                'tipo_nota_esquema_id'    => $tipo->id,
                'nota'                    => $definicion['nota'],
                'nota_ponderada'          => $definicion['nota'] * $definicion['peso'] / 100,
                'fecha_registro'          => '2024-08-01',
                'registrado_por_id'       => $this->usuario->id,
                'status'                  => 1,
            ]);
        }
    }

    /** @test */
    public function la_sabana_por_estudiante_devuelve_sus_modulos_y_promedio(): void
    {
        $response = $this->actingAs($this->usuario)
            ->getJson(route('notas-estudiantes.sabana-estudiante', $this->matricula->estudiante_id));

        $response->assertOk()
            ->assertJsonPath('data.promedio_general', 4.3)
            ->assertJsonPath('data.total_modulos', 1)
            ->assertJsonPath('data.modulos_completos', 1)
            ->assertJsonPath('data.modulos.0.nota_final', 4.3)
            ->assertJsonPath('data.modulos.0.completo', true);
    }

    /** @test */
    public function la_sabana_por_estudiante_responde_404_sin_matricula_activa(): void
    {
        $sinMatricula = User::factory()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('notas-estudiantes.sabana-estudiante', $sinMatricula->id))
            ->assertNotFound();
    }

    /** @test */
    public function la_sabana_grupal_devuelve_los_estudiantes_con_sus_notas(): void
    {
        $response = $this->actingAs($this->usuario)
            ->getJson(route('notas-estudiantes.sabana-grupal', [
                'grupoId'  => $this->grupo->id,
                'moduloId' => $this->grupo->modulo_id,
            ]));

        $response->assertOk()
            ->assertJsonPath('data.grupo.id', $this->grupo->id)
            ->assertJsonPath('data.modulo.id', $this->grupo->modulo_id)
            ->assertJsonPath('data.total_estudiantes', 1)
            ->assertJsonPath('data.estudiantes_con_notas', 1)
            ->assertJsonPath('data.estudiantes.0.estudiante.id', $this->matricula->estudiante_id)
            ->assertJsonPath('data.estudiantes.0.nota_final', 4.3)
            ->assertJsonPath('data.estudiantes.0.completo', true)
            ->assertJsonCount(2, 'data.estudiantes.0.tipos_nota');
    }

    /** @test */
    public function la_sabana_grupal_incluye_el_esquema_de_calificacion(): void
    {
        $response = $this->actingAs($this->usuario)
            ->getJson(route('notas-estudiantes.sabana-grupal', [
                'grupoId'  => $this->grupo->id,
                'moduloId' => $this->grupo->modulo_id,
            ]));

        $response->assertOk()
            ->assertJsonPath('data.esquema_calificacion.id', $this->esquema->id)
            ->assertJsonPath('data.esquema_calificacion.nombre_esquema', 'Esquema Estándar');
    }

    /** @test */
    public function la_sabana_grupal_marca_pendientes_los_tipos_sin_nota(): void
    {
        TipoNotaEsquema::create([
            'esquema_calificacion_id' => $this->esquema->id,
            'nombre_tipo'             => 'Trabajo final',
            'peso'                    => 0,
            'orden'                   => 3,
            'nota_minima'             => 0,
            'nota_maxima'             => 5,
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('notas-estudiantes.sabana-grupal', [
                'grupoId'  => $this->grupo->id,
                'moduloId' => $this->grupo->modulo_id,
            ]));

        $response->assertOk()
            ->assertJsonPath('data.estudiantes.0.completo', false)
            ->assertJsonPath('data.estudiantes.0.tipos_nota.2.pendiente', true)
            ->assertJsonPath('data.estudiantes.0.tipos_nota.2.nota', null);
    }

    /** @test */
    public function la_sabana_grupal_rechaza_un_modulo_que_no_pertenece_al_grupo(): void
    {
        $otroModulo = Modulo::factory()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('notas-estudiantes.sabana-grupal', [
                'grupoId'  => $this->grupo->id,
                'moduloId' => $otroModulo->id,
            ]))
            ->assertStatus(422);
    }

    /** @test */
    public function la_sabana_grupal_responde_404_sin_esquema_activo(): void
    {
        $this->esquema->update(['status' => 0]);

        $this->actingAs($this->usuario)
            ->getJson(route('notas-estudiantes.sabana-grupal', [
                'grupoId'  => $this->grupo->id,
                'moduloId' => $this->grupo->modulo_id,
            ]))
            ->assertNotFound();
    }

    /** @test */
    public function la_sabana_grupal_expone_los_tipos_de_nota_del_esquema_como_columnas(): void
    {
        $response = $this->actingAs($this->usuario)
            ->getJson(route('notas-estudiantes.sabana-grupal', [
                'grupoId'  => $this->grupo->id,
                'moduloId' => $this->grupo->modulo_id,
            ]));

        $response->assertOk()
            ->assertJsonCount(2, 'data.esquema_calificacion.tipos_nota')
            ->assertJsonPath('data.esquema_calificacion.tipos_nota.0.nombre_tipo', 'Parcial')
            ->assertJsonPath('data.esquema_calificacion.tipos_nota.1.nombre_tipo', 'Examen final')
            ->assertJsonPath('data.esquema_calificacion.pesos_validos', true);

        // El orden de las columnas del esquema coincide con el de cada estudiante.
        $this->assertSame(
            array_column($response->json('data.esquema_calificacion.tipos_nota'), 'nombre_tipo'),
            array_column($response->json('data.estudiantes.0.tipos_nota'), 'nombre_tipo')
        );
    }

    /** @test */
    public function un_estudiante_inexistente_responde_500_y_no_404(): void
    {
        // Comportamiento documentado: el findOrFail queda dentro del try del
        // controlador, así que no se distingue por código de estado de "sin matrícula".
        $this->actingAs($this->usuario)
            ->getJson(route('notas-estudiantes.sabana-estudiante', 999999))
            ->assertStatus(500);
    }

    /** @test */
    public function las_sabanas_exigen_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('notas-estudiantes.sabana-estudiante', $this->matricula->estudiante_id))
            ->assertForbidden();

        $this->actingAs($sinPermiso)
            ->getJson(route('notas-estudiantes.sabana-grupal', [
                'grupoId'  => $this->grupo->id,
                'moduloId' => $this->grupo->modulo_id,
            ]))
            ->assertForbidden();
    }
}
