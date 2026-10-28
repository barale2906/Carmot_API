<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\RecuperacionNota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Academico\Concerns\CreaEscenarioNotas;
use Tests\TestCase;

/**
 * Recuperación de módulos reprobados: solo se registra con la nota final completa y por
 * debajo de la aprobatoria, y define si el estudiante aprueba el módulo.
 */
class RecuperacionNotaTest extends TestCase
{
    use RefreshDatabase, CreaEscenarioNotas;

    protected function setUp(): void
    {
        parent::setUp();

        config(['academico.nota_aprobatoria' => 3.0]);
        $this->crearEscenarioNotas();
    }

    /**
     * Payload de una recuperación del estudiante del escenario.
     *
     * @param float $nota
     * @return array<string, mixed>
     */
    private function payload(float $nota): array
    {
        return [
            'estudiante_id' => $this->estudiante->id,
            'grupo_id'      => $this->grupo->id,
            'nota'          => $nota,
        ];
    }

    /**
     * Sábana grupal del escenario: el resultado del primer (único) estudiante.
     *
     * @return array<string, mixed>
     */
    private function resultado(): array
    {
        return $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.sabana-grupal', ['grupoId' => $this->grupo->id, 'moduloId' => $this->grupo->modulo_id]))
            ->assertOk()
            ->json('data.estudiantes.0');
    }

    /** @test */
    public function un_estudiante_reprobado_queda_por_recuperar(): void
    {
        $this->registrarNotaFinal(2.5);

        $resultado = $this->resultado();

        $this->assertSame('reprobado', $resultado['estado']);
        $this->assertTrue($resultado['puede_recuperar']);
        $this->assertNull($resultado['recuperacion']);
        $this->assertEquals(2.5, $resultado['nota_definitiva']);
    }

    /** @test */
    public function la_recuperacion_aprobada_aprueba_el_modulo_con_su_nota(): void
    {
        $this->registrarNotaFinal(2.5);

        $this->actingAs($this->profesor->givePermissionTo('aca_notaCrear'))
            ->postJson(route('recuperaciones-notas.store'), $this->payload(3.5))
            ->assertCreated()
            ->assertJsonPath('data.nota', 3.5)
            ->assertJsonPath('data.modulo_id', $this->grupo->modulo_id);

        $resultado = $this->resultado();

        $this->assertSame('aprobado', $resultado['estado']);
        $this->assertTrue($resultado['aprobado']);
        $this->assertEquals(2.5, $resultado['nota_final']);
        $this->assertEquals(3.5, $resultado['nota_definitiva']);
        $this->assertEquals(3.5, $resultado['recuperacion']['nota']);
    }

    /** @test */
    public function la_recuperacion_reprobada_deja_el_modulo_reprobado(): void
    {
        $this->registrarNotaFinal(2.5);

        $this->actingAs($this->coordinador)
            ->postJson(route('recuperaciones-notas.store'), $this->payload(2.0))
            ->assertCreated();

        $resultado = $this->resultado();

        $this->assertSame('reprobado', $resultado['estado']);
        $this->assertEquals(2.0, $resultado['nota_definitiva']);
    }

    /** @test */
    public function registrar_de_nuevo_actualiza_la_misma_recuperacion(): void
    {
        $this->registrarNotaFinal(2.5);

        $this->actingAs($this->coordinador)->postJson(route('recuperaciones-notas.store'), $this->payload(2.0))->assertCreated();
        $this->actingAs($this->coordinador)->postJson(route('recuperaciones-notas.store'), $this->payload(4.0))->assertCreated();

        $this->assertSame(1, RecuperacionNota::count());
        $this->assertEquals(4.0, RecuperacionNota::first()->nota);
    }

    /** @test */
    public function no_se_recupera_un_modulo_aprobado_ni_uno_con_notas_pendientes(): void
    {
        $this->actingAs($this->coordinador)
            ->postJson(route('recuperaciones-notas.store'), $this->payload(4.0))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estudiante_id');

        $this->registrarNotaFinal(3.0);

        $this->actingAs($this->coordinador)
            ->postJson(route('recuperaciones-notas.store'), $this->payload(4.0))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estudiante_id');
    }

    /** @test */
    public function la_nota_de_recuperacion_debe_caber_en_la_escala_del_esquema(): void
    {
        $this->registrarNotaFinal(2.5);

        $this->actingAs($this->coordinador)
            ->postJson(route('recuperaciones-notas.store'), $this->payload(5.5))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nota');

        $this->actingAs($this->coordinador)
            ->postJson(route('recuperaciones-notas.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estudiante_id', 'grupo_id', 'nota']);
    }

    /** @test */
    public function no_se_recupera_a_un_estudiante_sin_matricula_en_el_grupo(): void
    {
        $ajeno = User::factory()->create();

        $this->actingAs($this->coordinador)
            ->postJson(route('recuperaciones-notas.store'), ['estudiante_id' => $ajeno->id] + $this->payload(3.5))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estudiante_id');
    }

    /** @test */
    public function otro_profesor_no_registra_recuperaciones_en_un_grupo_ajeno(): void
    {
        $this->registrarNotaFinal(2.5);
        $otro = User::factory()->create();
        $otro->givePermissionTo('aca_notaCrear');

        $this->actingAs($otro)
            ->postJson(route('recuperaciones-notas.store'), $this->payload(3.5))
            ->assertForbidden();
    }

    /** @test */
    public function registrar_una_recuperacion_exige_permiso(): void
    {
        $this->registrarNotaFinal(2.5);

        $this->actingAs($this->profesor)
            ->postJson(route('recuperaciones-notas.store'), $this->payload(3.5))
            ->assertForbidden();
    }

    /** @test */
    public function se_edita_y_elimina_una_recuperacion(): void
    {
        $this->registrarNotaFinal(2.5);
        $id = $this->actingAs($this->coordinador)
            ->postJson(route('recuperaciones-notas.store'), $this->payload(2.0))
            ->json('data.id');

        $this->actingAs($this->coordinador)
            ->putJson(route('recuperaciones-notas.update', $id), ['nota' => 3.2])
            ->assertOk()
            ->assertJsonPath('data.nota', 3.2);

        $this->actingAs($this->coordinador)
            ->deleteJson(route('recuperaciones-notas.destroy', $id))
            ->assertOk();

        $this->assertSoftDeleted('recuperacion_notas', ['id' => $id]);
        $this->assertSame('reprobado', $this->resultado()['estado']);

        $this->actingAs($this->coordinador)
            ->postJson(route('recuperaciones-notas.restore', $id))
            ->assertOk();

        $this->assertSame('aprobado', $this->resultado()['estado']);
    }

    /** @test */
    public function el_alumno_solo_lista_sus_recuperaciones(): void
    {
        $this->registrarNotaFinal(2.5);
        $otro = $this->matricular();
        $this->registrarNotaFinal(1.0, $otro);

        foreach ([$this->estudiante, $otro] as $estudiante) {
            $this->actingAs($this->coordinador)
                ->postJson(route('recuperaciones-notas.store'), ['estudiante_id' => $estudiante->id] + $this->payload(3.5))
                ->assertCreated();
        }

        $this->estudiante->assignRole(\Spatie\Permission\Models\Role::create(['name' => User::ROL_ALUMNO, 'guard_name' => 'web']));
        $this->estudiante->givePermissionTo('aca_notas');

        $this->actingAs($this->estudiante)
            ->getJson(route('recuperaciones-notas.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estudiante_id', $this->estudiante->id);
    }
}
