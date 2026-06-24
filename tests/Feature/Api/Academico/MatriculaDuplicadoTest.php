<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
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
            'matriculado_por_id' => $this->usuario->id,
            'comercial_id'       => $this->usuario->id,
            'fecha_matricula'    => now()->format('Y-m-d'),
            'fecha_inicio'       => now()->addDay()->format('Y-m-d'),
            'monto'              => 500000,
        ], $overrides);
    }

    /** @test */
    public function no_permite_matricular_dos_veces_al_mismo_estudiante_en_el_mismo_curso_y_ciclo(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();
        $ciclo      = Ciclo::factory()->create();

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id'      => $curso->id,
            'ciclo_id'      => $ciclo->id,
            'status'        => 1,
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosBase([
                'estudiante_id' => $estudiante->id,
                'curso_id'      => $curso->id,
                'ciclo_id'      => $ciclo->id,
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('estudiante_id')
            ->assertJsonFragment(['estudiante_id' => ['El estudiante ya se encuentra matriculado en este curso y ciclo.']]);
    }

    /** @test */
    public function permite_matricular_si_la_matricula_previa_esta_anulada(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();
        $ciclo      = Ciclo::factory()->create();

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id'      => $curso->id,
            'ciclo_id'      => $ciclo->id,
            'status'        => 2, // Anulada
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosBase([
                'estudiante_id' => $estudiante->id,
                'curso_id'      => $curso->id,
                'ciclo_id'      => $ciclo->id,
            ]));

        $response->assertCreated();
    }

    /** @test */
    public function permite_matricular_al_mismo_estudiante_en_un_ciclo_diferente(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();
        $cicloPrevio = Ciclo::factory()->create();
        $cicloNuevo  = Ciclo::factory()->create();

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id'      => $curso->id,
            'ciclo_id'      => $cicloPrevio->id,
            'status'        => 1,
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosBase([
                'estudiante_id' => $estudiante->id,
                'curso_id'      => $curso->id,
                'ciclo_id'      => $cicloNuevo->id,
            ]));

        $response->assertCreated();
    }

    /** @test */
    public function permite_actualizar_la_propia_matricula_sin_disparar_el_error_de_duplicado(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();
        $ciclo      = Ciclo::factory()->create();

        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id'      => $curso->id,
            'ciclo_id'      => $ciclo->id,
            'status'        => 1,
        ]);

        $response = $this->actingAs($this->usuario)
            ->putJson(route('matriculas.update', $matricula->id), [
                'observaciones' => 'Actualización sin cambios de curso/ciclo/estudiante.',
            ]);

        $response->assertOk();
    }

    /** @test */
    public function no_permite_actualizar_una_matricula_para_que_quede_duplicada_con_otra(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();
        $ciclo      = Ciclo::factory()->create();

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id'      => $curso->id,
            'ciclo_id'      => $ciclo->id,
            'status'        => 1,
        ]);

        $otraMatricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id'      => Curso::factory()->create()->id,
            'ciclo_id'      => Ciclo::factory()->create()->id,
            'status'        => 1,
        ]);

        $response = $this->actingAs($this->usuario)
            ->putJson(route('matriculas.update', $otraMatricula->id), [
                'curso_id' => $curso->id,
                'ciclo_id' => $ciclo->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('estudiante_id');
    }
}
