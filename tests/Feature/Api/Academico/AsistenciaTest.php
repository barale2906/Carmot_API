<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Toma de asistencia por clase: grupos disponibles, lista del grupo (con la mora de cada
 * estudiante), registro masivo con justificación aparte del estado, y el listado filtrable
 * con su descarga en Excel.
 */
class AsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $profesor;
    private Grupo $grupo;
    private Ciclo $ciclo;
    private AsistenciaClaseProgramada $clase;
    private User $estudianteA;
    private User $estudianteB;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['aca_asistencias', 'aca_asistenciaCrear'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->profesor = User::factory()->create();
        $this->profesor->givePermissionTo(['aca_asistencias', 'aca_asistenciaCrear']);

        $this->ciclo = Ciclo::factory()->create([
            'status'       => 1,
            'fecha_inicio' => now()->subWeek()->toDateString(),
            'fecha_fin'    => now()->addMonth()->toDateString(),
        ]);
        $this->grupo = Grupo::factory()->create(['profesor_id' => $this->profesor->id, 'status' => 1]);
        $this->ciclo->grupos()->attach($this->grupo->id, ['orden' => 1]);

        $this->estudianteA = User::factory()->create();
        $this->estudianteB = User::factory()->create();
        foreach ([$this->estudianteA, $this->estudianteB] as $estudiante) {
            Matricula::factory()->create([
                'ciclo_id'      => $this->ciclo->id,
                'curso_id'      => $this->ciclo->curso_id,
                'estudiante_id' => $estudiante->id,
                'status'        => 1,
            ]);
        }

        $this->clase = AsistenciaClaseProgramada::factory()->create([
            'grupo_id'    => $this->grupo->id,
            'ciclo_id'    => $this->ciclo->id,
            'fecha_clase' => now()->toDateString(),
            'estado'      => 'programada',
        ]);
    }

    private function registrar(array $estados)
    {
        return $this->actingAs($this->profesor)->postJson(route('asistencias.masivo'), [
            'clase_programada_id' => $this->clase->id,
            'asistencias'         => collect($estados)
                ->map(fn ($estado, $id) => ['estudiante_id' => $id, 'estado' => $estado])
                ->values()
                ->all(),
        ]);
    }

    /** @test */
    public function lista_asistencia_trae_estudiantes_y_clases_del_grupo(): void
    {
        $this->actingAs($this->profesor)
            ->getJson(route('asistencias.lista-asistencia', $this->grupo->id))
            ->assertOk()
            ->assertJsonPath('data.total_estudiantes', 2)
            ->assertJsonPath('data.total_clases', 1);
    }

    /** @test */
    public function lista_asistencia_excluye_estudiantes_aplazados(): void
    {
        Matricula::where('estudiante_id', $this->estudianteB->id)->update(['estado_academico' => Matricula::ESTADO_APLAZADO]);

        $this->actingAs($this->profesor)
            ->getJson(route('asistencias.lista-asistencia', $this->grupo->id))
            ->assertJsonPath('data.total_estudiantes', 1)
            ->assertJsonPath('data.estudiantes.0.id', $this->estudianteA->id);
    }

    /** @test */
    public function registro_masivo_crea_asistencias_y_marca_la_clase_como_dictada(): void
    {
        $this->registrar([$this->estudianteA->id => 'presente', $this->estudianteB->id => 'ausente'])
            ->assertCreated()
            ->assertJsonPath('meta.creadas', 2)
            ->assertJsonPath('meta.actualizadas', 0);

        $this->assertDatabaseHas('asistencias', [
            'estudiante_id' => $this->estudianteB->id,
            'clase_programada_id' => $this->clase->id,
            'estado' => 'ausente',
            'modulo_id' => $this->grupo->modulo_id,
            'curso_id' => $this->ciclo->curso_id,
            'registrado_por_id' => $this->profesor->id,
        ]);
        $this->assertSame('dictada', $this->clase->fresh()->estado);
    }

    /** @test */
    public function volver_a_guardar_la_lista_corrige_sin_duplicar(): void
    {
        $this->registrar([$this->estudianteA->id => 'presente', $this->estudianteB->id => 'ausente']);

        $this->registrar([$this->estudianteA->id => 'presente', $this->estudianteB->id => 'tardanza'])
            ->assertCreated()
            ->assertJsonPath('meta.creadas', 0)
            ->assertJsonPath('meta.actualizadas', 2);

        $this->assertSame(2, Asistencia::where('clase_programada_id', $this->clase->id)->count());
        $this->assertDatabaseHas('asistencias', ['estudiante_id' => $this->estudianteB->id, 'estado' => 'tardanza']);
    }

    /** @test */
    public function una_asistencia_eliminada_se_restaura_al_volver_a_registrarla(): void
    {
        $this->registrar([$this->estudianteA->id => 'presente']);
        Asistencia::where('estudiante_id', $this->estudianteA->id)->first()->delete();

        $this->registrar([$this->estudianteA->id => 'tardanza'])
            ->assertCreated()
            ->assertJsonPath('meta.actualizadas', 1);

        $this->assertDatabaseHas('asistencias', ['estudiante_id' => $this->estudianteA->id, 'estado' => 'tardanza', 'deleted_at' => null]);
    }

    /** @test */
    public function rechaza_clase_cancelada_estudiante_repetido_y_estado_invalido(): void
    {
        $this->clase->update(['estado' => 'cancelada']);

        $this->actingAs($this->profesor)->postJson(route('asistencias.masivo'), [
            'clase_programada_id' => $this->clase->id,
            'asistencias' => [
                ['estudiante_id' => $this->estudianteA->id, 'estado' => 'presente'],
                ['estudiante_id' => $this->estudianteA->id, 'estado' => 'volando'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clase_programada_id', 'asistencias.0.estudiante_id', 'asistencias.1.estado']);
    }

    /** @test */
    public function no_se_toma_asistencia_de_una_clase_futura(): void
    {
        $this->clase->update(['fecha_clase' => now()->addDay()->toDateString()]);

        $this->registrar([$this->estudianteA->id => 'presente'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('clase_programada_id');
    }

    /** @test */
    public function reporte_de_grupo_calcula_porcentaje_por_horas(): void
    {
        $this->registrar([$this->estudianteA->id => 'presente', $this->estudianteB->id => 'ausente']);

        $estudiantes = collect($this->actingAs($this->profesor)
            ->getJson(route('asistencias.reporte-grupo', $this->grupo->id))
            ->assertOk()
            ->json('data.estudiantes'))->keyBy('estudiante.id');

        $this->assertEquals(100, $estudiantes[$this->estudianteA->id]['porcentaje_asistencia']);
        $this->assertEquals(0, $estudiantes[$this->estudianteB->id]['porcentaje_asistencia']);
    }

    /** @test */
    public function sin_permiso_de_crear_no_registra(): void
    {
        $soloLectura = User::factory()->create();
        $soloLectura->givePermissionTo('aca_asistencias');

        $this->actingAs($soloLectura)->postJson(route('asistencias.masivo'), [])->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson(route('asistencias.lista-asistencia', $this->grupo->id))->assertForbidden();
    }
    /** Registra la lista de la clase con registros completos (estado, justificación...). */
    private function registrarRegistros(array $registros)
    {
        return $this->actingAs($this->profesor)->postJson(route('asistencias.masivo'), [
            'clase_programada_id' => $this->clase->id,
            'asistencias'         => $registros,
        ]);
    }

    /** Cuota vencida y con saldo del estudiante: lo deja en mora. */
    private function ponerEnMora(User $estudiante, float $saldo = 150000): void
    {
        Cartera::factory()->create([
            'matricula_id'      => Matricula::where('estudiante_id', $estudiante->id)->value('id'),
            'estudiante_id'     => $estudiante->id,
            'sede_id'           => $this->grupo->sede_id,
            'valor'             => $saldo,
            'saldo'             => $saldo,
            'fecha_vencimiento' => now()->subDays(5)->toDateString(),
            'status'            => Cartera::getStatusKey('Activa'),
        ]);
    }

    /** Segunda clase del grupo en la fecha indicada, con asistencia del estudiante A. */
    private function asistenciaEnOtraClase(string $fecha, string $estado = 'presente', ?Grupo $grupo = null): Asistencia
    {
        $grupo ??= $this->grupo;
        $clase = AsistenciaClaseProgramada::factory()->create([
            'grupo_id'    => $grupo->id,
            'ciclo_id'    => $this->ciclo->id,
            'fecha_clase' => $fecha,
            'estado'      => 'dictada',
        ]);

        return Asistencia::factory()->create([
            'estudiante_id'       => $this->estudianteA->id,
            'clase_programada_id' => $clase->id,
            'grupo_id'            => $grupo->id,
            'ciclo_id'            => $this->ciclo->id,
            'modulo_id'           => $grupo->modulo_id,
            'curso_id'            => $this->ciclo->curso_id,
            'estado'              => $estado,
            'justificada'         => false,
            'registrado_por_id'   => $this->profesor->id,
        ]);
    }

    /** @test */
    public function la_ausencia_justificada_sigue_siendo_falla(): void
    {
        $this->registrarRegistros([
            ['estudiante_id' => $this->estudianteA->id, 'estado' => 'presente'],
            ['estudiante_id' => $this->estudianteB->id, 'estado' => 'ausente', 'justificada' => true, 'justificacion' => 'Cita médica'],
        ])->assertCreated();

        $this->assertDatabaseHas('asistencias', [
            'estudiante_id' => $this->estudianteB->id,
            'estado'        => 'ausente',
            'justificada'   => true,
            'justificacion' => 'Cita médica',
        ]);

        $estudiantes = collect($this->actingAs($this->profesor)
            ->getJson(route('asistencias.reporte-grupo', $this->grupo->id))
            ->assertOk()
            ->json('data.estudiantes'))->keyBy('estudiante.id');

        $this->assertEquals(0, $estudiantes[$this->estudianteB->id]['porcentaje_asistencia']);
        $this->assertSame(1, $estudiantes[$this->estudianteB->id]['ausentes']);
        $this->assertSame(1, $estudiantes[$this->estudianteB->id]['justificadas']);

        $this->actingAs($this->profesor)
            ->getJson(route('asistencias.reporte-estudiante', $this->estudianteB->id))
            ->assertOk()
            ->assertJsonPath('data.resumen.ausentes', 1)
            ->assertJsonPath('data.resumen.justificadas', 1)
            ->assertJsonPath('data.resumen.porcentaje_asistencia', 0);
    }

    /** @test */
    public function la_justificacion_exige_motivo_y_no_aplica_a_presentes_ni_existe_el_estado_justificado(): void
    {
        $this->registrarRegistros([
            ['estudiante_id' => $this->estudianteA->id, 'estado' => 'presente', 'justificada' => true, 'justificacion' => 'x'],
            ['estudiante_id' => $this->estudianteB->id, 'estado' => 'ausente', 'justificada' => true],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asistencias.0.justificada', 'asistencias.1.justificacion']);

        $this->registrar([$this->estudianteA->id => 'justificado'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('asistencias.0.estado');
    }

    /** @test */
    public function una_tardanza_tambien_se_puede_justificar(): void
    {
        $this->registrarRegistros([
            ['estudiante_id' => $this->estudianteA->id, 'estado' => 'tardanza', 'justificada' => true, 'justificacion' => 'Paro de transporte'],
        ])->assertCreated();

        $this->assertDatabaseHas('asistencias', ['estudiante_id' => $this->estudianteA->id, 'estado' => 'tardanza', 'justificada' => true]);
    }

    /** @test */
    public function al_editar_quitar_la_justificacion_borra_el_motivo_y_no_se_justifica_un_presente(): void
    {
        $editor = User::factory()->create();
        Permission::create(['name' => 'aca_asistenciaEditar', 'descripcion' => 'aca_asistenciaEditar']);
        $this->profesor->givePermissionTo('aca_asistenciaEditar');

        $this->registrarRegistros([
            ['estudiante_id' => $this->estudianteA->id, 'estado' => 'ausente', 'justificada' => true, 'justificacion' => 'Calamidad'],
        ]);
        $asistencia = Asistencia::where('estudiante_id', $this->estudianteA->id)->firstOrFail();

        $this->actingAs($this->profesor)->putJson(route('asistencias.update', $asistencia->id), ['estado' => 'presente'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('justificada');

        $this->actingAs($this->profesor)->putJson(route('asistencias.update', $asistencia->id), ['justificada' => false])
            ->assertOk()
            ->assertJsonPath('data.justificada', false)
            ->assertJsonPath('data.justificacion', null);

        $this->actingAs($editor)->putJson(route('asistencias.update', $asistencia->id), ['estado' => 'presente'])->assertForbidden();
    }

    /** @test */
    public function la_lista_de_asistencia_indica_quien_esta_en_mora(): void
    {
        $this->ponerEnMora($this->estudianteA, 150000);
        // Cuota por vencer: no es mora
        Cartera::factory()->create([
            'matricula_id'      => Matricula::where('estudiante_id', $this->estudianteB->id)->value('id'),
            'estudiante_id'     => $this->estudianteB->id,
            'sede_id'           => $this->grupo->sede_id,
            'saldo'             => 90000,
            'fecha_vencimiento' => now()->addDays(10)->toDateString(),
            'status'            => Cartera::getStatusKey('Activa'),
        ]);

        $estudiantes = collect($this->actingAs($this->profesor)
            ->getJson(route('asistencias.lista-asistencia', $this->grupo->id))
            ->assertOk()
            ->json('data.estudiantes'))->keyBy('id');

        $this->assertTrue($estudiantes[$this->estudianteA->id]['en_mora']);
        $this->assertEquals(150000, $estudiantes[$this->estudianteA->id]['saldo_vencido']);
        $this->assertFalse($estudiantes[$this->estudianteB->id]['en_mora']);
        $this->assertEquals(0, $estudiantes[$this->estudianteB->id]['saldo_vencido']);
    }

    /** @test */
    public function el_listado_filtra_por_grupo_sede_curso_estudiante_y_fecha_de_la_clase(): void
    {
        $this->registrar([$this->estudianteA->id => 'presente', $this->estudianteB->id => 'ausente']);
        $haceDiezDias = now()->subDays(10)->toDateString();
        $this->asistenciaEnOtraClase($haceDiezDias);
        $otroGrupo = Grupo::factory()->create([
            'status'      => 1,
            'sede_id'     => \App\Models\Configuracion\Sede::factory()->create()->id,
            'profesor_id' => User::factory()->create()->id,
        ]);
        $this->asistenciaEnOtraClase(now()->subDays(3)->toDateString(), 'ausente', $otroGrupo);
        $this->ponerEnMora($this->estudianteA);

        $listar = fn (array $filtros) => $this->actingAs($this->profesor)
            ->getJson(route('asistencias.index', $filtros))
            ->assertOk();

        $listar(['grupo_id' => $this->grupo->id])->assertJsonPath('meta.total', 3);
        $listar(['sede_id' => $otroGrupo->sede_id])->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.grupo.id', $otroGrupo->id);
        $listar(['curso_id' => $this->ciclo->curso_id])->assertJsonPath('meta.total', 4);
        $listar(['estudiante_id' => $this->estudianteB->id])->assertJsonPath('meta.total', 1);
        $listar(['fecha' => $haceDiezDias])->assertJsonPath('meta.total', 1);
        $listar(['fecha_desde' => now()->subDays(5)->toDateString(), 'fecha_hasta' => now()->toDateString()])->assertJsonPath('meta.total', 3);

        // Clase más reciente primero, con la mora y la sede de cada registro
        $datos = $listar(['grupo_id' => $this->grupo->id])->json('data');
        $this->assertSame($haceDiezDias, end($datos)['clase_programada']['fecha_clase']);
        $porEstudiante = collect($datos)->where('clase_programada_id', $this->clase->id)->keyBy('estudiante_id');
        $this->assertTrue($porEstudiante[$this->estudianteA->id]['en_mora']);
        $this->assertFalse($porEstudiante[$this->estudianteB->id]['en_mora']);
        $this->assertSame($this->grupo->sede_id, $porEstudiante[$this->estudianteA->id]['grupo']['sede']['id']);
    }

    /** @test */
    public function el_listado_busca_por_nombre_o_documento_del_estudiante(): void
    {
        $this->registrar([$this->estudianteA->id => 'presente', $this->estudianteB->id => 'ausente']);

        $this->actingAs($this->profesor)
            ->getJson(route('asistencias.index', ['estudiante' => $this->estudianteB->documento]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.estudiante_id', $this->estudianteB->id);
    }

    /** @test */
    public function los_filtros_ofrecen_las_sedes_del_usuario_y_los_grupos_que_puede_ver(): void
    {
        $this->registrar([$this->estudianteA->id => 'presente']);
        $ajeno = Grupo::factory()->create([
            'status'      => 1,
            'sede_id'     => \App\Models\Configuracion\Sede::factory()->create()->id,
            'profesor_id' => User::factory()->create()->id,
        ]);
        $this->asistenciaEnOtraClase(now()->subDay()->toDateString(), 'presente', $ajeno);

        // Quien solo es profesor ve solo sus grupos
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => User::ROL_PROFESOR, 'guard_name' => 'web']);
        $this->profesor->assignRole(User::ROL_PROFESOR);
        $this->actingAs($this->profesor)->getJson(route('asistencias.filters'))
            ->assertOk()
            ->assertJsonCount(1, 'data.grupos')
            ->assertJsonPath('data.grupos.0.id', $this->grupo->id)
            ->assertJsonPath('data.cursos.0.id', $this->ciclo->curso_id)
            ->assertJsonCount(3, 'data.estados');

        // El personal ve los grupos de sus sedes
        $coordinador = User::factory()->create();
        $coordinador->givePermissionTo('aca_asistencias');
        $coordinador->sedes()->attach($ajeno->sede_id);
        $this->actingAs($coordinador)->getJson(route('asistencias.filters'))
            ->assertOk()
            ->assertJsonPath('data.sedes.0.id', $ajeno->sede_id)
            ->assertJsonCount(1, 'data.grupos')
            ->assertJsonPath('data.grupos.0.id', $ajeno->id);

        $this->actingAs(User::factory()->create())->getJson(route('asistencias.filters'))->assertForbidden();
    }

    /** @test */
    public function el_listado_rechaza_un_rango_de_fechas_invertido(): void
    {
        $this->actingAs($this->profesor)
            ->getJson(route('asistencias.index', ['fecha_desde' => '2026-10-10', 'fecha_hasta' => '2026-10-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_hasta');
    }

    /** @test */
    public function el_excel_trae_las_asistencias_de_los_filtros_aplicados(): void
    {
        $this->registrarRegistros([
            ['estudiante_id' => $this->estudianteA->id, 'estado' => 'presente'],
            ['estudiante_id' => $this->estudianteB->id, 'estado' => 'ausente', 'justificada' => true, 'justificacion' => 'Cita médica'],
        ]);
        $this->asistenciaEnOtraClase(now()->subDays(10)->toDateString());
        $this->ponerEnMora($this->estudianteB, 80000);

        $respuesta = $this->actingAs($this->profesor)
            ->get(route('asistencias.excel', ['grupo_id' => $this->grupo->id, 'fecha' => now()->toDateString()]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $archivo = tempnam(sys_get_temp_dir(), 'asis') . '.xlsx';
        file_put_contents($archivo, $respuesta->streamedContent());
        $filas = IOFactory::load($archivo)->getActiveSheet()->toArray();
        unlink($archivo);

        $this->assertSame('Fecha clase', $filas[0][0]);
        $this->assertCount(3, $filas); // encabezado + 2 asistencias del día
        $filaB = collect($filas)->first(fn ($fila) => $fila[6] === $this->estudianteB->name);
        $this->assertSame(['Ausente', 'Sí', 'Cita médica'], [$filaB[8], $filaB[9], $filaB[10]]);
        $this->assertSame('Sí', $filaB[12]);
    }

    /** @test */
    public function el_excel_exige_permiso_y_a_un_alumno_solo_le_trae_lo_suyo(): void
    {
        $this->actingAs(User::factory()->create())->get(route('asistencias.excel'))->assertForbidden();

        $this->registrar([$this->estudianteA->id => 'presente', $this->estudianteB->id => 'ausente']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => User::ROL_ALUMNO, 'guard_name' => 'web']);
        $this->estudianteA->assignRole(User::ROL_ALUMNO);
        $this->estudianteA->givePermissionTo('aca_asistencias');

        $respuesta = $this->actingAs($this->estudianteA)->get(route('asistencias.excel'))->assertOk();
        $archivo = tempnam(sys_get_temp_dir(), 'asis') . '.xlsx';
        file_put_contents($archivo, $respuesta->streamedContent());
        $filas = IOFactory::load($archivo)->getActiveSheet()->toArray();
        unlink($archivo);

        $this->assertCount(2, $filas);
        $this->assertSame($this->estudianteA->name, $filas[1][6]);
    }
}
