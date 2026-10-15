<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MatriculaDuplicadoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_matriculaCrear', 'descripcion' => 'crear matricula']);
        Permission::create(['name' => 'aca_matriculaEditar', 'descripcion' => 'editar matricula']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['aca_matriculaCrear', 'aca_matriculaEditar']);
    }

    private function datosBase(array $overrides = []): array
    {
        return array_merge([
            'sede_id'            => Sede::factory()->create()->id,
            'matriculado_por_id' => $this->usuario->id,
            'comercial_id'       => $this->usuario->id,
            'fecha_matricula'    => now()->format('Y-m-d'),
            'fecha_inicio'       => now()->addDay()->format('Y-m-d'),
            'monto'              => 500000,
        ], $overrides);
    }

    private function crearMatricula(User $estudiante, Curso $curso, int $status, ?Ciclo $ciclo = null): Matricula
    {
        return Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id'      => $curso->id,
            'ciclo_id'      => ($ciclo ?? Ciclo::factory()->create())->id,
            'status'        => $status,
        ]);
    }

    private function matricular(User $estudiante, Curso $curso, ?Ciclo $ciclo = null)
    {
        $ciclo ??= Ciclo::factory()->create(['curso_id' => $curso->id]);

        // La matrícula debe ser de la sede y el curso del ciclo
        return $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosBase([
                'sede_id'       => $ciclo->sede_id,
                'estudiante_id' => $estudiante->id,
                'curso_id'      => $curso->id,
                'ciclo_id'      => $ciclo->id,
            ]));
    }

    /** @test */
    public function no_permite_matricular_dos_veces_al_mismo_estudiante_en_el_mismo_curso_y_ciclo(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();
        $ciclo      = Ciclo::factory()->create(['curso_id' => $curso->id]);

        $this->crearMatricula($estudiante, $curso, 1, $ciclo);

        $this->matricular($estudiante, $curso, $ciclo)
            ->assertStatus(422)
            ->assertJsonValidationErrors('estudiante_id')
            ->assertJsonFragment(['estudiante_id' => ['El estudiante ya tiene una matrícula vigente en este curso.']]);
    }

    /** @test */
    public function no_permite_matricular_en_el_mismo_curso_en_otro_ciclo_si_hay_matricula_vigente(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();

        $this->crearMatricula($estudiante, $curso, 1);

        $this->matricular($estudiante, $curso)
            ->assertStatus(422)
            ->assertJsonValidationErrors('estudiante_id');
    }

    /** @test */
    public function permite_matricular_si_la_matricula_previa_esta_anulada(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();
        $ciclo      = Ciclo::factory()->create(['curso_id' => $curso->id]);

        $this->crearMatricula($estudiante, $curso, 2, $ciclo);

        $this->matricular($estudiante, $curso, $ciclo)->assertCreated();
    }

    /** @test */
    public function permite_matricular_si_la_matricula_previa_esta_inactiva_por_finalizacion_o_cancelacion(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();

        $this->crearMatricula($estudiante, $curso, 0);

        $this->matricular($estudiante, $curso)->assertCreated();
    }

    /** @test */
    public function permite_matricular_al_mismo_estudiante_en_otro_curso_aunque_tenga_matricula_vigente(): void
    {
        $estudiante = User::factory()->create();

        $this->crearMatricula($estudiante, Curso::factory()->create(), 1);

        $this->matricular($estudiante, Curso::factory()->create())->assertCreated();
    }

    /** @test */
    public function permite_actualizar_la_propia_matricula_sin_disparar_el_error_de_duplicado(): void
    {
        $matricula = $this->crearMatricula(User::factory()->create(), Curso::factory()->create(), 1);

        $this->actingAs($this->usuario)
            ->putJson(route('matriculas.update', $matricula->id), [
                'observaciones' => 'Actualización sin cambios de curso/ciclo/estudiante.',
            ])
            ->assertOk();
    }

    /** @test */
    public function no_permite_actualizar_una_matricula_para_que_quede_duplicada_con_otra_vigente(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();

        $this->crearMatricula($estudiante, $curso, 1);
        $otraMatricula = $this->crearMatricula($estudiante, Curso::factory()->create(), 1);

        $this->actingAs($this->usuario)
            ->putJson(route('matriculas.update', $otraMatricula->id), [
                'curso_id' => $curso->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('estudiante_id');
    }

    /** @test */
    public function no_permite_reactivar_una_matricula_si_existe_otra_vigente_en_el_mismo_curso(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();

        $this->crearMatricula($estudiante, $curso, 1);
        $inactiva = $this->crearMatricula($estudiante, $curso, 0);

        $this->actingAs($this->usuario)
            ->putJson(route('matriculas.update', $inactiva->id), ['status' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('estudiante_id');
    }

    /** @test */
    public function permite_editar_una_matricula_no_vigente_aunque_exista_otra_vigente_en_el_mismo_curso(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();

        $this->crearMatricula($estudiante, $curso, 1);
        $anulada = $this->crearMatricula($estudiante, $curso, 2);

        $this->actingAs($this->usuario)
            ->putJson(route('matriculas.update', $anulada->id), ['observaciones' => 'Nota histórica.'])
            ->assertOk();
    }
}
