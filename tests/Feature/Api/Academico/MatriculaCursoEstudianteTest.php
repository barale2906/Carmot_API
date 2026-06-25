<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatriculaCursoEstudianteTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function al_crear_una_matricula_activa_se_vincula_el_estudiante_al_curso(): void
    {
        $estudiante = User::factory()->create();
        $curso = Curso::factory()->create();
        $ciclo = Ciclo::factory()->create();

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'ciclo_id' => $ciclo->id,
            'status' => 1,
        ]);

        $this->assertTrue($curso->estudiantes()->where('user_id', $estudiante->id)->exists());
    }

    /** @test */
    public function una_matricula_inactiva_o_anulada_no_vincula_al_estudiante_con_el_curso(): void
    {
        $estudiante = User::factory()->create();
        $curso = Curso::factory()->create();
        $ciclo = Ciclo::factory()->create();

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'ciclo_id' => $ciclo->id,
            'status' => 2,
        ]);

        $this->assertFalse($curso->estudiantes()->where('user_id', $estudiante->id)->exists());
    }

    /** @test */
    public function al_activar_una_matricula_previamente_inactiva_se_vincula_el_estudiante_al_curso(): void
    {
        $estudiante = User::factory()->create();
        $curso = Curso::factory()->create();
        $ciclo = Ciclo::factory()->create();

        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'ciclo_id' => $ciclo->id,
            'status' => 0,
        ]);

        $matricula->update(['status' => 1]);

        $this->assertTrue($curso->estudiantes()->where('user_id', $estudiante->id)->exists());
    }

    /** @test */
    public function al_anular_la_unica_matricula_activa_se_desvincula_el_estudiante_del_curso(): void
    {
        $estudiante = User::factory()->create();
        $curso = Curso::factory()->create();
        $ciclo = Ciclo::factory()->create();

        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'ciclo_id' => $ciclo->id,
            'status' => 1,
        ]);

        $matricula->update(['status' => 2]);

        $this->assertFalse($curso->estudiantes()->where('user_id', $estudiante->id)->exists());
    }

    /** @test */
    public function al_eliminar_una_matricula_activa_se_mantiene_el_vinculo_si_existe_otra_matricula_activa_en_el_mismo_curso(): void
    {
        $estudiante = User::factory()->create();
        $curso = Curso::factory()->create();
        $cicloUno = Ciclo::factory()->create();
        $cicloDos = Ciclo::factory()->create();

        $matriculaUno = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'ciclo_id' => $cicloUno->id,
            'status' => 1,
        ]);

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'ciclo_id' => $cicloDos->id,
            'status' => 1,
        ]);

        $matriculaUno->delete();

        $this->assertTrue($curso->estudiantes()->where('user_id', $estudiante->id)->exists());
    }

    /** @test */
    public function al_eliminar_la_unica_matricula_activa_se_desvincula_el_estudiante_del_curso(): void
    {
        $estudiante = User::factory()->create();
        $curso = Curso::factory()->create();
        $ciclo = Ciclo::factory()->create();

        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'ciclo_id' => $ciclo->id,
            'status' => 1,
        ]);

        $matricula->delete();

        $this->assertFalse($curso->estudiantes()->where('user_id', $estudiante->id)->exists());
    }

    /** @test */
    public function al_cambiar_el_curso_de_una_matricula_activa_se_traslada_el_vinculo_al_nuevo_curso(): void
    {
        $estudiante = User::factory()->create();
        $cursoOrigen = Curso::factory()->create();
        $cursoDestino = Curso::factory()->create();
        $ciclo = Ciclo::factory()->create();

        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => $cursoOrigen->id,
            'ciclo_id' => $ciclo->id,
            'status' => 1,
        ]);

        $matricula->update(['curso_id' => $cursoDestino->id]);

        $this->assertFalse($cursoOrigen->estudiantes()->where('user_id', $estudiante->id)->exists());
        $this->assertTrue($cursoDestino->estudiantes()->where('user_id', $estudiante->id)->exists());
    }
}
