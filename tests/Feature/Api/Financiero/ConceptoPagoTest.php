<?php

namespace Tests\Feature\Api\Financiero;

use App\Models\Financiero\ConceptoPago\ConceptoPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del módulo ConceptoPago: CRUD y permisos.
 */
class ConceptoPagoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'fin_conceptos_pago',       'descripcion' => 'ver conceptos de pago']);
        Permission::create(['name' => 'fin_conceptoPagoCrear',    'descripcion' => 'crear concepto de pago']);
        Permission::create(['name' => 'fin_conceptoPagoEditar',   'descripcion' => 'editar concepto de pago']);
        Permission::create(['name' => 'fin_conceptoPagoInactivar','descripcion' => 'inactivar concepto de pago']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'fin_conceptos_pago',
            'fin_conceptoPagoCrear',
            'fin_conceptoPagoEditar',
            'fin_conceptoPagoInactivar',
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function lista_conceptos_de_pago_paginados(): void
    {
        ConceptoPago::factory()->count(3)->tipoCartera()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('conceptos-pago.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    /** @test */
    public function rechaza_index_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('conceptos-pago.index'))
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function muestra_un_concepto_de_pago(): void
    {
        $concepto = ConceptoPago::factory()->tipoCartera()->create(['nombre' => 'Matrícula']);

        $this->actingAs($this->usuario)
            ->getJson(route('conceptos-pago.show', $concepto))
            ->assertOk()
            ->assertJsonPath('data.id', $concepto->id)
            ->assertJsonPath('data.nombre', 'Matrícula');
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function crea_un_concepto_de_pago(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('conceptos-pago.store'), [
                'nombre' => 'Recargo por mora',
                'tipo'   => 0,
                'valor'  => 15000.00,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'Recargo por mora');

        $this->assertDatabaseHas('conceptos_pago', ['nombre' => 'Recargo por mora', 'tipo' => 0]);
    }

    /** @test */
    public function validacion_falla_sin_campos_requeridos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('conceptos-pago.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'tipo', 'valor']);
    }

    /** @test */
    public function rechaza_crear_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_conceptos_pago');

        $this->actingAs($sinPermiso)
            ->postJson(route('conceptos-pago.store'), ['nombre' => 'Test', 'tipo' => 0, 'valor' => 1000])
            ->assertForbidden();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function actualiza_un_concepto_de_pago(): void
    {
        $concepto = ConceptoPago::factory()->tipoCartera()->create();

        $this->actingAs($this->usuario)
            ->putJson(route('conceptos-pago.update', $concepto), [
                'nombre' => 'Nombre actualizado',
                'tipo'   => 0,
                'valor'  => 25000.00,
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre actualizado');

        $this->assertDatabaseHas('conceptos_pago', ['id' => $concepto->id, 'nombre' => 'Nombre actualizado']);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    /** @test */
    public function elimina_un_concepto_de_pago_con_soft_delete(): void
    {
        $concepto = ConceptoPago::factory()->tipoCartera()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('conceptos-pago.destroy', $concepto))
            ->assertOk();

        $this->assertSoftDeleted('conceptos_pago', ['id' => $concepto->id]);
    }

    /** @test */
    public function rechaza_eliminar_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_conceptos_pago');
        $concepto = ConceptoPago::factory()->tipoCartera()->create();

        $this->actingAs($sinPermiso)
            ->deleteJson(route('conceptos-pago.destroy', $concepto))
            ->assertForbidden();
    }

    // ─── tipos ────────────────────────────────────────────────────────────────

    /** @test */
    public function retorna_tipos_disponibles(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('conceptos-pago.tipos'))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
