<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Selector de grupos vigentes de las pantallas de asistencia y calificaciones.
 */
class GrupoVigentesTest extends TestCase
{
    use RefreshDatabase;

    private User $profesor;
    private Grupo $grupo;
    private Grupo $grupoOtroProfesor;
    private Grupo $grupoSinCicloVigente;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['aca_grupos', 'aca_asistencias', 'aca_notas'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->profesor = User::factory()->create();

        $vigente = Ciclo::factory()->create([
            'status' => 1, 'fecha_inicio' => now()->subWeek()->toDateString(), 'fecha_fin' => now()->addMonth()->toDateString(),
        ]);
        $terminado = Ciclo::factory()->create([
            'status' => 1, 'fecha_inicio' => now()->subYear()->toDateString(), 'fecha_fin' => now()->subMonth()->toDateString(),
        ]);

        $this->grupo                = Grupo::factory()->create(['profesor_id' => $this->profesor->id]);
        $this->grupoOtroProfesor    = Grupo::factory()->create(['profesor_id' => User::factory()->create()->id]);
        $this->grupoSinCicloVigente = Grupo::factory()->create(['profesor_id' => $this->profesor->id]);

        $vigente->grupos()->attach($this->grupo->id, ['orden' => 1]);
        $vigente->grupos()->attach($this->grupoOtroProfesor->id, ['orden' => 2]);
        $terminado->grupos()->attach($this->grupoSinCicloVigente->id, ['orden' => 1]);
    }

    /** @test */
    public function lista_solo_grupos_con_ciclo_vigente(): void
    {
        $this->profesor->givePermissionTo('aca_asistencias');

        $ids = collect($this->actingAs($this->profesor)->getJson(route('grupos.vigentes'))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'nombre', 'modulo', 'sede', 'profesor']]])
            ->json('data'))->pluck('id');

        $this->assertEqualsCanonicalizing([$this->grupo->id, $this->grupoOtroProfesor->id], $ids->all());
    }

    /** @test */
    public function mis_grupos_filtra_por_el_profesor_autenticado(): void
    {
        $this->profesor->givePermissionTo('aca_notas');

        $this->actingAs($this->profesor)
            ->getJson(route('grupos.vigentes', ['mis_grupos' => 1]))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->grupo->id);
    }

    /** @test */
    public function basta_cualquiera_de_los_permisos_academicos(): void
    {
        foreach (['aca_grupos', 'aca_asistencias', 'aca_notas'] as $permiso) {
            $usuario = User::factory()->create();
            $usuario->givePermissionTo($permiso);
            $this->actingAs($usuario)->getJson(route('grupos.vigentes'))->assertOk();
        }

        $this->actingAs(User::factory()->create())->getJson(route('grupos.vigentes'))->assertForbidden();
    }
}
