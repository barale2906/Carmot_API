<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\CicloCierreNota;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Academico\Concerns\CreaEscenarioNotas;
use Tests\TestCase;

/**
 * Cierre de notas por ciclo: con el ciclo cerrado no se registran, editan ni eliminan notas
 * ni recuperaciones de sus estudiantes; solo quien tiene `aca_notaReabrir` puede reabrirlo,
 * con motivo, y todo queda en la bitácora.
 */
class CierreNotasCicloTest extends TestCase
{
    use RefreshDatabase, CreaEscenarioNotas;

    protected function setUp(): void
    {
        parent::setUp();

        config(['academico.nota_aprobatoria' => 3.0]);
        $this->crearEscenarioNotas();
    }

    /**
     * Payload de registro masivo de notas para el estudiante del escenario.
     *
     * @param float $nota
     * @return array<string, mixed>
     */
    private function notaMasiva(float $nota): array
    {
        return [
            'grupo_id'                => $this->grupo->id,
            'modulo_id'               => $this->grupo->modulo_id,
            'esquema_calificacion_id' => $this->esquema->id,
            'tipo_nota_esquema_id'    => $this->tipo->id,
            'notas'                   => [['estudiante_id' => $this->estudiante->id, 'nota' => $nota]],
        ];
    }

    /** @test */
    public function la_coordinacion_cierra_las_notas_del_ciclo(): void
    {
        $this->actingAs($this->coordinador)
            ->postJson(route('notas-estudiantes.cierre-ciclo.cerrar', $this->ciclo))
            ->assertOk()
            ->assertJsonPath('data.notas_cerradas', true)
            ->assertJsonPath('data.cerradas_por.id', $this->coordinador->id)
            ->assertJsonPath('data.historial.0.accion', CicloCierreNota::ACCION_CIERRE);

        $this->assertNotNull($this->ciclo->fresh()->notas_cerradas_at);
    }

    /** @test */
    public function con_el_ciclo_cerrado_no_se_registran_ni_modifican_notas(): void
    {
        $nota = $this->registrarNotaFinal(4.0);
        $this->cerrarCiclo();

        $this->actingAs($this->coordinador)
            ->postJson(route('notas-estudiantes.masivo'), $this->notaMasiva(4.5))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notas.0.estudiante_id');

        $this->actingAs($this->coordinador)
            ->putJson(route('notas-estudiantes.update', $nota), ['nota' => 4.5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estudiante_id');

        $this->actingAs($this->coordinador)
            ->deleteJson(route('notas-estudiantes.destroy', $nota))
            ->assertUnprocessable();

        $this->assertEquals(4.0, $nota->fresh()->nota);
        $this->assertNotSoftDeleted($nota);
    }

    /** @test */
    public function con_el_ciclo_cerrado_no_se_registran_recuperaciones(): void
    {
        $this->registrarNotaFinal(2.0);
        $this->cerrarCiclo();

        $this->actingAs($this->coordinador)
            ->postJson(route('recuperaciones-notas.store'), [
                'estudiante_id' => $this->estudiante->id,
                'grupo_id'      => $this->grupo->id,
                'nota'          => 3.5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estudiante_id');
    }

    /** @test */
    public function el_cierre_solo_bloquea_a_los_estudiantes_del_ciclo_cerrado(): void
    {
        // El grupo se comparte con otro ciclo abierto: sus estudiantes siguen registrando.
        $otroCiclo = \App\Models\Academico\Ciclo::factory()->create(['sede_id' => $this->sede->id, 'status' => 1]);
        $otroCiclo->grupos()->attach($this->grupo->id, ['orden' => 1]);
        $otro = $this->matricular(null, $otroCiclo);
        $this->cerrarCiclo();

        $payload = $this->notaMasiva(4.0);
        $payload['notas'] = [['estudiante_id' => $otro->id, 'nota' => 4.0]];

        $this->actingAs($this->coordinador)
            ->postJson(route('notas-estudiantes.masivo'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.creadas', 1);
    }

    /** @test */
    public function la_sabana_grupal_marca_las_notas_cerradas(): void
    {
        $this->registrarNotaFinal(2.0);
        $this->cerrarCiclo();

        $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.sabana-grupal', ['grupoId' => $this->grupo->id, 'moduloId' => $this->grupo->modulo_id]))
            ->assertOk()
            ->assertJsonPath('data.estudiantes.0.notas_cerradas', true)
            ->assertJsonPath('data.estudiantes.0.puede_recuperar', false);
    }

    /** @test */
    public function reabrir_exige_motivo_y_queda_en_la_bitacora(): void
    {
        $this->cerrarCiclo();

        $this->actingAs($this->coordinador)
            ->postJson(route('notas-estudiantes.cierre-ciclo.reabrir', $this->ciclo))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motivo');

        $this->actingAs($this->coordinador)
            ->postJson(route('notas-estudiantes.cierre-ciclo.reabrir', $this->ciclo), ['motivo' => 'Corrección de nota del examen final'])
            ->assertOk()
            ->assertJsonPath('data.notas_cerradas', false)
            ->assertJsonPath('data.historial.0.accion', CicloCierreNota::ACCION_REAPERTURA)
            ->assertJsonPath('data.historial.0.motivo', 'Corrección de nota del examen final');

        $this->actingAs($this->coordinador)
            ->postJson(route('notas-estudiantes.masivo'), $this->notaMasiva(4.5))
            ->assertCreated();
    }

    /** @test */
    public function no_se_cierra_dos_veces_ni_se_reabre_un_ciclo_abierto(): void
    {
        $this->actingAs($this->coordinador)
            ->postJson(route('notas-estudiantes.cierre-ciclo.reabrir', $this->ciclo), ['motivo' => 'Sin cierre previo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ciclo');

        $this->cerrarCiclo();

        $this->actingAs($this->coordinador)
            ->postJson(route('notas-estudiantes.cierre-ciclo.cerrar', $this->ciclo))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ciclo');
    }

    /** @test */
    public function reabrir_exige_su_propio_permiso(): void
    {
        $this->cerrarCiclo();
        $soloCierra = User::factory()->create();
        $soloCierra->givePermissionTo(['aca_notas', 'aca_notaCerrar']);
        $soloCierra->sedes()->attach($this->sede->id);

        $this->actingAs($soloCierra)
            ->postJson(route('notas-estudiantes.cierre-ciclo.reabrir', $this->ciclo), ['motivo' => 'Quiero corregir'])
            ->assertForbidden();

        $this->assertTrue($this->ciclo->fresh()->notasCerradas());
    }

    /** @test */
    public function cerrar_exige_permiso_y_la_sede_del_ciclo(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('aca_notas');

        $this->actingAs($sinPermiso)
            ->postJson(route('notas-estudiantes.cierre-ciclo.cerrar', $this->ciclo))
            ->assertForbidden();

        $otraSede = User::factory()->create();
        $otraSede->givePermissionTo(['aca_notas', 'aca_notaCerrar']);
        $otraSede->sedes()->attach(Sede::factory()->create()->id);

        $this->actingAs($otraSede)
            ->postJson(route('notas-estudiantes.cierre-ciclo.cerrar', $this->ciclo))
            ->assertForbidden();
    }

    /** @test */
    public function se_consulta_el_estado_del_cierre(): void
    {
        $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.cierre-ciclo', $this->ciclo))
            ->assertOk()
            ->assertJsonPath('data.ciclo.id', $this->ciclo->id)
            ->assertJsonPath('data.notas_cerradas', false)
            ->assertJsonPath('data.historial', []);
    }
}
