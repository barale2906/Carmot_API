<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\Configuracion\Area;
use App\Models\Configuracion\Horario;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Verifica que múltiples grupos pueden compartir la misma área en el mismo horario.
 *
 * El cliente solicitó eliminar la restricción de "un área por horario = un grupo".
 * El backend nunca tuvo esa restricción (no hay UNIQUE en la DB ni validación cross-grupo);
 * estos tests documentan y fijan ese comportamiento como contrato para evitar regresiones.
 */
class GrupoHorarioCompartidoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Sede $sede;
    private Modulo $modulo;
    private User $profesor;
    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_grupos',           'descripcion' => 'ver grupos']);
        Permission::create(['name' => 'aca_grupoCrear',       'descripcion' => 'crear grupo']);
        Permission::create(['name' => 'aca_grupoEditar',      'descripcion' => 'editar grupo']);
        Permission::create(['name' => 'aca_grupoInactivar',   'descripcion' => 'inactivar grupo']);
        Permission::create(['name' => 'co_horarios',          'descripcion' => 'ver horarios']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'aca_grupos', 'aca_grupoCrear', 'aca_grupoEditar', 'aca_grupoInactivar', 'co_horarios',
        ]);

        $this->area   = Area::factory()->active()->create(['nombre' => 'Aula 101']);
        $this->sede   = Sede::factory()->create();
        $this->modulo = Modulo::factory()->withoutCursos()->create(['status' => 1]);
        $this->profesor = User::factory()->create();
    }

    // =========================================================================
    // HAPPY PATH: múltiples grupos en la misma área/día/hora
    // =========================================================================

    /** @test */
    public function dos_grupos_pueden_asignarse_a_la_misma_area_en_el_mismo_dia_y_hora(): void
    {
        $grupo1 = $this->crearGrupo('Grupo Alfa');
        $grupo2 = $this->crearGrupo('Grupo Beta');

        // Asignar área+día+hora al primer grupo
        $this->actingAs($this->usuario)
            ->postJson(route('grupos.horarios.store', $grupo1), [
                'horarios' => [
                    ['area_id' => $this->area->id, 'dia' => 'lunes', 'hora' => '08:00', 'duracion_horas' => 2],
                ],
            ])
            ->assertStatus(201);

        // Asignar la MISMA área+día+hora al segundo grupo — debe permitirse
        $this->actingAs($this->usuario)
            ->postJson(route('grupos.horarios.store', $grupo2), [
                'horarios' => [
                    ['area_id' => $this->area->id, 'dia' => 'lunes', 'hora' => '08:00', 'duracion_horas' => 2],
                ],
            ])
            ->assertStatus(201);

        // Ambos horarios de grupo deben existir en el área, día y hora indicados
        $this->assertEquals(
            2,
            Horario::where('area_id', $this->area->id)
                ->where('dia', 'lunes')
                ->where('tipo', false)
                ->count()
        );
    }

    /** @test */
    public function tres_grupos_pueden_compartir_la_misma_area_en_varios_dias(): void
    {
        $grupo1 = $this->crearGrupo('Grupo Uno');
        $grupo2 = $this->crearGrupo('Grupo Dos');
        $grupo3 = $this->crearGrupo('Grupo Tres');

        foreach ([$grupo1, $grupo2, $grupo3] as $grupo) {
            $this->actingAs($this->usuario)
                ->postJson(route('grupos.horarios.store', $grupo), [
                    'horarios' => [
                        ['area_id' => $this->area->id, 'dia' => 'martes',  'hora' => '10:00', 'duracion_horas' => 1],
                        ['area_id' => $this->area->id, 'dia' => 'jueves',  'hora' => '10:00', 'duracion_horas' => 1],
                    ],
                ])
                ->assertStatus(201);
        }

        $this->assertEquals(
            3,
            Horario::where('area_id', $this->area->id)
                ->where('dia', 'martes')
                ->where('tipo', false)
                ->count()
        );
    }

    /** @test */
    public function crear_grupo_con_area_ya_ocupada_por_otro_grupo_en_el_mismo_horario(): void
    {
        // Crear un grupo con horario vía POST /grupos (incluye horarios en el body)
        $grupo1 = $this->crearGrupo('Grupo Previo');

        $this->actingAs($this->usuario)
            ->postJson(route('grupos.horarios.store', $grupo1), [
                'horarios' => [
                    ['area_id' => $this->area->id, 'dia' => 'viernes', 'hora' => '14:00', 'duracion_horas' => 3],
                ],
            ])
            ->assertStatus(201);

        // Crear el segundo grupo incluyendo los horarios en el cuerpo del POST /grupos
        $response = $this->actingAs($this->usuario)
            ->postJson(route('grupos.store'), [
                'sede_id'    => $this->sede->id,
                'modulo_id'  => $this->modulo->id,
                'profesor_id' => $this->profesor->id,
                'nombre'     => 'Grupo Nuevo',
                'inscritos'  => 5,
                'jornada'    => 1,
                'status'     => 1,
                'horarios'   => [
                    ['area_id' => $this->area->id, 'dia' => 'viernes', 'hora' => '14:00', 'duracion_horas' => 3],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.nombre', 'Grupo Nuevo');
    }

    // =========================================================================
    // SEMANARIO: debe mostrar múltiples grupos en el mismo slot
    // =========================================================================

    /** @test */
    public function semanario_muestra_todos_los_grupos_que_comparten_area_y_horario(): void
    {
        $grupo1 = $this->crearGrupo('Grupo X');
        $grupo2 = $this->crearGrupo('Grupo Y');

        // Horario compartido
        foreach ([$grupo1, $grupo2] as $grupo) {
            Horario::create([
                'sede_id'       => $this->sede->id,
                'area_id'       => $this->area->id,
                'grupo_id'      => $grupo->id,
                'grupo_nombre'  => $grupo->nombre,
                'tipo'          => false,
                'periodo'       => true,
                'dia'           => 'miércoles',
                'hora'          => '09:00',
                'duracion_horas' => 2,
                'status'        => 1,
            ]);
        }

        $response = $this->actingAs($this->usuario)
            ->getJson(route('horarios.semanario', [
                'sede_id' => $this->sede->id,
                'area_id' => $this->area->id,
            ]));

        $response->assertOk();

        $ocupadosMiercoles = $response->json('data.por_dia.miércoles.ocupados');

        $this->assertCount(2, $ocupadosMiercoles);

        $gruposEnSlot = collect($ocupadosMiercoles)->pluck('grupo_nombre')->all();
        $this->assertContains('Grupo X', $gruposEnSlot);
        $this->assertContains('Grupo Y', $gruposEnSlot);
    }

    // =========================================================================
    // VALIDACIÓN INTERNA: solapes DENTRO del mismo grupo siguen bloqueados
    // =========================================================================

    /** @test */
    public function solapamiento_dentro_del_mismo_grupo_sigue_siendo_rechazado(): void
    {
        $grupo = $this->crearGrupo('Grupo Solo');

        // Dos bloques del mismo grupo en el mismo día que se solapan: 08:00–10:00 y 09:00–11:00
        $response = $this->actingAs($this->usuario)
            ->postJson(route('grupos.horarios.store', $grupo), [
                'horarios' => [
                    ['area_id' => $this->area->id, 'dia' => 'lunes', 'hora' => '08:00', 'duracion_horas' => 2],
                    ['area_id' => $this->area->id, 'dia' => 'lunes', 'hora' => '09:00', 'duracion_horas' => 2],
                ],
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function permiso_denegado_al_asignar_horarios_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $grupo = $this->crearGrupo('Grupo Sin Perm');

        $this->actingAs($sinPermiso)
            ->postJson(route('grupos.horarios.store', $grupo), [
                'horarios' => [
                    ['area_id' => $this->area->id, 'dia' => 'lunes', 'hora' => '08:00', 'duracion_horas' => 1],
                ],
            ])
            ->assertStatus(403);
    }

    /**
     * Escenario real del cliente: el grupo trabaja en área A (martes+jueves 06:00–10:00).
     * Al editarlo se le asigna área B (lunes+martes+jueves 06:00–10:00).
     * El frontend envía los horarios actuales (área A) + los nuevos (área B) en el mismo PUT.
     * El backend debe deduplicar por (dia, hora) conservando los de área B y guardar sin error.
     */
    /** @test */
    public function put_con_mismo_horario_en_distinta_area_deduplica_y_guarda_la_nueva(): void
    {
        $areaVieja = Area::factory()->active()->create(['nombre' => 'Área Vieja']);
        $areaNueva = Area::factory()->active()->create(['nombre' => 'Área Nueva']);

        $grupo = $this->crearGrupo('Grupo Cambio Area');

        // Horarios actuales del grupo: martes y jueves 06:00–09:00 en área vieja
        foreach (['martes', 'jueves'] as $dia) {
            foreach (['06:00', '07:00', '08:00', '09:00'] as $hora) {
                Horario::create([
                    'sede_id'       => $this->sede->id,
                    'area_id'       => $areaVieja->id,
                    'grupo_id'      => $grupo->id,
                    'grupo_nombre'  => $grupo->nombre,
                    'tipo'          => false,
                    'periodo'       => true,
                    'dia'           => $dia,
                    'hora'          => $hora,
                    'duracion_horas' => 1,
                    'status'        => 1,
                ]);
            }
        }

        // El frontend envía: horarios viejos (área vieja, martes+jueves 8 ítems)
        // + nuevos (área nueva, lunes+martes+jueves 12 ítems) = 20 ítems totales
        $horariosViejos = [];
        foreach (['martes', 'jueves'] as $dia) {
            foreach (['06:00', '07:00', '08:00', '09:00'] as $hora) {
                $horariosViejos[] = ['area_id' => $areaVieja->id, 'dia' => $dia, 'hora' => $hora, 'duracion_horas' => 1];
            }
        }
        $horariosNuevos = [];
        foreach (['lunes', 'martes', 'jueves'] as $dia) {
            foreach (['06:00', '07:00', '08:00', '09:00'] as $hora) {
                $horariosNuevos[] = ['area_id' => $areaNueva->id, 'dia' => $dia, 'hora' => $hora, 'duracion_horas' => 1];
            }
        }

        $response = $this->actingAs($this->usuario)
            ->putJson(route('grupos.horarios.update', $grupo), [
                'horarios' => array_merge($horariosViejos, $horariosNuevos), // 20 ítems
            ]);

        $response->assertStatus(200);

        // En BD deben quedar solo 12 horarios (lunes+martes+jueves × 4 horas) con área nueva
        $horariosGuardados = Horario::where('grupo_id', $grupo->id)
            ->where('tipo', false)
            ->whereNull('deleted_at')
            ->get();

        $this->assertCount(12, $horariosGuardados);

        // Todos deben tener el área nueva
        $this->assertTrue($horariosGuardados->every(fn($h) => $h->area_id === $areaNueva->id));

        // Los tres días deben estar presentes
        $dias = $horariosGuardados->pluck('dia')->unique()->sort()->values()->all();
        $this->assertEquals(['jueves', 'lunes', 'martes'], $dias);
    }

    /** @test */
    public function put_con_mismo_slot_y_misma_area_enviado_dos_veces_deduplica_sin_error(): void
    {
        $grupo = $this->crearGrupo('Grupo Dup');

        // Mismo slot exacto enviado dos veces — el backend deduplica y guarda uno solo
        $response = $this->actingAs($this->usuario)
            ->putJson(route('grupos.horarios.update', $grupo), [
                'horarios' => [
                    ['area_id' => $this->area->id, 'dia' => 'lunes', 'hora' => '08:00', 'duracion_horas' => 2],
                    ['area_id' => $this->area->id, 'dia' => 'lunes', 'hora' => '08:00', 'duracion_horas' => 2],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            Horario::where('grupo_id', $grupo->id)->where('dia', 'lunes')->where('tipo', false)->count()
        );
    }

    /** @test */
    public function put_con_solapamiento_real_sigue_siendo_rechazado(): void
    {
        $grupo = $this->crearGrupo('Grupo Solape');

        // 08:00–11:00 solapa con 10:00–12:00 (horas distintas pero rangos que se cruzan)
        $response = $this->actingAs($this->usuario)
            ->putJson(route('grupos.horarios.update', $grupo), [
                'horarios' => [
                    ['area_id' => $this->area->id, 'dia' => 'lunes', 'hora' => '08:00', 'duracion_horas' => 3],
                    ['area_id' => $this->area->id, 'dia' => 'lunes', 'hora' => '10:00', 'duracion_horas' => 2],
                ],
            ]);

        $response->assertStatus(422);

        $errores = $response->json('errors');
        $this->assertNotEmpty($errores);
        $this->assertStringContainsString('08:00', $errores[0]);
        $this->assertStringContainsString('11:00', $errores[0]);
        $this->assertStringContainsString('10:00', $errores[0]);
    }

    // =========================================================================
    // Helper
    // =========================================================================

    /**
     * Crea un grupo con los datos de setUp para evitar repetición.
     */
    private function crearGrupo(string $nombre): Grupo
    {
        return Grupo::factory()->create([
            'sede_id'    => $this->sede->id,
            'modulo_id'  => $this->modulo->id,
            'profesor_id' => $this->profesor->id,
            'nombre'     => $nombre,
            'inscritos'  => 10,
            'jornada'    => 0,
            'status'     => 1,
        ]);
    }
}
