<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Curso;
use App\Models\Academico\Modulo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CursoOrdenModulosTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Curso $curso;
    private Modulo $moduloA;
    private Modulo $moduloB;
    private Modulo $moduloC;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_cursos',       'descripcion' => 'ver cursos']);
        Permission::create(['name' => 'aca_cursoEditar',  'descripcion' => 'editar curso']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['aca_cursos', 'aca_cursoEditar']);

        $this->curso = Curso::factory()->create();

        $this->moduloA = Modulo::create(['nombre' => 'Módulo A', 'duracion' => 40, 'status' => 1]);
        $this->moduloB = Modulo::create(['nombre' => 'Módulo B', 'duracion' => 40, 'status' => 1]);
        $this->moduloC = Modulo::create(['nombre' => 'Módulo C', 'duracion' => 40, 'status' => 1]);

        DB::table('modulo_curso')->insert([
            ['modulo_id' => $this->moduloA->id, 'curso_id' => $this->curso->id, 'orden' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['modulo_id' => $this->moduloB->id, 'curso_id' => $this->curso->id, 'orden' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['modulo_id' => $this->moduloC->id, 'curso_id' => $this->curso->id, 'orden' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** @test */
    public function actualiza_orden_canonico_de_modulos_exitosamente(): void
    {
        $response = $this->actingAs($this->usuario)->putJson(
            route('cursos.modulos-orden', $this->curso),
            [
                'modulos' => [
                    ['modulo_id' => $this->moduloA->id, 'orden' => 1],
                    ['modulo_id' => $this->moduloB->id, 'orden' => 2],
                    ['modulo_id' => $this->moduloC->id, 'orden' => 3],
                ],
            ]
        );

        $response->assertOk()
                 ->assertJsonPath('message', 'Orden de módulos actualizado exitosamente.');

        $this->assertDatabaseHas('modulo_curso', ['modulo_id' => $this->moduloA->id, 'curso_id' => $this->curso->id, 'orden' => 1]);
        $this->assertDatabaseHas('modulo_curso', ['modulo_id' => $this->moduloB->id, 'curso_id' => $this->curso->id, 'orden' => 2]);
        $this->assertDatabaseHas('modulo_curso', ['modulo_id' => $this->moduloC->id, 'curso_id' => $this->curso->id, 'orden' => 3]);
    }

    /** @test */
    public function la_respuesta_incluye_el_campo_orden_en_pivot_de_modulos(): void
    {
        $response = $this->actingAs($this->usuario)->putJson(
            route('cursos.modulos-orden', $this->curso),
            [
                'modulos' => [
                    ['modulo_id' => $this->moduloA->id, 'orden' => 2],
                    ['modulo_id' => $this->moduloB->id, 'orden' => 1],
                    ['modulo_id' => $this->moduloC->id, 'orden' => 3],
                ],
            ]
        );

        $response->assertOk();

        $modulos = collect($response->json('data.modulos'));
        $ordenA  = $modulos->firstWhere('id', $this->moduloA->id)['pivot']['orden'];
        $ordenB  = $modulos->firstWhere('id', $this->moduloB->id)['pivot']['orden'];

        $this->assertEquals(2, $ordenA);
        $this->assertEquals(1, $ordenB);
    }

    /** @test */
    public function falla_si_el_listado_de_modulos_es_incompleto(): void
    {
        $response = $this->actingAs($this->usuario)->putJson(
            route('cursos.modulos-orden', $this->curso),
            [
                'modulos' => [
                    ['modulo_id' => $this->moduloA->id, 'orden' => 1],
                    // falta moduloB y moduloC
                ],
            ]
        );

        $response->assertStatus(422)
                 ->assertJsonPath('message', 'El listado de módulos no coincide con los módulos asignados al curso. Debe incluirlos todos.');
    }

    /** @test */
    public function falla_si_se_envia_un_modulo_que_no_pertenece_al_curso(): void
    {
        $moduloAjeno = Modulo::create(['nombre' => 'Ajeno', 'duracion' => 10, 'status' => 1]);

        $response = $this->actingAs($this->usuario)->putJson(
            route('cursos.modulos-orden', $this->curso),
            [
                'modulos' => [
                    ['modulo_id' => $this->moduloA->id, 'orden' => 1],
                    ['modulo_id' => $this->moduloB->id, 'orden' => 2],
                    ['modulo_id' => $moduloAjeno->id,   'orden' => 3],
                ],
            ]
        );

        $response->assertStatus(422)
                 ->assertJsonPath('message', 'El listado de módulos no coincide con los módulos asignados al curso. Debe incluirlos todos.');
    }

    /** @test */
    public function falla_si_modulos_esta_vacio(): void
    {
        $response = $this->actingAs($this->usuario)->putJson(
            route('cursos.modulos-orden', $this->curso),
            ['modulos' => []]
        );

        $response->assertStatus(422);
    }

    /** @test */
    public function requiere_permiso_aca_cursoEditar(): void
    {
        $sinPermiso = User::factory()->create();

        $response = $this->actingAs($sinPermiso)->putJson(
            route('cursos.modulos-orden', $this->curso),
            [
                'modulos' => [
                    ['modulo_id' => $this->moduloA->id, 'orden' => 1],
                    ['modulo_id' => $this->moduloB->id, 'orden' => 2],
                    ['modulo_id' => $this->moduloC->id, 'orden' => 3],
                ],
            ]
        );

        $response->assertStatus(403);
    }

    /** @test */
    public function show_incluye_campo_orden_en_modulos(): void
    {
        DB::table('modulo_curso')
            ->where('modulo_id', $this->moduloA->id)
            ->where('curso_id', $this->curso->id)
            ->update(['orden' => 5]);

        $response = $this->actingAs($this->usuario)
                         ->getJson(route('cursos.show', $this->curso) . '?with=modulos');

        $response->assertOk();

        $modulos = collect($response->json('data.modulos'));
        $ordenA  = $modulos->firstWhere('id', $this->moduloA->id)['pivot']['orden'];

        $this->assertEquals(5, $ordenA);
    }
}
