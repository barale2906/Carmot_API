<?php

namespace Tests\Feature\Api\Financiero\Cartera;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\Cartera\ProrrogaPago;
use App\Models\User;
use App\Notifications\Financiero\ProrrogaPagoVencidaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Prórrogas de pago: plazo nuevo (máx. 15 días) para un estudiante en mora que no modifica la
 * cartera, se muestra en la planilla de asistencia y se vigila a diario.
 */
class ProrrogaPagoTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $estudiante;

    private Matricula $matricula;

    private Cartera $cuota;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['fin_prorrogasPago', 'fin_prorrogaPagoCrear', 'fin_prorrogaPagoCancelar', 'aca_asistencias'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }
        Role::firstOrCreate(['name' => 'superusuario']);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('superusuario');
        $this->staff->givePermissionTo(['fin_prorrogasPago', 'fin_prorrogaPagoCrear', 'fin_prorrogaPagoCancelar', 'aca_asistencias']);

        $this->matricula = Matricula::factory()->create(['status' => 1, 'estudiante_id' => User::factory()->create()->id]);
        $this->estudiante = $this->matricula->estudiante;
        $this->cuota = $this->cuotaVencida();
    }

    private function cuotaVencida(float $saldo = 300000): Cartera
    {
        return Cartera::factory()->create([
            'matricula_id' => $this->matricula->id,
            'sede_id' => $this->matricula->sede_id,
            'estudiante_id' => $this->matricula->estudiante_id,
            'numero_cuota' => 1,
            'valor' => $saldo, 'saldo' => $saldo, 'abono' => 0, 'descuento' => 0, 'mora_acumulada' => 0,
            'fecha_vencimiento' => now()->subDays(5)->toDateString(),
            'status' => Cartera::getStatusKey('Activa'),
        ]);
    }

    private function datos(array $extra = []): array
    {
        return $extra + [
            'estudiante_id' => $this->estudiante->id,
            'fecha_compromiso' => today()->addDays(7)->toDateString(),
            'observacion' => 'Paga el viernes',
        ];
    }

    private function registrar(): ProrrogaPago
    {
        return ProrrogaPago::registrar($this->estudiante->id, today()->addDays(7)->toDateString(), null, $this->staff);
    }

    /** @test */
    public function registra_el_plazo_sin_modificar_la_cartera(): void
    {
        $antes = $this->cuota->only(['fecha_vencimiento', 'saldo', 'status', 'valor']);

        $this->actingAs($this->staff)->postJson('/api/financiero/prorrogas-pago', $this->datos())
            ->assertCreated()
            ->assertJsonPath('data.estado', 'vigente')
            ->assertJsonPath('data.saldo_vencido', 300000)
            ->assertJsonPath('data.cartera_ids', [$this->cuota->id])
            ->assertJsonPath('data.registrada_por_id', $this->staff->id);

        $this->assertEquals($antes, $this->cuota->fresh()->only(['fecha_vencimiento', 'saldo', 'status', 'valor']));
    }

    /** @test */
    public function no_permite_mas_de_15_dias_ni_fechas_pasadas(): void
    {
        $this->actingAs($this->staff)->postJson('/api/financiero/prorrogas-pago', $this->datos(['fecha_compromiso' => today()->addDays(16)->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors('fecha_compromiso');

        $this->postJson('/api/financiero/prorrogas-pago', $this->datos(['fecha_compromiso' => today()->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors('fecha_compromiso');

        $this->postJson('/api/financiero/prorrogas-pago', $this->datos(['fecha_compromiso' => today()->addDays(15)->toDateString()]))
            ->assertCreated();
    }

    /** @test */
    public function exige_estudiante_y_fecha(): void
    {
        $this->actingAs($this->staff)->postJson('/api/financiero/prorrogas-pago', [])
            ->assertStatus(422)->assertJsonValidationErrors(['estudiante_id', 'fecha_compromiso']);
    }

    /** @test */
    public function rechaza_a_un_estudiante_sin_mora(): void
    {
        $this->cuota->update(['saldo' => 0]);

        $this->actingAs($this->staff)->postJson('/api/financiero/prorrogas-pago', $this->datos())
            ->assertStatus(422)->assertJsonValidationErrors('estudiante_id');
    }

    /** @test */
    public function no_permite_dos_plazos_vigentes_del_mismo_estudiante(): void
    {
        $this->actingAs($this->staff)->postJson('/api/financiero/prorrogas-pago', $this->datos())->assertCreated();

        $this->postJson('/api/financiero/prorrogas-pago', $this->datos())
            ->assertStatus(422)->assertJsonValidationErrors('estudiante_id');
    }

    /** @test */
    public function sin_permiso_no_registra_ni_cancela(): void
    {
        $sinPermiso = User::factory()->create();
        $prorroga = $this->registrar();

        $this->actingAs($sinPermiso)->postJson('/api/financiero/prorrogas-pago', $this->datos())->assertForbidden();
        $this->deleteJson("/api/financiero/prorrogas-pago/{$prorroga->id}")->assertForbidden();
        $this->getJson('/api/financiero/prorrogas-pago')->assertForbidden();
    }

    /** @test */
    public function cancelar_la_deja_fuera_de_la_asistencia_y_permite_otra(): void
    {
        $prorroga = $this->registrar();

        $this->actingAs($this->staff)->deleteJson("/api/financiero/prorrogas-pago/{$prorroga->id}")->assertOk();

        $this->assertSoftDeleted('prorrogas_pago', ['id' => $prorroga->id]);
        $this->assertTrue(ProrrogaPago::vigentesPorEstudiante([$this->estudiante->id])->isEmpty());
        $this->postJson('/api/financiero/prorrogas-pago', $this->datos())->assertCreated();
    }

    /** @test */
    public function lista_y_filtra_por_estado(): void
    {
        $prorroga = $this->registrar();
        $otraMatricula = Matricula::factory()->create(['status' => 1, 'sede_id' => $this->matricula->sede_id]);
        ProrrogaPago::create([
            'estudiante_id' => $otraMatricula->estudiante_id, 'sede_id' => $this->matricula->sede_id, 'cartera_ids' => [],
            'saldo_vencido' => 1, 'fecha_acuerdo' => today(), 'fecha_compromiso' => today()->addDay(), 'estado' => 'cumplido',
        ]);

        $this->actingAs($this->staff)->getJson('/api/financiero/prorrogas-pago?estado=vigente')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $prorroga->id)->assertJsonPath('meta.total', 1);

        $this->getJson('/api/financiero/prorrogas-pago?estado=otro')->assertStatus(422);
    }

    /** @test */
    public function el_alumno_solo_ve_sus_plazos(): void
    {
        $alumno = $this->estudiante;
        $alumno->assignRole(Role::firstOrCreate(['name' => User::ROL_ALUMNO]));
        $alumno->givePermissionTo('fin_prorrogasPago');
        $prorroga = $this->registrar();
        $otraMatricula = Matricula::factory()->create(['status' => 1, 'estudiante_id' => User::factory()->create()->id]);
        $ajena = ProrrogaPago::create([
            'estudiante_id' => $otraMatricula->estudiante_id, 'sede_id' => $otraMatricula->sede_id, 'cartera_ids' => [],
            'saldo_vencido' => 1, 'fecha_acuerdo' => today(), 'fecha_compromiso' => today()->addDay(),
        ]);

        $this->actingAs($alumno)->getJson('/api/financiero/prorrogas-pago')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($alumno)->getJson('/api/financiero/prorrogas-pago')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/financiero/prorrogas-pago/{$prorroga->id}")->assertOk();
        $this->getJson("/api/financiero/prorrogas-pago/{$ajena->id}")->assertForbidden();
    }

    /** @test */
    public function el_informe_de_cartera_cuenta_las_veces_que_se_acordo_un_plazo(): void
    {
        $vigente = $this->registrar();
        $this->cuota->update(['saldo' => 0]);
        $vigente->update(['estado' => ProrrogaPago::CUMPLIDO]);
        $this->cuota->update(['saldo' => 300000]);
        $incumplida = $this->registrar();
        $incumplida->update(['estado' => ProrrogaPago::INCUMPLIDO]);
        $this->registrar();
        $cancelada = $this->registrar();
        $cancelada->delete();
        $this->staff->givePermissionTo(Permission::create(['name' => 'fin_carteras', 'descripcion' => 'x']));

        $this->actingAs($this->staff)->getJson(route('carteras.index', ['estudiante_id' => $this->estudiante->id]))
            ->assertOk()
            ->assertJsonPath('data.0.prorrogas_pago', ['total' => 3, 'vigentes' => 1, 'cumplidas' => 1, 'incumplidas' => 1]);

        $this->getJson(route('carteras.reportes'))
            ->assertOk()
            ->assertJsonPath('data.prorrogas_pago', ['total' => 3, 'vigentes' => 1, 'cumplidas' => 1, 'incumplidas' => 1]);

        $this->getJson(route('carteras.reportes', ['fecha_desde' => today()->addDay()->toDateString(), 'fecha_hasta' => today()->addDays(2)->toDateString()]))
            ->assertJsonPath('data.prorrogas_pago.total', 0);
    }

    /** @test */
    public function aparece_en_la_planilla_de_asistencia_mientras_este_vigente(): void
    {
        $ciclo = Ciclo::factory()->create(['status' => 1, 'fecha_inicio' => now()->subMonth()->toDateString(), 'fecha_fin' => now()->addMonth()->toDateString()]);
        $grupo = Grupo::factory()->create(['status' => 1]);
        $ciclo->grupos()->attach($grupo->id, ['orden' => 1]);
        $this->matricula->update(['ciclo_id' => $ciclo->id, 'curso_id' => $ciclo->curso_id]);

        $this->actingAs($this->staff)->getJson("/api/academico/asistencias/lista-asistencia/{$grupo->id}")
            ->assertOk()->assertJsonPath('data.estudiantes.0.prorroga_pago', null);

        $this->registrar();

        $this->getJson("/api/academico/asistencias/lista-asistencia/{$grupo->id}")
            ->assertOk()
            ->assertJsonPath('data.estudiantes.0.en_mora', true)
            ->assertJsonPath('data.estudiantes.0.prorroga_pago.dias_restantes', 7)
            ->assertJsonPath('data.estudiantes.0.prorroga_pago.fecha_compromiso', today()->addDays(7)->toDateString());
    }

    /** @test */
    public function una_prorroga_con_cuotas_pagadas_deja_de_mostrarse(): void
    {
        $this->registrar();
        $this->cuota->update(['saldo' => 0]);

        $this->assertTrue(ProrrogaPago::vigentesPorEstudiante([$this->estudiante->id])->isEmpty());
    }

    /** @test */
    public function el_comando_marca_cumplida_la_pagada_e_incumplida_la_vencida_y_avisa(): void
    {
        Notification::fake();
        $prorroga = $this->registrar();
        $prorroga->update(['fecha_compromiso' => today()->subDay()]);

        $this->artisan('financiero:verificar-prorrogas-pago')->assertSuccessful();

        $this->assertEquals(ProrrogaPago::INCUMPLIDO, $prorroga->fresh()->estado);
        Notification::assertSentTo($this->estudiante, ProrrogaPagoVencidaNotification::class);
        Notification::assertSentTo($this->staff, ProrrogaPagoVencidaNotification::class);

        // Pagada a tiempo: se cierra como cumplida y no avisa
        Notification::fake();
        $otra = $this->registrar();
        $otra->update(['fecha_compromiso' => today()->subDay()]);
        $this->cuota->update(['saldo' => 0]);

        $this->artisan('financiero:verificar-prorrogas-pago')->assertSuccessful();

        $this->assertEquals(ProrrogaPago::CUMPLIDO, $otra->fresh()->estado);
        Notification::assertNothingSent();
    }

    /** @test */
    public function el_comando_no_toca_las_vigentes(): void
    {
        $prorroga = $this->registrar();

        $this->artisan('financiero:verificar-prorrogas-pago')->assertSuccessful();

        $this->assertEquals(ProrrogaPago::VIGENTE, $prorroga->fresh()->estado);
    }
}
