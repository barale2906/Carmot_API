<?php

namespace Tests\Feature\Api\Financiero;

use App\Models\Financiero\Descuento\Descuento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del módulo Descuento: CRUD, aprobación y permisos.
 */
class DescuentoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'fin_descuentos',          'descripcion' => 'ver descuentos']);
        Permission::create(['name' => 'fin_descuentoCrear',      'descripcion' => 'crear descuento']);
        Permission::create(['name' => 'fin_descuentoEditar',     'descripcion' => 'editar descuento']);
        Permission::create(['name' => 'fin_descuentoInactivar',  'descripcion' => 'inactivar descuento']);
        Permission::create(['name' => 'fin_descuentoAprobar',    'descripcion' => 'aprobar descuento']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'fin_descuentos',
            'fin_descuentoCrear',
            'fin_descuentoEditar',
            'fin_descuentoInactivar',
            'fin_descuentoAprobar',
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function datosBase(array $overrides = []): array
    {
        return array_merge([
            'tipo_movimiento'   => Descuento::MOVIMIENTO_DESCUENTO,
            'nombre'            => 'Descuento pronto pago',
            'tipo'              => Descuento::TIPO_PORCENTUAL,
            'valor'             => 10,
            'aplicacion'        => Descuento::APLICACION_CUOTA,
            'tipo_activacion'   => Descuento::ACTIVACION_PAGO_ANTICIPADO,
            'dias_anticipacion' => 5,
            'fecha_inicio'      => now()->toDateString(),
            'fecha_fin'         => now()->addMonths(6)->toDateString(),
        ], $overrides);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function lista_descuentos_paginados(): void
    {
        Descuento::factory()->count(3)->enProceso()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('descuentos.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    /** @test */
    public function rechaza_index_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('descuentos.index'))
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function muestra_un_descuento(): void
    {
        $descuento = Descuento::factory()->enProceso()->pagoAnticipado()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('descuentos.show', $descuento))
            ->assertOk()
            ->assertJsonPath('data.id', $descuento->id);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function crea_un_descuento_porcentual_de_pago_anticipado(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosBase());

        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'Descuento pronto pago')
            ->assertJsonPath('data.tipo', Descuento::TIPO_PORCENTUAL);

        $this->assertDatabaseHas('descuentos', ['nombre' => 'Descuento pronto pago']);
    }

    /** @test */
    public function crea_un_descuento_de_valor_fijo(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosBase([
                'tipo'  => Descuento::TIPO_VALOR_FIJO,
                'valor' => 50000,
            ]));

        $response->assertCreated()
            ->assertJsonPath('data.tipo', Descuento::TIPO_VALOR_FIJO);
    }

    /** @test */
    public function validacion_falla_sin_campos_requeridos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_movimiento', 'nombre', 'tipo', 'valor', 'aplicacion', 'tipo_activacion', 'fecha_inicio', 'fecha_fin']);
    }

    /** @test */
    public function crea_un_descuento_y_retorna_tipo_movimiento(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('descuentos.store'), $this->datosBase());

        $response->assertCreated()
            ->assertJsonPath('data.tipo_movimiento', Descuento::MOVIMIENTO_DESCUENTO)
            ->assertJsonPath('data.es_sobrecargo', false);
    }

    /** @test */
    public function rechaza_crear_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_descuentos');

        $this->actingAs($sinPermiso)
            ->postJson(route('descuentos.store'), $this->datosBase())
            ->assertForbidden();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function actualiza_un_descuento(): void
    {
        $descuento = Descuento::factory()->enProceso()->pagoAnticipado()->create();

        $this->actingAs($this->usuario)
            ->putJson(route('descuentos.update', $descuento), $this->datosBase([
                'nombre' => 'Descuento modificado',
                'valor'  => 15,
            ]))
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Descuento modificado');
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    /** @test */
    public function elimina_descuento_con_soft_delete(): void
    {
        $descuento = Descuento::factory()->enProceso()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('descuentos.destroy', $descuento))
            ->assertOk();

        $this->assertSoftDeleted('descuentos', ['id' => $descuento->id]);
    }

    // ─── aprobar ──────────────────────────────────────────────────────────────

    /** @test */
    public function aprueba_un_descuento_en_proceso(): void
    {
        $descuento = Descuento::factory()->enProceso()->create();

        $this->actingAs($this->usuario)
            ->postJson(route('descuentos.aprobar', $descuento))
            ->assertOk()
            ->assertJsonPath('data.status', Descuento::STATUS_APROBADO);

        $this->assertDatabaseHas('descuentos', ['id' => $descuento->id, 'status' => Descuento::STATUS_APROBADO]);
    }

    /** @test */
    public function rechaza_aprobar_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_descuentos');
        $descuento = Descuento::factory()->enProceso()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('descuentos.aprobar', $descuento))
            ->assertForbidden();
    }

    // ─── activar ──────────────────────────────────────────────────────────────

    /** @test */
    public function activa_un_descuento_aprobado(): void
    {
        $descuento = Descuento::factory()->aprobado()->create();

        $this->actingAs($this->usuario)
            ->postJson(route('descuentos.activar', $descuento))
            ->assertOk()
            ->assertJsonPath('data.status', Descuento::STATUS_ACTIVO);

        $this->assertDatabaseHas('descuentos', ['id' => $descuento->id, 'status' => Descuento::STATUS_ACTIVO]);
    }

    /** @test */
    public function rechaza_activar_si_no_esta_aprobado(): void
    {
        $descuento = Descuento::factory()->enProceso()->create();

        $this->actingAs($this->usuario)
            ->postJson(route('descuentos.activar', $descuento))
            ->assertStatus(422);
    }

    /** @test */
    public function rechaza_activar_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_descuentos');
        $descuento = Descuento::factory()->aprobado()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('descuentos.activar', $descuento))
            ->assertForbidden();
    }
}
