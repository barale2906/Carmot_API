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
            ->assertJsonPath('data.status', 0);

        $this->assertDatabaseHas('seguimientos', [
            'referido_id' => $this->referido->id,
            'seguidor_id' => $this->usuario->id,
            'seguimiento' => "AGENDA: Cita agendada para el {$fecha} a las 09:30 (am)",
        ]);
    }

    /** @test */
    public function store_rechaza_fecha_pasada_y_jornada_invalida(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('agendas.store'), [
                'referido_id' => $this->referido->id,
                'fecha'       => now()->subDay()->toDateString(),
                'hora'        => '09:30',
                'jornada'     => 'noche',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha', 'jornada']);
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
    public function usuario_sin_permiso_recibe_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('agendas.index'))
            ->assertForbidden();
    }
}
