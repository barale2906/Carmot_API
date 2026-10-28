<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Configuracion\Sede;
use App\Models\Crm\Referido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Conversión de un lead del CRM en matrícula.
 */
class MatriculaReferidoTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        $curso = Curso::factory()->create();

        return array_merge([
            'sede_id'            => Sede::factory()->create()->id,
            'curso_id'           => $curso->id,
            'ciclo_id'           => Ciclo::factory()->create(['curso_id' => $curso->id])->id,
            'estudiante_id'      => User::factory()->create()->id,
            'matriculado_por_id' => $this->usuario->id,
            'comercial_id'       => $this->usuario->id,
            'fecha_matricula'    => now()->format('Y-m-d'),
            'fecha_inicio'       => now()->addDay()->format('Y-m-d'),
            'monto'              => 500000,
        ], $overrides);
    }

    private function crearReferido(): Referido
    {
        return Referido::factory()->create([
            'curso_id'  => Curso::factory()->create()->id,
            'gestor_id' => $this->usuario->id,
            'status'    => 2,
        ]);
    }

    /** @test */
    public function al_matricular_desde_un_lead_este_queda_matriculado_con_seguimiento(): void
    {
        $referido = $this->crearReferido();

        $respuesta = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->payload(['referido_id' => $referido->id]))
            ->assertCreated()
            ->assertJsonPath('data.referido_id', $referido->id);

        $matriculaId = $respuesta->json('data.id');

        $this->assertSame(3, $referido->fresh()->status);
        $this->assertDatabaseHas('seguimientos', [
            'referido_id' => $referido->id,
            'seguidor_id' => $this->usuario->id,
            'seguimiento' => "MATRÍCULA: Convertido en la matrícula #{$matriculaId}.",
            // La bitácora registra que el lead quedó matriculado
            'estado_referido' => 3,
        ]);
    }

    /** @test */
    public function sin_lead_la_matricula_se_crea_normalmente(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.referido_id', null);
    }

    /** @test */
    public function rechaza_lead_eliminado(): void
    {
        $referido = $this->crearReferido();
        $referido->delete();

        $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->payload(['referido_id' => $referido->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('referido_id');
    }

    /** @test */
    public function el_lead_no_se_puede_cambiar_al_editar_la_matricula(): void
    {
        $referido = $this->crearReferido();
        $id = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->payload())
            ->json('data.id');

        $this->actingAs($this->usuario)
            ->putJson(route('matriculas.update', $id), ['referido_id' => $referido->id])
            ->assertOk();

        $this->assertDatabaseHas('matriculas', ['id' => $id, 'referido_id' => null]);
    }
}
