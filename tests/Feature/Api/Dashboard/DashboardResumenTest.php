<?php

namespace Tests\Feature\Api\Dashboard;

use App\Models\Academico\Matricula;
use App\Models\Configuracion\DiaNoLaborable;
use App\Models\Configuracion\Sede;
use App\Models\Crm\Agenda;
use App\Models\Crm\Referido;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Resumen del dashboard: secciones por permiso y cifras limitadas a las sedes del usuario.
 */
class DashboardResumenTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Sede $sedePropia;
    private Sede $sedeAjena;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['aca_matriculas', 'crm_agendas', 'crm_referidos', 'fin_carteras', 'fin_reciboPagoAprobar'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->sedePropia = Sede::factory()->create();
        $this->sedeAjena  = Sede::factory()->create();

        $this->usuario = User::factory()->create();
        $this->usuario->sedes()->attach($this->sedePropia->id);
    }

    private function resumen(): array
    {
        return $this->actingAs($this->usuario)
            ->getJson(route('dashboard.resumen'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['tarjetas', 'alertas', 'actividad', 'eventos', 'retencion']])
            ->json('data');
    }

    private function tarjeta(array $resumen, string $clave): ?array
    {
        return collect($resumen['tarjetas'])->firstWhere('clave', $clave);
    }

    /** @test */
    public function sin_permisos_no_expone_cifras_de_ningun_modulo(): void
    {
        Matricula::factory()->create(['sede_id' => $this->sedePropia->id, 'status' => 1]);

        $resumen = $this->resumen();

        $this->assertSame([], $resumen['tarjetas']);
        $this->assertSame([], $resumen['alertas']);
        $this->assertSame([], $resumen['actividad']);
        $this->assertNull($resumen['retencion']);
    }

    /** @test */
    public function estudiantes_activos_y_retencion_solo_cuentan_las_sedes_del_usuario(): void
    {
        $this->usuario->givePermissionTo('aca_matriculas');

        Matricula::factory()->count(3)->create([
            'sede_id' => $this->sedePropia->id, 'status' => 1, 'fecha_matricula' => now()->subMonth(),
        ]);
        Matricula::factory()->create([
            'sede_id' => $this->sedePropia->id, 'status' => 0, 'fecha_matricula' => now()->subMonth(),
            'estado_academico' => Matricula::ESTADO_DESERTADO,
        ]);
        Matricula::factory()->create(['sede_id' => $this->sedeAjena->id, 'status' => 1, 'fecha_matricula' => now()->subMonth()]);

        $resumen = $this->resumen();

        $this->assertSame(3, $this->tarjeta($resumen, 'estudiantes_activos')['valor']);
        $this->assertEquals(75.0, $resumen['retencion']['tasa']);
        $this->assertSame(1, $resumen['retencion']['desertados']);
        $this->assertNotEmpty($resumen['actividad']);
    }

    /** @test */
    public function citas_de_hoy_cuenta_solo_las_pendientes(): void
    {
        $this->usuario->givePermissionTo('crm_agendas');
        $referido = Referido::factory()->create([
            'curso_id' => \App\Models\Academico\Curso::factory()->create()->id, 'gestor_id' => $this->usuario->id,
        ]);
        $base = ['referido_id' => $referido->id, 'agendador_id' => $this->usuario->id, 'fecha' => now()->toDateString()];
        Agenda::factory()->create($base + ['status' => 0]);
        Agenda::factory()->create($base + ['status' => 1]);
        Agenda::factory()->create(['fecha' => now()->addDay()->toDateString(), 'status' => 0] + $base);

        $this->assertSame(1, $this->tarjeta($this->resumen(), 'citas_hoy')['valor']);
    }

    /** @test */
    public function cartera_vencida_cuenta_estudiantes_en_mora_de_sus_sedes(): void
    {
        $this->usuario->givePermissionTo('fin_carteras');
        $estudiante = User::factory()->create();
        $base = ['sede_id' => $this->sedePropia->id, 'estudiante_id' => $estudiante->id, 'saldo' => 100000, 'valor' => 100000];

        Cartera::factory()->create($base + ['fecha_vencimiento' => now()->subDays(40)->toDateString()]);
        Cartera::factory()->create($base + ['fecha_vencimiento' => now()->subDays(5)->toDateString()]);
        Cartera::factory()->create($base + ['fecha_vencimiento' => now()->addDays(5)->toDateString()]);
        Cartera::factory()->create(['sede_id' => $this->sedeAjena->id, 'saldo' => 50000, 'fecha_vencimiento' => now()->subDays(40)->toDateString()]);

        $resumen = $this->resumen();

        $this->assertSame(1, $this->tarjeta($resumen, 'cartera_vencida')['valor']);
        $this->assertStringContainsString('200.000', $this->tarjeta($resumen, 'cartera_vencida')['descripcion']);
        $this->assertSame(1, collect($resumen['alertas'])->firstWhere('clave', 'cartera_vencida_30')['cantidad']);
    }

    /** @test */
    public function las_alertas_sin_casos_no_se_muestran(): void
    {
        $this->usuario->givePermissionTo(['fin_reciboPagoAprobar', 'crm_referidos']);

        $this->assertSame([], $this->resumen()['alertas']);

        ReciboPago::factory()->create(['sede_id' => $this->sedePropia->id, 'status' => ReciboPago::STATUS_PENDIENTE_APROBACION]);

        $alerta = collect($this->resumen()['alertas'])->firstWhere('clave', 'transferencias_por_aprobar');
        $this->assertSame(1, $alerta['cantidad']);
    }

    /** @test */
    public function un_cierre_de_varios_dias_aparece_como_un_solo_evento(): void
    {
        foreach (range(1, 4) as $dia) {
            DiaNoLaborable::factory()->create([
                'fecha' => now()->addDays($dia)->toDateString(), 'nombre' => 'Reparaciones', 'sede_id' => $this->sedePropia->id,
            ]);
        }
        DiaNoLaborable::factory()->create(['fecha' => now()->addDays(10)->toDateString(), 'nombre' => 'Reparaciones', 'sede_id' => $this->sedePropia->id]);
        DiaNoLaborable::factory()->create(['fecha' => now()->addDays(2)->toDateString(), 'nombre' => 'Festivo', 'sede_id' => null]);
        DiaNoLaborable::factory()->create(['fecha' => now()->addDays(3)->toDateString(), 'nombre' => 'Ajena', 'sede_id' => $this->sedeAjena->id]);

        $eventos = $this->resumen()['eventos'];

        $this->assertSame(['Reparaciones', 'Festivo', 'Reparaciones'], array_column($eventos, 'titulo'));
        $this->assertSame(now()->addDays(1)->toDateString(), $eventos[0]['fecha']);
        $this->assertSame(now()->addDays(4)->toDateString(), $eventos[0]['fecha_fin']);
        $this->assertNull($eventos[1]['fecha_fin']);
        $this->assertNull($eventos[2]['fecha_fin']);
    }

    /** @test */
    public function requiere_autenticacion(): void
    {
        $this->getJson(route('dashboard.resumen'))->assertUnauthorized();
    }
}
