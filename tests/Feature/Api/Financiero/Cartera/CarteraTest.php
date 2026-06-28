<?php

namespace Tests\Feature\Api\Financiero\Cartera;

use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del módulo Cartera: consultas, anulación y estados.
 */
class CarteraTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Matricula $matricula;
    private Sede $sede;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'fin_carteras',       'descripcion' => 'ver carteras']);
        Permission::create(['name' => 'fin_carteraAnular',  'descripcion' => 'anular cartera']);
        Permission::create(['name' => 'fin_carteraAcuerdo', 'descripcion' => 'acuerdo de pago']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['fin_carteras', 'fin_carteraAnular', 'fin_carteraAcuerdo']);

        $this->sede = Sede::first() ?? Sede::factory()->create();

        // Matrícula con estudiante
        $this->matricula = Matricula::factory()->create([
            'status'         => 1,
            'lp_precio_producto_id' => null, // sin LP para no disparar el servicio en setUp
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function crearCartera(array $attrs = []): Cartera
    {
        return Cartera::factory()->create(array_merge([
            'matricula_id'  => $this->matricula->id,
            'sede_id'       => $this->sede->id,
            'estudiante_id' => $this->matricula->estudiante_id,
        ], $attrs));
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function lista_carteras_paginadas(): void
    {
        $this->crearCartera();
        $this->crearCartera(['status' => Cartera::getStatusKey('Abonada')]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('carteras.index'));

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    /** @test */
    public function rechaza_index_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('carteras.index'))
            ->assertForbidden();
    }

    /** @test */
    public function filtra_carteras_por_matricula_id(): void
    {
        $this->crearCartera();
        $otraMatricula = Matricula::factory()->create(['lp_precio_producto_id' => null]);
        Cartera::factory()->create(['matricula_id' => $otraMatricula->id, 'sede_id' => $this->sede->id, 'estudiante_id' => $otraMatricula->estudiante_id]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('carteras.index', ['matricula_id' => $this->matricula->id]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->matricula->id, $data[0]['matricula_id']);
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function muestra_detalle_de_una_cartera(): void
    {
        $cartera = $this->crearCartera();

        $this->actingAs($this->usuario)
            ->getJson(route('carteras.show', $cartera))
            ->assertOk()
            ->assertJsonPath('data.id', $cartera->id)
            ->assertJsonPath('data.status_text', 'Activa');
    }

    // ─── deudasEstudiante ─────────────────────────────────────────────────────

    /** @test */
    public function deudas_estudiante_agrupa_por_matricula(): void
    {
        // Dos cuotas de la misma matrícula
        $this->crearCartera(['saldo' => 100000, 'valor' => 100000]);
        $this->crearCartera(['saldo' => 150000, 'valor' => 150000]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('carteras.deudas-estudiante', ['estudiante_id' => $this->matricula->estudiante_id]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(250000, $data[0]['total_saldo']);
        $this->assertEquals(2, $data[0]['carteras_count']);
    }

    /** @test */
    public function deudas_estudiante_requiere_estudiante_id(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('carteras.deudas-estudiante'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estudiante_id']);
    }

    // ─── detalleMatricula ─────────────────────────────────────────────────────

    /** @test */
    public function detalle_matricula_separa_vencidas_y_proximas(): void
    {
        $hoy = now()->toDateString();

        $this->crearCartera(['fecha_vencimiento' => now()->subDays(10)->toDateString()]);  // vencida
        $this->crearCartera(['fecha_vencimiento' => now()->addDays(15)->toDateString()]);  // próxima

        $response = $this->actingAs($this->usuario)
            ->getJson(route('carteras.detalle-matricula', ['matricula_id' => $this->matricula->id]));

        $response->assertOk()
            ->assertJsonStructure(['data' => ['vencidas', 'proximas', 'siguiente_cuota', 'total_saldo', 'descuento_disponible']]);

        $this->assertCount(1, $response->json('data.vencidas'));
        $this->assertCount(1, $response->json('data.proximas'));
    }

    // ─── anular ───────────────────────────────────────────────────────────────

    /** @test */
    public function anula_una_cartera_activa(): void
    {
        $cartera = $this->crearCartera(['status' => Cartera::getStatusKey('Activa')]);

        $this->actingAs($this->usuario)
            ->postJson(route('carteras.anular', $cartera))
            ->assertOk()
            ->assertJsonPath('data.status_text', 'Anulada');

        $this->assertDatabaseHas('carteras', [
            'id'     => $cartera->id,
            'status' => Cartera::getStatusKey('Anulada'),
        ]);
    }

    /** @test */
    public function no_puede_anular_cartera_cerrada(): void
    {
        $cartera = $this->crearCartera(['status' => Cartera::getStatusKey('Cerrada')]);

        $this->actingAs($this->usuario)
            ->postJson(route('carteras.anular', $cartera))
            ->assertUnprocessable();
    }

    /** @test */
    public function rechaza_anulacion_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $cartera    = $this->crearCartera();

        $this->actingAs($sinPermiso)
            ->postJson(route('carteras.anular', $cartera))
            ->assertForbidden();
    }

    // ─── acuerdo de pago ──────────────────────────────────────────────────────

    /** @test */
    public function registra_un_acuerdo_de_pago(): void
    {
        $this->crearCartera(['status' => Cartera::getStatusKey('Activa')]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('carteras.acuerdo-pago'), [
                'matricula_id'  => $this->matricula->id,
                'monto_inicial' => 50000,
                'numero_cuotas' => 3,
                'valor_cuota'   => 100000,
                'observaciones' => 'Acuerdo de prueba',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['carteras_acuerdo', 'total_reestructurado']]);

        // La cartera original debe quedar "En Acuerdo"
        $this->assertDatabaseHas('carteras', [
            'matricula_id' => $this->matricula->id,
            'status'       => Cartera::getStatusKey('En Acuerdo'),
        ]);

        // Deben existir 1 cartera inicial + 3 cuotas del acuerdo
        $nuevas = Cartera::where('matricula_id', $this->matricula->id)
            ->where('status', Cartera::getStatusKey('Activa'))
            ->count();

        $this->assertEquals(4, $nuevas);
    }

    /** @test */
    public function acuerdo_pago_valida_campos_requeridos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('carteras.acuerdo-pago'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['matricula_id', 'monto_inicial', 'numero_cuotas', 'valor_cuota']);
    }

    // ─── HasCarteraStatus ─────────────────────────────────────────────────────

    /** @test */
    public function getStatusKey_retorna_el_entero_correcto(): void
    {
        $this->assertEquals(0, Cartera::getStatusKey('Activa'));
        $this->assertEquals(1, Cartera::getStatusKey('Abonada'));
        $this->assertEquals(2, Cartera::getStatusKey('Cerrada'));
        $this->assertEquals(3, Cartera::getStatusKey('Anulada'));
        $this->assertEquals(4, Cartera::getStatusKey('En Acuerdo'));
        $this->assertNull(Cartera::getStatusKey('Inexistente'));
    }

    /** @test */
    public function aplicar_pago_actualiza_saldo_y_status(): void
    {
        $cartera = $this->crearCartera(['valor' => 200000, 'saldo' => 200000]);
        $cartera->aplicarPago(100000);

        $cartera->refresh();
        $this->assertEquals(100000, $cartera->saldo);
        $this->assertEquals(100000, $cartera->abono);
        $this->assertEquals(Cartera::getStatusKey('Abonada'), $cartera->status);
    }

    /** @test */
    public function aplicar_pago_total_cierra_la_cartera(): void
    {
        $cartera = $this->crearCartera(['valor' => 100000, 'saldo' => 100000]);
        $cartera->aplicarPago(100000);

        $cartera->refresh();
        $this->assertEquals(0, $cartera->saldo);
        $this->assertEquals(Cartera::getStatusKey('Cerrada'), $cartera->status);
    }

    /** @test */
    public function revertir_pago_restaura_el_saldo(): void
    {
        $cartera = $this->crearCartera([
            'valor'  => 100000,
            'saldo'  => 0,
            'abono'  => 100000,
            'status' => Cartera::getStatusKey('Cerrada'),
        ]);

        $cartera->revertirPago(100000);
        $cartera->refresh();

        $this->assertEquals(100000, $cartera->saldo);
        $this->assertEquals(0, $cartera->abono);
        $this->assertEquals(Cartera::getStatusKey('Activa'), $cartera->status);
    }
}
