<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Academico\Modulo;
use App\Models\Academico\TipoNotaEsquema;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Quién puede registrar asistencia y notas de un grupo, y a qué estudiantes:
 * B22/B25 (solo el profesor del grupo o el personal de su sede) y
 * B23/B26 (solo estudiantes con matrícula vigente en el grupo) y
 * N18 (programar clases, con la misma regla que asistencia y notas).
 */
class RegistroAcademicoPermisosTest extends TestCase
{
    use RefreshDatabase;

    private User $profesor;
    private User $otroProfesor;
    private User $coordinador;
    private User $estudiante;
    private User $ajeno;
    private Grupo $grupo;
    private AsistenciaClaseProgramada $clase;
    private EsquemaCalificacion $esquema;
    private TipoNotaEsquema $tipoNota;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permisos = ['aca_asistenciaCrear', 'aca_asistenciaEditar', 'aca_notaCrear', 'aca_notaEditar', 'aca_claseProgramar'];
        foreach ($permisos as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }
        Role::create(['name' => User::ROL_PROFESOR])->givePermissionTo($permisos);
        Role::create(['name' => 'coordinador'])->givePermissionTo($permisos);

        $sede = Sede::factory()->create();
        $this->profesor     = $this->usuarioConRol(User::ROL_PROFESOR);
        $this->otroProfesor = $this->usuarioConRol(User::ROL_PROFESOR);
        $this->coordinador  = $this->usuarioConRol('coordinador');
        $this->coordinador->sedes()->attach($sede->id);

        $modulo      = Modulo::factory()->create();
        $this->grupo = Grupo::factory()->create(['sede_id' => $sede->id, 'modulo_id' => $modulo->id, 'profesor_id' => $this->profesor->id]);
        $ciclo       = Ciclo::factory()->create(['sede_id' => $sede->id]);
        $ciclo->grupos()->attach($this->grupo->id, ['orden' => 1]);

        $this->estudiante = User::factory()->create();
        $this->ajeno      = User::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $this->estudiante->id, 'ciclo_id' => $ciclo->id, 'curso_id' => $ciclo->curso_id, 'sede_id' => $sede->id, 'status' => 1]);
        // Matrícula vigente pero en otro ciclo (sin este grupo)
        Matricula::factory()->create(['estudiante_id' => $this->ajeno->id, 'ciclo_id' => Ciclo::factory()->create()->id, 'status' => 1]);

        $this->clase = AsistenciaClaseProgramada::factory()->create([
            'grupo_id' => $this->grupo->id, 'ciclo_id' => $ciclo->id, 'fecha_clase' => today(), 'estado' => 'programada',
        ]);

        $this->esquema  = EsquemaCalificacion::factory()->create(['modulo_id' => $modulo->id, 'grupo_id' => $this->grupo->id, 'profesor_id' => $this->profesor->id]);
        $this->tipoNota = TipoNotaEsquema::factory()->create(['esquema_calificacion_id' => $this->esquema->id, 'peso' => 100]);
    }

    // ── B22 / B23: asistencia ─────────────────────────────────────────────────

    /** @test */
    public function el_profesor_del_grupo_y_la_coordinacion_de_la_sede_registran_asistencia(): void
    {
        foreach ([$this->profesor, $this->coordinador] as $usuario) {
            $this->actingAs($usuario)->postJson('/api/academico/asistencias/masivo', $this->asistencia($this->estudiante))
                ->assertCreated();
        }
    }

    /** @test */
    public function otro_profesor_no_registra_asistencia_en_un_grupo_ajeno(): void
    {
        $this->actingAs($this->otroProfesor)->postJson('/api/academico/asistencias/masivo', $this->asistencia($this->estudiante))
            ->assertForbidden();
    }

    /** @test */
    public function el_personal_sin_la_sede_del_grupo_no_registra_asistencia(): void
    {
        $coordinadorOtraSede = $this->usuarioConRol('coordinador');

        $this->actingAs($coordinadorOtraSede)->postJson('/api/academico/asistencias/masivo', $this->asistencia($this->estudiante))
            ->assertForbidden();
    }

    /** @test */
    public function no_se_registra_asistencia_a_un_estudiante_sin_matricula_en_el_grupo(): void
    {
        $this->actingAs($this->profesor)->postJson('/api/academico/asistencias/masivo', $this->asistencia($this->ajeno))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('asistencias.0.estudiante_id');
    }

    /** @test */
    public function el_reporte_del_grupo_cuenta_clases_y_no_registros(): void
    {
        Permission::create(['name' => 'aca_asistencias', 'descripcion' => 'ver']);
        $this->coordinador->givePermissionTo('aca_asistencias');
        $otro = User::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $otro->id, 'ciclo_id' => $this->clase->ciclo_id, 'status' => 1]);

        // Una clase, dos estudiantes → 2 registros, 1 clase
        $this->actingAs($this->coordinador)->postJson('/api/academico/asistencias/masivo', [
            'clase_programada_id' => $this->clase->id,
            'asistencias' => [
                ['estudiante_id' => $this->estudiante->id, 'estado' => 'presente'],
                ['estudiante_id' => $otro->id, 'estado' => 'ausente'],
            ],
        ])->assertCreated();

        $this->actingAs($this->coordinador)->getJson("/api/academico/asistencias/reporte/grupo/{$this->grupo->id}")
            ->assertOk()
            ->assertJsonPath('data.resumen_grupal.total_clases', 1)
            ->assertJsonPath('data.resumen_grupal.total_estudiantes', 2);
    }

    // ── B25 / B26: notas ──────────────────────────────────────────────────────

    /** @test */
    public function el_profesor_del_grupo_registra_notas(): void
    {
        $this->actingAs($this->profesor)->postJson('/api/academico/notas-estudiantes/masivo', $this->notas($this->estudiante))
            ->assertCreated()
            ->assertJsonPath('data.creadas', 1);
    }

    /** @test */
    public function otro_profesor_no_registra_notas_en_un_grupo_ajeno(): void
    {
        $this->actingAs($this->otroProfesor)->postJson('/api/academico/notas-estudiantes/masivo', $this->notas($this->estudiante))
            ->assertForbidden();
    }

    /** @test */
    public function no_se_registran_notas_a_un_estudiante_sin_matricula_en_el_grupo(): void
    {
        $this->actingAs($this->profesor)->postJson('/api/academico/notas-estudiantes/masivo', $this->notas($this->ajeno))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notas.0.estudiante_id');
    }

    // ── N18: clases programadas ───────────────────────────────────────────────

    /** @test */
    public function otro_profesor_no_programa_ni_modifica_clases_de_un_grupo_ajeno(): void
    {
        $generar = ['grupo_id' => $this->grupo->id, 'ciclo_id' => $this->clase->ciclo_id];
        $nueva   = $generar + ['fecha_clase' => today()->addDay()->toDateString(), 'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00', 'duracion_horas' => 2];

        $this->actingAs($this->otroProfesor)->postJson(route('asistencia-clases-programadas.generar-automaticas'), $generar)
            ->assertForbidden();
        $this->actingAs($this->otroProfesor)->postJson(route('asistencia-clases-programadas.store'), $nueva)
            ->assertForbidden();
        $this->actingAs($this->otroProfesor)->putJson(route('asistencia-clases-programadas.update', $this->clase->id), ['observaciones' => 'Ajena'])
            ->assertForbidden();
        $this->actingAs($this->otroProfesor)->deleteJson(route('asistencia-clases-programadas.destroy', $this->clase->id))
            ->assertForbidden();
        $this->assertNotSoftDeleted('asistencia_clases_programadas', ['id' => $this->clase->id]);

        foreach ([$this->profesor, $this->coordinador] as $usuario) {
            $this->assertNotSame(403, $this->actingAs($usuario)
                ->postJson(route('asistencia-clases-programadas.generar-automaticas'), $generar)->status());
        }
        $this->actingAs($this->profesor)->postJson(route('asistencia-clases-programadas.store'), $nueva)
            ->assertCreated();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function usuarioConRol(string $rol): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    }

    private function asistencia(User $estudiante): array
    {
        return [
            'clase_programada_id' => $this->clase->id,
            'asistencias' => [['estudiante_id' => $estudiante->id, 'estado' => 'presente']],
        ];
    }

    private function notas(User $estudiante): array
    {
        return [
            'grupo_id' => $this->grupo->id,
            'modulo_id' => $this->grupo->modulo_id,
            'esquema_calificacion_id' => $this->esquema->id,
            'tipo_nota_esquema_id' => $this->tipoNota->id,
            'notas' => [['estudiante_id' => $estudiante->id, 'nota' => 4.5]],
        ];
    }
}
