<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\TipoAplazamiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TipoAplazamientoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_tiposAplazamiento',      'descripcion' => 'ver tipos aplazamiento']);
        Permission::create(['name' => 'aca_tipoAplazamientoCrear',  'descripcion' => 'crear tipo aplazamiento']);
        Permission::create(['name' => 'aca_tipoAplazamientoEditar', 'descripcion' => 'editar tipo aplazamiento']);
        Permission::create(['name' => 'aca_tipoAplazamientoInactivar', 'descripcion' => 'inactivar tipo aplazamiento']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'aca_tiposAplazamiento',
            'aca_tipoAplazamientoCrear',
            'aca_tipoAplazamientoEditar',
            'aca_tipoAplazamientoInactivar',
        ]);
    }

    /** @test */
    public function lista_tipos_de_aplazamiento_paginados(): void
    {
        TipoAplazamiento::factory()->count(3)->create();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('tipos-aplazamiento.index'));

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function crea_tipo_de_aplazamiento_correctamente(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('tipos-aplazamiento.store'), [
                'nombre'      => 'Demanda incompleta',
                'descripcion' => 'No hay suficientes inscritos.',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['nombre' => 'Demanda incompleta']);

        $this->assertDatabaseHas('tipo_aplazamientos', ['nombre' => 'Demanda incompleta']);
    }

    /** @test */
    public function no_permite_nombre_duplicado(): void
    {
        TipoAplazamiento::factory()->create(['nombre' => 'Enfermedad del profesor']);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('tipos-aplazamiento.store'), [
                'nombre' => 'Enfermedad del profesor',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    /** @test */
    public function actualiza_tipo_de_aplazamiento(): void
    {
        $tipo = TipoAplazamiento::factory()->create(['nombre' => 'Original']);

        $response = $this->actingAs($this->usuario)
            ->putJson(route('tipos-aplazamiento.update', $tipo), [
                'nombre' => 'Actualizado',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['nombre' => 'Actualizado']);
    }

    /** @test */
    public function soft_delete_y_restore(): void
    {
        $tipo = TipoAplazamiento::factory()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('tipos-aplazamiento.destroy', $tipo))
            ->assertOk();

        $this->assertSoftDeleted('tipo_aplazamientos', ['id' => $tipo->id]);

        $this->actingAs($this->usuario)
            ->postJson(route('tipos-aplazamiento.restore', $tipo->id))
            ->assertOk();

        $this->assertDatabaseHas('tipo_aplazamientos', ['id' => $tipo->id, 'deleted_at' => null]);
    }

    /** @test */
    public function no_puede_eliminar_con_aplazamientos_asociados(): void
    {
        $tipo  = TipoAplazamiento::factory()->create();
        $ciclo = \App\Models\Academico\Ciclo::factory()->create([
            'fecha_inicio' => now()->addDays(10)->format('Y-m-d'),
        ]);

        // Inserción directa para garantizar visibilidad dentro de la transacción del test
        \Illuminate\Support\Facades\DB::table('aplazamientos')->insert([
            'ciclo_id'                => $ciclo->id,
            'tipo_aplazamiento_id'    => $tipo->id,
            'user_id'                 => $this->usuario->id,
            'aplazamiento_padre_id'   => null,
            'fecha_aplazamiento'      => now()->format('Y-m-d'),
            'fecha_inicio_original'   => now()->addDays(10)->format('Y-m-d'),
            'fecha_reinicio_probable'  => now()->addDays(20)->format('Y-m-d'),
            'dias_aplazamiento'       => 10,
            'mover_cartera'           => 0,
            'clases_movidas'          => 0,
            'carteras_movidas'        => 0,
            'estado'                  => 0,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        $response = $this->actingAs($this->usuario)
            ->deleteJson(route('tipos-aplazamiento.destroy', $tipo));

        $response->assertStatus(422);
    }

    /** @test */
    public function permiso_denegado_sin_autenticacion(): void
    {
        $this->getJson(route('tipos-aplazamiento.index'))
            ->assertUnauthorized();
    }

    /** @test */
    public function permiso_denegado_sin_permiso_adecuado(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('tipos-aplazamiento.store'), ['nombre' => 'Test'])
            ->assertForbidden();
    }
}
