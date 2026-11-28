<?php

namespace Tests\Feature\Api\Traslados;

use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\AsistenciaReposicion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\Traslados\MatriculaTraslado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Api\Traslados\Concerns\CreaEscenarioTraslado;
use Tests\TestCase;

/**
 * Traslado de matrículas: los cuatro tipos, la deducción del tipo, las validaciones del destino,
 * los permisos y el acceso por sede. Lo financiero y la nivelación tienen sus propias pruebas.
 */
class TrasladoTest extends TestCase
{
    use CreaEscenarioTraslado, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEscenarioTraslado();
    }

    // ─── Los cuatro tipos ─────────────────────────────────────────────────────

    /** @test */
    public function cambio_de_programacion_mueve_la_matricula_a_otro_ciclo_de_la_misma_sede_y_curso(): void
    {
        $this->cicloA1->refresh()->update(['inscritos' => 1]);

        $this->trasladar([
            'sede_id' => $this->sedeA->id,
            'ciclo_id' => $this->cicloA2->id,
            'lp_precio_producto_id' => null,
        ])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'programacion')
            ->assertJsonPath('data.tipo_texto', 'Cambio de programación')
            ->assertJsonPath('data.origen.ciclo.nombre', 'Ciclo A1')
            ->assertJsonPath('data.destino.ciclo.nombre', 'Ciclo A2')
            ->assertJsonPath('data.motivo', 'Cambio de domicilio del estudiante');

        $matricula = $this->matricula->fresh();
        $this->assertSame($this->cicloA2->id, $matricula->ciclo_id);
        $this->assertSame($this->sedeA->id, $matricula->sede_id);
        $this->assertSame(0, $this->cicloA1->fresh()->inscritos);
        $this->assertSame(1, $this->cicloA2->fresh()->inscritos);
        $this->assertDatabaseHas('matricula_traslados', [
            'matricula_id' => $this->matricula->id, 'tipo' => 'programacion', 'usuario_id' => $this->coordinador->id,
        ]);
    }

    /** @test */
    public function cambio_de_curso_conserva_la_matricula_y_mueve_el_vinculo_con_el_curso(): void
    {
        $this->assertTrue($this->matricula->estudiante->cursos()->whereKey($this->cursoX->id)->exists());

        $this->trasladar([
            'sede_id' => $this->sedeA->id,
            'curso_id' => $this->cursoY->id,
            'ciclo_id' => $this->cicloAY->id,
            'lp_precio_producto_id' => $this->lpAY->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'curso')
            ->assertJsonPath('data.nivelacion.aplica', false);

        $matricula = $this->matricula->fresh();
        $this->assertSame($this->cursoY->id, $matricula->curso_id);
        $this->assertSame($this->cicloAY->id, $matricula->ciclo_id);
        $this->assertSame($this->matricula->id, MatriculaTraslado::first()->matricula_id);
        $this->assertFalse($matricula->estudiante->cursos()->whereKey($this->cursoX->id)->exists());
        $this->assertTrue($matricula->estudiante->cursos()->whereKey($this->cursoY->id)->exists());
    }

    /** @test */
    public function cambio_de_sede_con_el_mismo_curso_elige_el_ciclo_de_la_nueva_sede(): void
    {
        $this->trasladar()
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'sede')
            ->assertJsonPath('data.origen.sede.id', $this->sedeA->id)
            ->assertJsonPath('data.destino.sede.id', $this->sedeB->id);

        $matricula = $this->matricula->fresh();
        $this->assertSame($this->sedeB->id, $matricula->sede_id);
        $this->assertSame($this->cicloB1->id, $matricula->ciclo_id);
        $this->assertSame($this->progBX->id, $matricula->programacion_id, 'La programación del ciclo se resuelve sola cuando es la única.');
        $this->assertSame($this->lpBX->id, $matricula->lp_precio_producto_id);
    }

    /** @test */
    public function cambio_de_sede_y_de_curso_a_la_vez(): void
    {
        $this->trasladar([
            'curso_id' => $this->cursoY->id,
            'ciclo_id' => $this->cicloBY->id,
            'lp_precio_producto_id' => $this->lpBY->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'sede_curso');

        $matricula = $this->matricula->fresh();
        $this->assertSame($this->sedeB->id, $matricula->sede_id);
        $this->assertSame($this->cursoY->id, $matricula->curso_id);
    }

    // ─── Simulación y opciones ────────────────────────────────────────────────

    /** @test */
    public function simular_no_escribe_nada(): void
    {
        $this->simular()
            ->assertOk()
            ->assertJsonPath('data.tipo', 'sede')
            ->assertJsonPath('data.origen.ciclo.nombre', 'Ciclo A1')
            ->assertJsonPath('data.destino.ciclo.nombre', 'Ciclo B1')
            ->assertJsonStructure(['data' => ['tipo', 'tipo_texto', 'fecha', 'origen', 'destino', 'financiero' => ['valor_pagado', 'saldo_nuevo', 'costo_traslado', 'cuotas_nuevas'], 'nivelacion']]);

        $this->assertDatabaseCount('matricula_traslados', 0);
        $this->assertSame($this->cicloA1->id, $this->matricula->fresh()->ciclo_id);
        $this->assertCount(3, $this->carteras($this->sedeA->id, 0));
    }

    /** @test */
    public function simular_no_exige_el_motivo(): void
    {
        $datos = $this->datosTraslado();
        unset($datos['motivo']);

        $this->actingAs($this->coordinador)->postJson(route('tra-traslados.simular'), $datos)->assertOk();
    }

    /** @test */
    public function opciones_lista_los_ciclos_disponibles_con_su_programacion_y_los_precios(): void
    {
        $this->crearCiclo($this->sedeB, $this->cursoX, 'Ciclo finalizado')->update(['fecha_fin' => now()->subDay()->toDateString()]);
        $this->crearCiclo($this->sedeB, $this->cursoX, 'Ciclo inactivo')->update(['status' => 0]);
        $this->crearCiclo($this->sedeB, $this->cursoX, 'Ciclo con notas cerradas')->update(['notas_cerradas_at' => now()]);

        $respuesta = $this->actingAs($this->coordinador)
            ->getJson(route('tra-traslados.opciones', [
                'matricula_id' => $this->matricula->id, 'sede_id' => $this->sedeB->id, 'curso_id' => $this->cursoX->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.ciclos')
            ->assertJsonPath('data.ciclos.0.nombre', 'Ciclo B1')
            ->assertJsonPath('data.ciclos.0.programaciones.0.id', $this->progBX->id)
            ->assertJsonPath('data.precios.0.id', $this->lpBX->id)
            ->assertJsonPath('data.precios.0.precio_total', 1300000);

        $this->assertCount(1, $respuesta->json('data.precios'));
    }

    /** @test */
    public function opciones_excluye_el_ciclo_actual_de_la_matricula(): void
    {
        $this->actingAs($this->coordinador)
            ->getJson(route('tra-traslados.opciones', [
                'matricula_id' => $this->matricula->id, 'sede_id' => $this->sedeA->id, 'curso_id' => $this->cursoX->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.ciclos')
            ->assertJsonPath('data.ciclos.0.nombre', 'Ciclo A2');
    }

    // ─── Validaciones del destino ─────────────────────────────────────────────

    /** @test */
    public function rechaza_un_destino_igual_al_origen(): void
    {
        $this->trasladar([
            'sede_id' => $this->sedeA->id, 'ciclo_id' => $this->cicloA1->id, 'lp_precio_producto_id' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors('ciclo_id');
    }

    /** @test */
    public function rechaza_un_ciclo_que_no_es_de_la_sede_y_curso_de_destino(): void
    {
        $this->trasladar(['ciclo_id' => $this->cicloA2->id])
            ->assertUnprocessable()->assertJsonValidationErrors('ciclo_id');
    }

    /** @test */
    public function rechaza_ciclos_de_destino_finalizados_inactivos_o_con_notas_cerradas(): void
    {
        $this->cicloB1->update(['fecha_fin' => now()->subDay()->toDateString()]);
        $this->trasladar()->assertUnprocessable()->assertJsonValidationErrors('ciclo_id');

        $this->cicloB1->update(['fecha_fin' => '2027-06-30', 'status' => 0]);
        $this->trasladar()->assertUnprocessable()->assertJsonValidationErrors('ciclo_id');

        $this->cicloB1->update(['status' => 1, 'notas_cerradas_at' => now()]);
        $this->trasladar()->assertUnprocessable()->assertJsonValidationErrors('ciclo_id');

        $this->assertDatabaseCount('matricula_traslados', 0);
    }

    /** @test */
    public function rechaza_trasladar_una_matricula_que_no_esta_cursando(): void
    {
        foreach ([[2, Matricula::ESTADO_ACTIVO], [0, Matricula::ESTADO_DESERTADO]] as [$status, $estado]) {
            $this->matricula->update(['status' => $status, 'estado_academico' => $estado]);

            $this->trasladar()->assertUnprocessable()->assertJsonValidationErrors('matricula_id');
        }
    }

    /** @test */
    public function permite_trasladar_una_matricula_aplazada(): void
    {
        $this->matricula->update(['estado_academico' => Matricula::ESTADO_APLAZADO]);

        $this->trasladar()->assertCreated();
    }

    /** @test */
    public function rechaza_cambiar_a_un_curso_en_el_que_el_estudiante_ya_esta_matriculado(): void
    {
        $this->crearMatricula([
            'estudiante_id' => $this->matricula->estudiante_id,
            'curso_id' => $this->cursoY->id,
            'sede_id' => $this->sedeA->id,
            'ciclo_id' => $this->cicloAY->id,
            'lp_precio_producto_id' => null,
            'programacion_id' => null,
        ]);

        $this->trasladar([
            'sede_id' => $this->sedeA->id, 'curso_id' => $this->cursoY->id, 'ciclo_id' => $this->cicloAY->id, 'lp_precio_producto_id' => $this->lpAY->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('curso_id');
    }

    /** @test */
    public function rechaza_una_programacion_de_otra_sede(): void
    {
        $this->trasladar(['programacion_id' => $this->progAX->id])
            ->assertUnprocessable()->assertJsonValidationErrors('programacion_id');
    }

    /** @test */
    public function pide_elegir_la_programacion_cuando_el_ciclo_tiene_varias_posibles(): void
    {
        $this->crearProgramacion($this->sedeB, $this->cursoX, 1);

        $this->trasladar()->assertUnprocessable()->assertJsonValidationErrors('programacion_id');

        $this->trasladar(['programacion_id' => $this->progBX->id])->assertCreated();
        $this->assertSame($this->progBX->id, $this->matricula->fresh()->programacion_id);
    }

    /** @test */
    public function rechaza_una_fecha_anterior_a_la_matricula_o_posterior_al_fin_del_ciclo(): void
    {
        $this->trasladar(['fecha' => now()->subMonths(2)->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('fecha');
        $this->trasladar(['fecha' => '2027-12-01'])->assertUnprocessable()->assertJsonValidationErrors('fecha');
    }

    /** @test */
    public function el_motivo_es_obligatorio_al_registrar(): void
    {
        $this->trasladar(['motivo' => ''])->assertUnprocessable()->assertJsonValidationErrors('motivo');
    }

    // ─── Reposiciones ─────────────────────────────────────────────────────────

    /** @test */
    public function cancela_las_reposiciones_pendientes_del_ciclo_de_origen(): void
    {
        $grupo = Grupo::factory()->create(['sede_id' => $this->sedeA->id, 'status' => 1]);
        $clase = fn (string $fecha, $ciclo) => AsistenciaClaseProgramada::factory()->create([
            'grupo_id' => $grupo->id, 'ciclo_id' => $ciclo->id, 'fecha_clase' => $fecha, 'estado' => 'programada',
        ]);

        $futura = $clase(now()->addDays(3)->toDateString(), $this->cicloA1);
        $destino = $clase(now()->addDays(4)->toDateString(), $this->cicloA2);
        $reposicion = AsistenciaReposicion::create([
            'estudiante_id' => $this->matricula->estudiante_id,
            'clase_origen_id' => $futura->id,
            'clase_destino_id' => $destino->id,
            'registrada_por_id' => $this->coordinador->id,
        ]);

        $this->trasladar()->assertCreated();

        $this->assertSoftDeleted('asistencia_reposiciones', ['id' => $reposicion->id]);
        $this->assertSame(1, MatriculaTraslado::first()->reposiciones_canceladas);
    }

    // ─── Permisos y sedes ─────────────────────────────────────────────────────

    /** @test */
    public function sin_permisos_no_se_puede_consultar_ni_trasladar(): void
    {
        $this->actingAs($this->sinPermisos)->getJson(route('tra-traslados.index'))->assertForbidden();
        $this->actingAs($this->sinPermisos)->getJson(route('tra-traslados.opciones', ['matricula_id' => 1, 'sede_id' => 1, 'curso_id' => 1]))->assertForbidden();
        $this->simular(usuario: $this->sinPermisos)->assertForbidden();
        $this->trasladar(usuario: $this->sinPermisos)->assertForbidden();
    }

    /** @test */
    public function quien_solo_puede_ver_no_puede_registrar(): void
    {
        $lector = User::factory()->create();
        $lector->givePermissionTo('tra_traslados');
        $lector->sedes()->attach([$this->sedeA->id, $this->sedeB->id]);

        $this->simular(usuario: $lector)->assertOk();
        $this->trasladar(usuario: $lector)->assertForbidden();
    }

    /** @test */
    public function exige_tener_asignada_la_sede_de_origen_o_la_de_destino(): void
    {
        $this->coordinador->sedes()->detach();

        $this->trasladar()->assertUnprocessable()->assertJsonValidationErrors('sede_id');

        // Con solo la sede de destino asignada sí puede recibir al estudiante
        $this->coordinador->sedes()->attach($this->sedeB->id);
        $this->trasladar()->assertCreated();
    }

    /** @test */
    public function el_listado_solo_trae_traslados_de_las_sedes_del_usuario(): void
    {
        $this->trasladar()->assertCreated();

        $this->actingAs($this->coordinador)->getJson(route('tra-traslados.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.estudiante.id', $this->matricula->estudiante_id);

        $otraSede = User::factory()->create();
        $otraSede->givePermissionTo('tra_traslados');
        $otraSede->sedes()->attach(Sede::factory()->create()->id);

        $this->actingAs($otraSede)->getJson(route('tra-traslados.index'))->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($otraSede)->getJson(route('tra-traslados.show', MatriculaTraslado::first()))->assertForbidden();
    }

    /** @test */
    public function el_listado_filtra_por_tipo_estudiante_y_fechas(): void
    {
        $this->trasladar()->assertCreated();

        $filtrar = fn (array $filtros) => $this->actingAs($this->coordinador)->getJson(route('tra-traslados.index', $filtros))->assertOk()->json('meta.total');

        $this->assertSame(1, $filtrar(['tipo' => 'sede']));
        $this->assertSame(0, $filtrar(['tipo' => 'curso']));
        $this->assertSame(1, $filtrar(['estudiante_id' => $this->matricula->estudiante_id]));
        $this->assertSame(0, $filtrar(['estudiante_id' => $this->coordinador->id]));
        $this->assertSame(1, $filtrar(['sede_id' => $this->sedeB->id, 'fecha_inicio' => now()->toDateString(), 'fecha_fin' => now()->toDateString()]));
        $this->assertSame(0, $filtrar(['fecha_inicio' => now()->addDay()->toDateString()]));
    }

    /** @test */
    public function se_puede_consultar_un_traslado_y_el_historial_de_la_matricula(): void
    {
        $this->trasladar()->assertCreated();
        $traslado = MatriculaTraslado::first();

        $this->actingAs($this->coordinador)->getJson(route('tra-traslados.show', $traslado))
            ->assertOk()
            ->assertJsonPath('data.id', $traslado->id)
            ->assertJsonPath('data.destino.sede.nombre', $this->sedeB->nombre);

        $this->actingAs($this->coordinador)->getJson(route('tra-traslados.index', ['matricula_id' => $this->matricula->id]))
            ->assertOk()->assertJsonCount(1, 'data');
        $this->assertCount(1, $this->matricula->traslados);
    }

    // ─── La edición de la matrícula ya no cambia la ubicación ─────────────────

    /** @test */
    public function la_edicion_de_la_matricula_rechaza_cambiar_sede_curso_ciclo_o_programacion(): void
    {
        $editor = User::factory()->create();
        Permission::create(['name' => 'aca_matriculaEditar', 'descripcion' => 'editar']);
        $editor->givePermissionTo('aca_matriculaEditar');

        $cambios = [
            'sede_id' => $this->sedeB->id,
            'curso_id' => $this->cursoY->id,
            'ciclo_id' => $this->cicloA2->id,
            'programacion_id' => $this->progBX->id,
        ];
        foreach ($cambios as $campo => $valor) {
            $this->actingAs($editor)->putJson(route('matriculas.update', $this->matricula), [$campo => $valor])
                ->assertUnprocessable()->assertJsonValidationErrors($campo);
        }

        // Enviar los mismos valores que ya tiene (el formulario manda todo) sí se acepta
        $this->actingAs($editor)->putJson(route('matriculas.update', $this->matricula), [
            'sede_id' => $this->sedeA->id, 'curso_id' => $this->cursoX->id, 'ciclo_id' => $this->cicloA1->id,
            'programacion_id' => $this->progAX->id, 'observaciones' => 'Sin cambios de ubicación',
        ])->assertOk();
    }

    // ─── Menú ─────────────────────────────────────────────────────────────────

    /** @test */
    public function el_menu_muestra_traslados_solo_con_permiso(): void
    {
        $menu = fn (User $u) => collect($this->actingAs($u)->getJson('/api/menu')->assertOk()->json('data'))->pluck('id');

        $this->assertContains('traslados', $menu($this->coordinador));
        $this->assertNotContains('traslados', $menu($this->sinPermisos));
    }
}
