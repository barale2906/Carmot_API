<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Academico\Modulo;
use App\Models\Academico\NotaEstudiante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Esquemas de calificación y registro masivo de notas desde la pantalla de calificaciones.
 */
class CalificacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $profesor;
    private Grupo $grupo;
    private User $estudiante;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permisos = ['aca_esquemas', 'aca_esquemaCrear', 'aca_esquemaEditar', 'aca_esquemaInactivar', 'aca_notas', 'aca_notaCrear'];
        foreach ($permisos as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->profesor = User::factory()->create();
        $this->profesor->givePermissionTo($permisos);

        $matricula = Matricula::factory()->create(['status' => 1]);
        $this->estudiante = User::find($matricula->estudiante_id);
        $this->grupo = Grupo::factory()->create(['profesor_id' => $this->profesor->id, 'status' => 1]);
        Ciclo::find($matricula->ciclo_id)->grupos()->attach($this->grupo->id, ['orden' => 1]);
    }

    private function crearEsquema(array $tipos = null): EsquemaCalificacion
    {
        $id = $this->actingAs($this->profesor)->postJson(route('esquemas-calificacion.store'), [
            'modulo_id'      => $this->grupo->modulo_id,
            'grupo_id'       => $this->grupo->id,
            'nombre_esquema' => 'Esquema del grupo',
            'tipos_nota'     => $tipos ?? [
                ['nombre_tipo' => 'Parcial', 'peso' => 40, 'orden' => 1],
                ['nombre_tipo' => 'Final', 'peso' => 60, 'orden' => 2],
            ],
        ])->assertCreated()->json('data.id');

        return EsquemaCalificacion::with('tiposNota')->find($id);
    }

    private function registrarNota(EsquemaCalificacion $esquema, int $tipoId, float $nota, array $overrides = [])
    {
        return $this->actingAs($this->profesor)->postJson(route('notas-estudiantes.masivo'), array_merge([
            'grupo_id'                => $this->grupo->id,
            'modulo_id'               => $this->grupo->modulo_id,
            'esquema_calificacion_id' => $esquema->id,
            'tipo_nota_esquema_id'    => $tipoId,
            'notas'                   => [['estudiante_id' => $this->estudiante->id, 'nota' => $nota]],
        ], $overrides));
    }

    /** @test */
    public function crear_esquema_exige_que_los_pesos_sumen_100(): void
    {
        $this->actingAs($this->profesor)->postJson(route('esquemas-calificacion.store'), [
            'modulo_id'      => $this->grupo->modulo_id,
            'nombre_esquema' => 'Descuadrado',
            'tipos_nota'     => [['nombre_tipo' => 'Único', 'peso' => 70, 'orden' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('tipos_nota');
    }

    /** @test */
    public function show_update_y_destroy_resuelven_el_esquema_de_la_ruta(): void
    {
        $esquema = $this->crearEsquema();

        $this->actingAs($this->profesor)
            ->getJson(route('esquemas-calificacion.show', $esquema->id))
            ->assertOk()
            ->assertJsonPath('data.id', $esquema->id);

        $this->actingAs($this->profesor)
            ->putJson(route('esquemas-calificacion.update', $esquema->id), ['nombre_esquema' => 'Renombrado'])
            ->assertOk()
            ->assertJsonPath('data.nombre_esquema', 'Renombrado');

        $this->actingAs($this->profesor)
            ->deleteJson(route('esquemas-calificacion.destroy', $esquema->id))
            ->assertOk();
        $this->assertSoftDeleted('esquema_calificacions', ['id' => $esquema->id]);
    }

    /** @test */
    public function no_permite_quitar_un_tipo_de_nota_con_calificaciones(): void
    {
        $esquema = $this->crearEsquema();
        [$parcial, $final] = $esquema->tiposNota->sortBy('orden')->values()->all();
        $this->registrarNota($esquema, $parcial->id, 4.0)->assertCreated();

        $this->actingAs($this->profesor)
            ->putJson(route('esquemas-calificacion.update', $esquema->id), [
                'tipos_nota' => [['id' => $final->id, 'nombre_tipo' => 'Final', 'peso' => 100, 'orden' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipos_nota');

        // Quitar el tipo sin notas sí se permite.
        $this->actingAs($this->profesor)
            ->putJson(route('esquemas-calificacion.update', $esquema->id), [
                'tipos_nota' => [['id' => $parcial->id, 'nombre_tipo' => 'Parcial', 'peso' => 100, 'orden' => 1]],
            ])
            ->assertOk();
    }

    /** @test */
    public function registro_masivo_crea_y_luego_actualiza_la_nota_y_la_sabana_la_refleja(): void
    {
        $esquema = $this->crearEsquema();
        [$parcial, $final] = $esquema->tiposNota->sortBy('orden')->values()->all();

        $this->registrarNota($esquema, $parcial->id, 3.0)->assertCreated()->assertJsonPath('data.creadas', 1);
        $this->registrarNota($esquema, $parcial->id, 4.0)->assertCreated()->assertJsonPath('data.actualizadas', 1);
        $this->registrarNota($esquema, $final->id, 5.0)->assertCreated();

        $this->assertSame(1, NotaEstudiante::where('tipo_nota_esquema_id', $parcial->id)->count());

        $this->actingAs($this->profesor)
            ->getJson(route('notas-estudiantes.sabana-grupal', [$this->grupo->id, $this->grupo->modulo_id]))
            ->assertOk()
            ->assertJsonPath('data.estudiantes.0.nota_final', 4.6)
            ->assertJsonPath('data.estudiantes.0.completo', true);
    }

    /** @test */
    public function registro_masivo_reporta_notas_fuera_de_rango_sin_guardarlas(): void
    {
        $esquema = $this->crearEsquema();

        $this->registrarNota($esquema, $esquema->tiposNota->first()->id, 7.5)
            ->assertCreated()
            ->assertJsonPath('data.creadas', 0)
            ->assertJsonPath('data.errores', 1);
    }

    /** @test */
    public function registro_masivo_valida_coherencia_de_grupo_modulo_esquema_y_tipo(): void
    {
        $esquema = $this->crearEsquema();
        $otroGrupo = Grupo::factory()->create(['modulo_id' => Modulo::factory()->create()->id]);
        $otroEsquema = EsquemaCalificacion::create([
            'modulo_id' => $otroGrupo->modulo_id, 'grupo_id' => $otroGrupo->id,
            'profesor_id' => $this->profesor->id, 'nombre_esquema' => 'Ajeno', 'status' => 1,
        ]);
        $tipoAjeno = $otroEsquema->tiposNota()->create(['nombre_tipo' => 'X', 'peso' => 100, 'orden' => 1, 'nota_minima' => 0, 'nota_maxima' => 5]);

        $this->registrarNota($esquema, $tipoAjeno->id, 4.0, ['modulo_id' => $otroGrupo->modulo_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['modulo_id', 'esquema_calificacion_id', 'tipo_nota_esquema_id']);
    }

    /** @test */
    public function sin_permiso_no_registra_notas(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('notas-estudiantes.masivo'), [])
            ->assertForbidden();
    }
}
