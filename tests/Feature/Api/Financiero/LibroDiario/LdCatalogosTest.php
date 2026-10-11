<?php

namespace Tests\Feature\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdImpuesto;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de los catálogos del libro diario: tipos de movimiento e impuestos.
 */
class LdCatalogosTest extends TestCase
{
    use PreparaLibroDiario, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararLibroDiario();
        $this->admin = $this->usuarioCon(['fin_ldCatalogos', 'fin_ldCatalogoCrear', 'fin_ldCatalogoEditar', 'fin_ldCatalogoInactivar']);
    }

    /** @test */
    public function crud_de_tipos_de_movimiento(): void
    {
        $id = $this->actingAs($this->admin)
            ->postJson(route('libro-diario.tipos-movimiento.store'), ['nombre' => 'Arriendo', 'clase' => LdTipoMovimiento::CLASE_EGRESO])
            ->assertCreated()
            ->assertJsonPath('data.clase_text', 'Egreso')
            ->assertJsonPath('data.status', 1)
            ->json('data.id');

        $this->actingAs($this->admin)
            ->putJson(route('libro-diario.tipos-movimiento.update', $id), ['nombre' => 'Arriendo sede'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Arriendo sede');

        $this->actingAs($this->admin)->getJson(route('libro-diario.tipos-movimiento.index', ['clase' => 1]))
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin)->deleteJson(route('libro-diario.tipos-movimiento.destroy', $id))->assertOk();
        $this->actingAs($this->admin)->getJson(route('libro-diario.tipos-movimiento.trashed'))->assertJsonPath('meta.total', 1);
        $this->actingAs($this->admin)->postJson(route('libro-diario.tipos-movimiento.restore', $id))->assertOk();
        $this->assertNotSoftDeleted('ld_tipos_movimiento', ['id' => $id]);
    }

    /** @test */
    public function valida_nombre_unico_y_clase(): void
    {
        LdTipoMovimiento::factory()->create(['nombre' => 'Nómina']);

        $this->actingAs($this->admin)
            ->postJson(route('libro-diario.tipos-movimiento.store'), ['nombre' => 'Nómina', 'clase' => 9])
            ->assertJsonValidationErrors(['nombre', 'clase']);
    }

    /** @test */
    public function no_elimina_permanentemente_un_tipo_con_movimientos(): void
    {
        $tipo = LdTipoMovimiento::factory()->create();
        LdMovimiento::factory()->create(['tipo_movimiento_id' => $tipo->id]);
        $tipo->delete();

        $this->actingAs($this->admin)
            ->deleteJson(route('libro-diario.tipos-movimiento.force-delete', $tipo->id))
            ->assertUnprocessable();
    }

    /** @test */
    public function crud_de_impuestos_y_activos_para_selector(): void
    {
        $id = $this->actingAs($this->admin)
            ->postJson(route('libro-diario.impuestos.store'), ['nombre' => 'ReteICA', 'porcentaje' => 0.966, 'efecto' => LdImpuesto::EFECTO_RESTA])
            ->assertCreated()
            ->assertJsonPath('data.porcentaje', 0.966)
            ->json('data.id');

        LdImpuesto::factory()->create(['status' => 0]);

        $registrador = $this->usuarioCon(['fin_ldRegistroCrear']);
        $this->actingAs($registrador)->getJson(route('libro-diario.impuestos.activos'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->actingAs($this->admin)
            ->postJson(route('libro-diario.impuestos.store'), ['nombre' => 'X', 'porcentaje' => 120, 'efecto' => 3])
            ->assertJsonValidationErrors(['porcentaje', 'efecto']);

        $this->actingAs($this->admin)->deleteJson(route('libro-diario.impuestos.destroy', $id))->assertOk();
        $this->actingAs($this->admin)->deleteJson(route('libro-diario.impuestos.force-delete', $id))->assertOk();
        $this->assertDatabaseMissing('ld_impuestos', ['id' => $id]);
    }

    /** @test */
    public function sin_permiso_no_crea_catalogos(): void
    {
        $this->actingAs($this->usuarioCon(['fin_ldCatalogos']))
            ->postJson(route('libro-diario.impuestos.store'), ['nombre' => 'IVA', 'porcentaje' => 19, 'efecto' => 1])
            ->assertForbidden();
    }
}
