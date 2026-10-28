<?php

namespace Tests\Feature\Api\Crm;

use App\Models\Academico\Curso;
use App\Models\Crm\Agenda;
use App\Models\Crm\Referido;
use App\Models\Crm\Seguimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature de la bitácora de seguimientos del CRM.
 */
class SeguimientoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Referido $referido;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['crm_seguimientos', 'crm_seguimientoCrear', 'crm_seguimientoEditar', 'crm_seguimientoInactivar'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['crm_seguimientos', 'crm_seguimientoCrear', 'crm_seguimientoEditar', 'crm_seguimientoInactivar']);

        $this->referido = Referido::factory()->create([
            'curso_id'  => Curso::factory()->create()->id,
            'gestor_id' => $this->usuario->id,
        ]);
    }

    /** @test */
    public function store_registra_seguimiento(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('seguimientos.store'), [
                'referido_id' => $this->referido->id,
                'seguidor_id' => $this->usuario->id,
                'fecha'       => now()->toDateString(),
                'seguimiento' => 'Llamada: interesado en jornada nocturna.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.seguimiento', 'Llamada: interesado en jornada nocturna.');
    }

    /** @test */
    public function store_actualiza_el_estado_del_lead_junto_con_el_seguimiento(): void
    {
        $this->referido->update(['status' => 0]);

        $this->actingAs($this->usuario)
            ->postJson(route('seguimientos.store'), [
                'referido_id'     => $this->referido->id,
                'seguidor_id'     => $this->usuario->id,
                'fecha'           => now()->toDateString(),
                'seguimiento'     => 'Confirmó que se inscribe el lunes.',
                'estado_referido' => 2,
            ])
            ->assertCreated();

        $this->assertSame(2, $this->referido->fresh()->status);
        $this->assertDatabaseHas('seguimientos', ['referido_id' => $this->referido->id, 'estado_referido' => 2]);
    }

    /** @test */
    public function store_programa_la_proxima_accion_en_la_agenda(): void
    {
        $this->darPermisoAgenda();
        $fecha = now()->addDays(3)->toDateString();

        $this->actingAs($this->usuario)
            ->postJson(route('seguimientos.store'), [
                'referido_id'    => $this->referido->id,
                'seguidor_id'    => $this->usuario->id,
                'fecha'          => now()->toDateString(),
                'seguimiento'    => 'Pide que lo llamen el jueves.',
                'proxima_accion' => ['tipo' => 'llamada', 'fecha' => $fecha, 'hora' => '16:30', 'nota' => 'Confirmar jornada.'],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('agendas', [
            'referido_id'  => $this->referido->id,
            'agendador_id' => $this->usuario->id,
            'tipo'         => 'llamada',
            'hora'         => '16:30:00',
            'jornada'      => 'pm',
            'nota'         => 'Confirmar jornada.',
            'status'       => 0,
        ]);
        // El seguimiento del asesor y la entrada automática de la agenda
        $this->assertSame(2, $this->referido->seguimientos()->count());
    }

    /** @test */
    public function store_rechaza_proxima_accion_invalida_sin_guardar_el_seguimiento(): void
    {
        $this->darPermisoAgenda();

        $this->actingAs($this->usuario)
            ->postJson(route('seguimientos.store'), [
                'referido_id'    => $this->referido->id,
                'seguidor_id'    => $this->usuario->id,
                'fecha'          => now()->toDateString(),
                'seguimiento'    => 'No contestó.',
                'proxima_accion' => ['tipo' => 'fax', 'fecha' => now()->subDay()->toDateString()],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['proxima_accion.tipo', 'proxima_accion.fecha', 'proxima_accion.hora']);

        $this->assertSame(0, $this->referido->seguimientos()->count());
    }

    /** @test */
    public function store_sin_permiso_de_agenda_no_programa_acciones(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('seguimientos.store'), [
                'referido_id'    => $this->referido->id,
                'seguidor_id'    => $this->usuario->id,
                'fecha'          => now()->toDateString(),
                'seguimiento'    => 'No contestó.',
                'proxima_accion' => ['tipo' => 'llamada', 'fecha' => now()->addDay()->toDateString(), 'hora' => '09:00'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('proxima_accion');

        $this->assertSame(0, Agenda::count());
    }

    /** @test */
    public function por_lead_agrupa_la_bitacora_con_el_estado_de_cada_seguimiento(): void
    {
        $this->referido->update(['nombre' => 'Camila Rojas', 'status' => 0]);
        $this->referido->registrarSeguimiento($this->usuario->id, now()->subDays(2)->toDateString(), 'Primer contacto', 1);
        $this->referido->registrarSeguimiento($this->usuario->id, now()->toDateString(), 'Confirma visita', 2);
        $sinSeguimientos = Referido::factory()->create(['curso_id' => $this->referido->curso_id, 'gestor_id' => $this->usuario->id, 'nombre' => 'Andrés Mora']);

        // Busca por nombre y trae todos los seguimientos del lead, del más reciente al más antiguo
        $this->actingAs($this->usuario)
            ->getJson(route('seguimientos.por-lead', ['search' => 'camila']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.seguimientos_count', 2)
            ->assertJsonPath('data.0.seguimientos.0.seguimiento', 'Confirma visita')
            ->assertJsonPath('data.0.seguimientos.0.estado_referido_text', 'Pendiente por matricular')
            ->assertJsonPath('data.0.seguimientos.0.seguidor.id', $this->usuario->id)
            ->assertJsonPath('data.0.seguimientos.1.estado_referido_text', 'Interesado')
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to']]);

        // Sin filtros incluye los leads sin seguimientos, al final
        $ids = collect($this->actingAs($this->usuario)->getJson(route('seguimientos.por-lead'))->json('data'))->pluck('id');
        $this->assertSame([$this->referido->id, $sinSeguimientos->id], $ids->all());

        // El rango de fechas deja solo los leads con seguimientos en él
        $this->actingAs($this->usuario)
            ->getJson(route('seguimientos.por-lead', ['fecha_desde' => now()->toDateString()]))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->referido->id);
    }

    /** @test */
    public function index_busca_por_nombre_del_lead(): void
    {
        $this->referido->update(['nombre' => 'Camila Rojas']);
        Seguimiento::factory()->create(['referido_id' => $this->referido->id, 'seguidor_id' => $this->usuario->id, 'seguimiento' => 'Llamada']);

        $this->actingAs($this->usuario)
            ->getJson(route('seguimientos.index', ['search' => 'Rojas']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function por_lead_en_la_misma_fecha_ordena_por_nombre(): void
    {
        $hoy = now()->toDateString();
        $this->referido->update(['nombre' => 'Zoraida Pérez']);
        $this->referido->registrarSeguimiento($this->usuario->id, $hoy, 'Llamada');
        $bruno = $this->crearLead('Bruno Díaz');
        $bruno->registrarSeguimiento($this->usuario->id, $hoy, 'Llamada');
        $antiguo = $this->crearLead('Ana Mora');
        $antiguo->registrarSeguimiento($this->usuario->id, now()->subDays(5)->toDateString(), 'Llamada');

        $nombres = collect($this->actingAs($this->usuario)->getJson(route('seguimientos.por-lead'))->json('data'))->pluck('nombre');

        $this->assertSame(['Bruno Díaz', 'Zoraida Pérez', 'Ana Mora'], $nombres->all());
    }

    /** @test */
    public function excel_descarga_los_seguimientos_del_lapso_ordenados_por_fecha_y_nombre(): void
    {
        $this->referido->update(['nombre' => 'Zoraida Pérez', 'status' => 0]);
        $this->referido->registrarSeguimiento($this->usuario->id, '2026-10-20', 'Primer contacto', 1);
        $this->referido->registrarSeguimiento($this->usuario->id, '2026-10-01', 'Fuera del lapso');
        $bruno = $this->crearLead('Bruno Díaz');
        $bruno->registrarSeguimiento($this->usuario->id, '2026-10-20', 'Pide información');
        $bruno->registrarSeguimiento($this->usuario->id, '2026-10-22', 'Confirma visita', 2);

        $response = $this->actingAs($this->usuario)
            ->get(route('seguimientos.excel', ['fecha_desde' => '2026-10-15', 'fecha_hasta' => '2026-10-31']));

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('seguimientos-2026-10-15-a-2026-10-31.xlsx', $response->headers->get('Content-Disposition'));

        $filas = $this->leerExcel($response->streamedContent());
        $this->assertSame(['Fecha', 'Lead', 'Celular', 'Ciudad', 'Curso de interés', 'Asesor', 'Registró', 'Seguimiento', 'Estado del lead'], $filas[0]);
        $this->assertSame(
            [
                ['2026-10-22', 'Bruno Díaz', 'Confirma visita', 'Pendiente por matricular'],
                ['2026-10-20', 'Bruno Díaz', 'Pide información', 'Creado'],
                ['2026-10-20', 'Zoraida Pérez', 'Primer contacto', 'Interesado'],
            ],
            array_map(fn ($f) => [$f[0], $f[1], $f[7], $f[8]], array_slice($filas, 1))
        );

        // Respeta la búsqueda de la bitácora
        $filtradas = $this->leerExcel($this->actingAs($this->usuario)
            ->get(route('seguimientos.excel', ['fecha_desde' => '2026-10-15', 'fecha_hasta' => '2026-10-31', 'search' => 'zoraida']))
            ->streamedContent());
        $this->assertCount(2, $filtradas);
    }

    /** @test */
    public function excel_exige_un_lapso_valido_y_el_permiso(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('seguimientos.excel'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_desde', 'fecha_hasta']);

        $this->actingAs($this->usuario)
            ->getJson(route('seguimientos.excel', ['fecha_desde' => '2026-10-20', 'fecha_hasta' => '2026-10-10']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_hasta');

        $this->actingAs(User::factory()->create())
            ->getJson(route('seguimientos.excel', ['fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-31']))
            ->assertForbidden();
    }

    private function crearLead(string $nombre): Referido
    {
        return Referido::factory()->create(['curso_id' => $this->referido->curso_id, 'gestor_id' => $this->usuario->id, 'nombre' => $nombre, 'status' => 0]);
    }

    /** Filas de la primera hoja de un xlsx descargado. */
    private function leerExcel(string $contenido): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'seg') . '.xlsx';
        file_put_contents($ruta, $contenido);
        $filas = IOFactory::load($ruta)->getActiveSheet()->toArray();
        unlink($ruta);

        return $filas;
    }

    private function darPermisoAgenda(): void
    {
        Permission::create(['name' => 'crm_agendaCrear', 'descripcion' => 'crm_agendaCrear']);
        $this->usuario->givePermissionTo('crm_agendaCrear');
    }

    /** @test */
    public function store_sin_estado_deja_el_lead_igual(): void
    {
        $this->referido->update(['status' => 1]);

        $this->actingAs($this->usuario)
            ->postJson(route('seguimientos.store'), [
                'referido_id' => $this->referido->id,
                'seguidor_id' => $this->usuario->id,
                'fecha'       => now()->toDateString(),
                'seguimiento' => 'No contestó.',
            ])
            ->assertCreated();

        $this->assertSame(1, $this->referido->fresh()->status);
    }

    /** @test */
    public function store_no_permite_marcar_matriculado_a_mano(): void
    {
        $this->referido->update(['status' => 1]);

        $this->actingAs($this->usuario)
            ->postJson(route('seguimientos.store'), [
                'referido_id'     => $this->referido->id,
                'seguidor_id'     => $this->usuario->id,
                'fecha'           => now()->toDateString(),
                'seguimiento'     => 'Dice que ya pagó.',
                'estado_referido' => Referido::ESTADO_MATRICULADO,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado_referido');

        $this->assertSame(1, $this->referido->fresh()->status);
        $this->assertSame(0, $this->referido->seguimientos()->count());
    }

    /** @test */
    public function store_valida_campos_obligatorios(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('seguimientos.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['referido_id', 'seguidor_id', 'fecha', 'seguimiento']);
    }

    /** @test */
    public function by_referido_lista_solo_la_bitacora_del_lead(): void
    {
        $otro = Referido::factory()->create(['curso_id' => $this->referido->curso_id, 'gestor_id' => $this->usuario->id]);
        Seguimiento::factory()->count(2)->create(['referido_id' => $this->referido->id, 'seguidor_id' => $this->usuario->id]);
        Seguimiento::factory()->create(['referido_id' => $otro->id, 'seguidor_id' => $this->usuario->id]);

        $this->actingAs($this->usuario)
            ->getJson(route('seguimientos.by-referido', $this->referido->id))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function usuario_sin_permiso_recibe_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('seguimientos.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->getJson(route('seguimientos.por-lead'))
            ->assertForbidden();
    }
}
