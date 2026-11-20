<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Estado académico inicial de la matrícula según el inicio del ciclo y su activación automática.
 */
class MatriculaEstadoInicialTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::create(['name' => 'aca_matriculaCrear', 'descripcion' => 'crear matricula']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo('aca_matriculaCrear');
    }

    private function matricular(string $inicioCiclo): int
    {
        $curso = Curso::factory()->create();
        $ciclo = Ciclo::factory()->create(['curso_id' => $curso->id, 'fecha_inicio' => $inicioCiclo]);

        return $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), [
                'sede_id'            => $ciclo->sede_id,
                'curso_id'           => $curso->id,
                'ciclo_id'           => $ciclo->id,
                'estudiante_id'      => User::factory()->create()->id,
                'matriculado_por_id' => $this->usuario->id,
                'comercial_id'       => $this->usuario->id,
                'fecha_matricula'    => now()->format('Y-m-d'),
                'fecha_inicio'       => now()->addDay()->format('Y-m-d'),
                'monto'              => 500000,
            ])
            ->assertCreated()
            ->json('data.estado_academico');
    }

    /** @test */
    public function si_el_ciclo_ya_inicio_la_matricula_nace_activa(): void
    {
        $this->assertSame(Matricula::ESTADO_ACTIVO, $this->matricular(now()->subDays(3)->toDateString()));
        $this->assertSame(Matricula::ESTADO_ACTIVO, $this->matricular(now()->toDateString()));
    }

    /** @test */
    public function si_el_ciclo_no_ha_iniciado_la_matricula_nace_pendiente(): void
    {
        $this->assertSame(Matricula::ESTADO_PENDIENTE_INICIO, $this->matricular(now()->addDays(5)->toDateString()));
    }

    /** @test */
    public function el_comando_activa_las_pendientes_cuyo_ciclo_ya_inicio(): void
    {
        $iniciado = Matricula::factory()->create([
            'ciclo_id'         => Ciclo::factory()->create(['fecha_inicio' => now()->subDay()])->id,
            'estado_academico' => Matricula::ESTADO_PENDIENTE_INICIO,
        ]);
        $futuro = Matricula::factory()->create([
            'ciclo_id'         => Ciclo::factory()->create(['fecha_inicio' => now()->addDays(4)])->id,
            'estado_academico' => Matricula::ESTADO_PENDIENTE_INICIO,
        ]);

        $this->artisan('academico:activar-matriculas-iniciadas')->assertSuccessful();

        $this->assertSame(Matricula::ESTADO_ACTIVO, $iniciado->fresh()->estado_academico);
        $this->assertSame(Matricula::ESTADO_PENDIENTE_INICIO, $futuro->fresh()->estado_academico);
        $this->assertDatabaseHas('matricula_estados', [
            'matricula_id' => $iniciado->id,
            'estado_nuevo' => Matricula::ESTADO_ACTIVO,
        ]);
    }
}
