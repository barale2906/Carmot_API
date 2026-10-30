<?php

namespace Tests\Feature\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTercero;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del tercero en el libro diario: búsqueda y edición del catálogo, y
 * selección o creación del tercero desde el mismo registro del movimiento.
 */
class LdTerceroTest extends TestCase
{
    use PreparaLibroDiario, RefreshDatabase;

    private User $cajero;

    private LdTurnoCaja $turno;

    private LdTipoMovimiento $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararLibroDiario();
        $this->cajero = $this->usuarioCon(['fin_ldRegistroCrear', 'fin_ldRegistroEditar']);
        $this->turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $this->tipo = LdTipoMovimiento::factory()->create();
    }

    /**
     * Movimiento sin tercero; se completa con tercero_id o con los datos del tercero.
     *
     * @param  array<string, mixed>  $tercero
     * @return array<string, mixed>
     */
    private function payload(array $tercero): array
    {
        return array_merge([
            'tipo_movimiento_id' => $this->tipo->id,
            'documento_tipo' => 'factura',
            'subtotal' => 50000,
            'valor_total' => 50000,
        ], $tercero);
    }

    /** @test */
    public function busca_terceros_por_inicio_de_identificacion_o_parte_del_nombre(): void
    {
        LdTercero::factory()->create(['identificacion' => '900123456', 'nombre' => 'Inmobiliaria Central']);
        LdTercero::factory()->create(['identificacion' => '800555000', 'nombre' => 'Papelería La Central']);
        LdTercero::factory()->create(['identificacion' => '123900', 'nombre' => 'Aseo Total']);

        $this->actingAs($this->cajero)
            ->getJson(route('libro-diario.terceros.index', ['search' => '900']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Inmobiliaria Central');

        $this->actingAs($this->cajero)
            ->getJson(route('libro-diario.terceros.index', ['search' => 'central']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.tipo_identificacion_text', 'NIT')
            ->assertJsonStructure(['data' => [['id', 'identificacion', 'nombre', 'telefono', 'email', 'movimientos_count']], 'meta' => ['total']]);
    }

    /** @test */
    public function filtra_terceros_por_estado_y_tipo_de_identificacion(): void
    {
        LdTercero::factory()->create(['tipo_identificacion' => 'NIT']);
        LdTercero::factory()->inactivo()->create(['tipo_identificacion' => 'NIT']);
        LdTercero::factory()->create(['tipo_identificacion' => 'CC', 'identificacion' => '1010']);
        $catalogo = $this->usuarioCon(['fin_ldCatalogos']);

        $this->actingAs($catalogo)->getJson(route('libro-diario.terceros.index', ['status' => 0]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status_text', 'Inactivo');
        $this->actingAs($catalogo)->getJson(route('libro-diario.terceros.index', ['status' => 1, 'tipo_identificacion' => 'CC']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.identificacion', '1010');
    }

    /** @test */
    public function registra_el_movimiento_con_un_tercero_seleccionado(): void
    {
        $tercero = LdTercero::factory()->create(['nombre' => 'Inmobiliaria Central']);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload(['tercero_id' => $tercero->id]))
            ->assertCreated()
            ->assertJsonPath('data.tercero_id', $tercero->id)
            ->assertJsonPath('data.tercero.nombre', 'Inmobiliaria Central')
            ->assertJsonPath('data.tercero_nombre', 'Inmobiliaria Central')
            ->assertJsonPath('data.tercero_identificacion', $tercero->identificacion);
    }

    /** @test */
    public function crea_el_tercero_en_el_mismo_registro_si_no_existe(): void
    {
        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload([
                'tercero_tipo_identificacion' => 'CC',
                'tercero_identificacion' => ' 1052345678 ',
                'tercero_nombre' => 'Pedro Gómez',
                'tercero_telefono' => '3001234567',
                'tercero_email' => 'pedro@example.com',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.tercero.identificacion', '1052345678')
            ->assertJsonPath('data.tercero.telefono', '3001234567');

        $this->assertDatabaseHas('ld_terceros', [
            'tipo_identificacion' => 'CC', 'identificacion' => '1052345678', 'nombre' => 'Pedro Gómez', 'email' => 'pedro@example.com', 'status' => 1,
        ]);
    }

    /** @test */
    public function con_una_identificacion_existente_usa_el_tercero_registrado_sin_duplicarlo(): void
    {
        $tercero = LdTercero::factory()->create(['tipo_identificacion' => 'NIT', 'identificacion' => '900123456', 'nombre' => 'Inmobiliaria Central']);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload([
                'tercero_tipo_identificacion' => 'NIT',
                'tercero_identificacion' => '900123456',
                'tercero_nombre' => 'Inmob. Central (otro nombre)',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.tercero_id', $tercero->id)
            ->assertJsonPath('data.tercero_nombre', 'Inmobiliaria Central');

        $this->assertDatabaseCount('ld_terceros', 1);
    }

    /** @test */
    public function valida_tercero_faltante_inexistente_o_inactivo(): void
    {
        $inactivo = LdTercero::factory()->inactivo()->create(['identificacion' => '800111222']);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload([]))
            ->assertJsonValidationErrors(['tercero_tipo_identificacion', 'tercero_identificacion', 'tercero_nombre']);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload(['tercero_id' => $inactivo->id]))
            ->assertJsonValidationErrors(['tercero_id' => 'El tercero no existe o está inactivo.']);

        $this->actingAs($this->cajero)
            ->postJson(route('libro-diario.movimientos.store'), $this->payload([
                'tercero_tipo_identificacion' => 'NIT', 'tercero_identificacion' => '800111222', 'tercero_nombre' => 'X',
                'tercero_email' => 'no-es-correo',
            ]))
            ->assertJsonValidationErrors(['tercero_identificacion', 'tercero_email']);

        $this->assertDatabaseCount('ld_movimientos', 0);
    }

    /** @test */
    public function al_editar_el_movimiento_se_puede_cambiar_el_tercero_y_filtrar_por_el(): void
    {
        $movimiento = LdMovimiento::factory()->create(['turno_caja_id' => $this->turno->id, 'tipo_movimiento_id' => $this->tipo->id]);
        $nuevo = LdTercero::factory()->create(['nombre' => 'Aseo Total']);

        $this->actingAs($this->cajero)
            ->putJson(route('libro-diario.movimientos.update', $movimiento), $this->payload(['tercero_id' => $nuevo->id]))
            ->assertOk()
            ->assertJsonPath('data.tercero_nombre', 'Aseo Total');

        $consulta = $this->usuarioCon(['fin_libroDiario']);
        $this->actingAs($consulta)
            ->getJson(route('libro-diario.movimientos.index', ['tercero_id' => $nuevo->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $movimiento->id);
    }

    /** @test */
    public function editar_el_tercero_conserva_la_copia_de_los_movimientos_ya_registrados(): void
    {
        $tercero = LdTercero::factory()->create(['nombre' => 'Nombre anterior']);
        $movimiento = LdMovimiento::factory()->create(['turno_caja_id' => $this->turno->id, 'tercero_id' => $tercero->id]);
        $editor = $this->usuarioCon(['fin_ldCatalogoEditar']);

        $this->actingAs($editor)
            ->putJson(route('libro-diario.terceros.update', $tercero), ['nombre' => 'Nombre corregido', 'telefono' => '6012345', 'status' => 0])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre corregido')
            ->assertJsonPath('data.status', 0);

        $this->assertSame('Nombre anterior', $movimiento->fresh()->tercero_nombre);
    }

    /** @test */
    public function no_permite_repetir_tipo_y_numero_de_identificacion_al_editar(): void
    {
        LdTercero::factory()->create(['tipo_identificacion' => 'NIT', 'identificacion' => '900123456']);
        $otro = LdTercero::factory()->create(['tipo_identificacion' => 'NIT', 'identificacion' => '800000001']);
        $editor = $this->usuarioCon(['fin_ldCatalogoEditar']);

        $this->actingAs($editor)
            ->putJson(route('libro-diario.terceros.update', $otro), ['identificacion' => '900123456'])
            ->assertJsonValidationErrors(['identificacion' => 'Ya existe un tercero con este tipo y número de identificación.']);

        $this->actingAs($editor)
            ->putJson(route('libro-diario.terceros.update', $otro), ['tipo_identificacion' => 'CC', 'identificacion' => '900123456'])
            ->assertOk();
    }

    /** @test */
    public function requiere_permisos_para_consultar_y_editar_terceros(): void
    {
        $tercero = LdTercero::factory()->create();
        $sinPermisos = User::factory()->create();

        $this->actingAs($sinPermisos)->getJson(route('libro-diario.terceros.index'))->assertForbidden();
        $this->actingAs($this->cajero)->getJson(route('libro-diario.terceros.show', $tercero))->assertOk();
        $this->actingAs($this->cajero)->putJson(route('libro-diario.terceros.update', $tercero), ['nombre' => 'X'])->assertForbidden();
    }
}
