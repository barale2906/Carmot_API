<?php

namespace Tests\Feature\Api\Crm;

use App\Models\Academico\Curso;
use App\Models\Crm\Agenda;
use App\Models\Crm\Referido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature de la agenda de citas del CRM.
 */
class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Referido $referido;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['crm_agendas', 'crm_agendaCrear', 'crm_agendaEditar', 'crm_agendaInactivar'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['crm_agendas', 'crm_agendaCrear', 'crm_agendaEditar', 'crm_agendaInactivar']);

        $this->referido = Referido::factory()->create([
            'curso_id'  => Curso::factory()->create()->id,
            'gestor_id' => $this->usuario->id,
            'nombre'    => 'Marta Díaz',
            'celular'   => '3151234567',
        ]);
    }

    private function crearAgenda(array $atributos = []): Agenda
    {
        return Agenda::factory()->create(array_merge([
            'referido_id'  => $this->referido->id,
            'agendador_id' => $this->usuario->id,
            'fecha'        => now()->toDateString(),
            'status'       => 0,
        ], $atributos));
    }

    /** @test */
    public function index_incluye_referido_agendador_y_texto_de_estado(): void
    {
        $this->crearAgenda();

        $this->actingAs($this->usuario)
            ->getJson(route('agendas.index'))
            ->assertOk()
            ->assertJsonPath('data.0.status_text', 'Agendado')
            ->assertJsonPath('data.0.referido.nombre', 'Marta Díaz')
            ->assertJsonPath('data.0.referido.status_text', $this->referido->status_text)
            ->assertJsonPath('data.0.agendador.id', $this->usuario->id);
    }

    /** @test */
    public function index_filtra_por_rango_de_fechas_estado_y_busqueda(): void
    {
        $this->crearAgenda(['fecha' => now()->toDateString()]);
        $this->crearAgenda(['fecha' => now()->addDays(10)->toDateString(), 'status' => 4]);

        $this->actingAs($this->usuario)
            ->getJson(route('agendas.index', ['fecha_desde' => now()->toDateString(), 'fecha_hasta' => now()->toDateString()]))
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->usuario)
            ->getJson(route('agendas.index', ['status' => 4]))
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->usuario)
            ->getJson(route('agendas.index', ['search' => '315123']))
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->usuario)
            ->getJson(route('agendas.index', ['search' => 'inexistente']))
            ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function store_asigna_agendador_por_defecto_y_crea_seguimiento_automatico(): void
    {
        $fecha = now()->addDay()->toDateString();

        $this->actingAs($this->usuario)
            ->postJson(route('agendas.store'), [
                'referido_id' => $this->referido->id,
                'fecha'       => $fecha,
                'hora'        => '09:30',
                'jornada'     => 'am',
            ])
            ->assertCreated()
            ->assertJsonPath('data.agendador_id', $this->usuario->id)
            ->assertJsonPath('data.status', 0)
            ->assertJsonPath('data.tipo', 'visita');

        $this->assertDatabaseHas('seguimientos', [
            'referido_id' => $this->referido->id,
            'seguidor_id' => $this->usuario->id,
            'seguimiento' => "AGENDA: Visita para el {$fecha} a las 09:30 (am)",
        ]);
    }

    /** @test */
    public function store_programa_una_llamada_con_nota_y_se_filtra_por_tipo(): void
    {
        $fecha = now()->addDays(2)->toDateString();

        $this->actingAs($this->usuario)
            ->postJson(route('agendas.store'), [
                'referido_id' => $this->referido->id,
                'fecha'       => $fecha,
                'hora'        => '15:00',
                'tipo'        => 'llamada',
                'nota'        => 'Confirmar si le sirve la jornada sábado.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tipo_text', 'Llamada')
            ->assertJsonPath('data.jornada', 'pm')
            ->assertJsonPath('data.nota', 'Confirmar si le sirve la jornada sábado.');

        $this->assertDatabaseHas('seguimientos', [
            'referido_id' => $this->referido->id,
            'seguimiento' => "AGENDA: Llamada para el {$fecha} a las 15:00 (pm). Confirmar si le sirve la jornada sábado.",
        ]);

        $this->crearAgenda(['tipo' => 'visita']);

        $this->actingAs($this->usuario)
            ->getJson(route('agendas.index', ['tipo' => 'llamada']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo', 'llamada');
    }

    /** @test */
    public function store_rechaza_tipo_invalido(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('agendas.store'), [
                'referido_id' => $this->referido->id,
                'fecha'       => now()->addDay()->toDateString(),
                'hora'        => '10:00',
                'tipo'        => 'correo',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo');
    }

    /** @test */
    public function store_rechaza_fecha_pasada_y_jornada_invalida(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('agendas.store'), [
                'referido_id' => $this->referido->id,
                'fecha'       => now()->subDay()->toDateString(),
                // Con una hora válida la jornada se deriva de ella; aquí la hora tampoco es válida
                'hora'        => '9:30 de la mañana',
                'jornada'     => 'noche',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha', 'hora', 'jornada']);
    }

    /** @test */
    public function update_cambia_estado_de_la_cita(): void
    {
        $agenda = $this->crearAgenda();

        $this->actingAs($this->usuario)
            ->putJson(route('agendas.update', $agenda), ['status' => 1])
            ->assertOk()
            ->assertJsonPath('data.status_text', 'Asistió');
    }

    /** @test */
    public function el_texto_del_resultado_depende_del_tipo_de_accion(): void
    {
        Permission::create(['name' => 'crm_referidos', 'descripcion' => 'crm_referidos']);
        $this->usuario->givePermissionTo('crm_referidos');

        $esperados = [
            'visita'  => ['Asistió', 'No asistió'],
            'llamada' => ['Contestó', 'No contestó'],
            'mensaje' => ['Respondió', 'Sin respuesta'],
            'otro'    => ['Realizada', 'No realizada'],
        ];

        foreach ($esperados as $tipo => [$realizada, $noRealizada]) {
            $agenda = $this->crearAgenda(['tipo' => $tipo]);

            $this->actingAs($this->usuario)
                ->putJson(route('agendas.update', $agenda), ['status' => 1])
                ->assertJsonPath('data.status_text', $realizada);
            $this->actingAs($this->usuario)
                ->putJson(route('agendas.update', $agenda), ['status' => 2])
                ->assertJsonPath('data.status_text', $noRealizada);
        }

        // La ficha del lead muestra el mismo texto
        $this->actingAs($this->usuario)
            ->getJson(route('referidos.show', ['referido' => $this->referido->id, 'with' => 'agendamientos']))
            ->assertOk()
            ->assertJsonFragment(['tipo' => 'llamada', 'status_text' => 'No contestó']);
    }

    /** @test */
    public function filters_entrega_los_estados_segun_el_tipo(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('agendas.filters'))
            ->assertOk()
            ->assertJsonPath('data.status_options.1', 'Realizada')
            ->assertJsonPath('data.status_options_por_tipo.llamada.1', 'Contestó')
            ->assertJsonPath('data.status_options_por_tipo.visita.2', 'No asistió')
            ->assertJsonPath('data.status_options_por_tipo.mensaje.4', 'Canceló');
    }

    /** @test */
    public function usuario_sin_permiso_recibe_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('agendas.index'))
            ->assertForbidden();
    }

    /** @test */
    public function la_cita_respeta_el_agendador_asignado_y_deriva_la_jornada_de_la_hora(): void
    {
        $asesora = User::factory()->create();

        $this->actingAs($this->usuario)
            ->postJson(route('agendas.store'), [
                'referido_id'  => $this->referido->id,
                'agendador_id' => $asesora->id,
                'fecha'        => now()->addDay()->toDateString(),
                'hora'         => '15:00',
                'jornada'      => 'am',
            ])
            ->assertCreated()
            ->assertJsonPath('data.fecha', now()->addDay()->toDateString());

        $this->assertDatabaseHas('agendas', ['agendador_id' => $asesora->id, 'jornada' => 'pm']);
    }
}
