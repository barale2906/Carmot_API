<?php

namespace Tests\Feature\Api\Financiero\LibroDiario;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\LibroDiario\LdConsignacion;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\User;
use App\Services\Financiero\LibroDiario\LibroDiarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del libro diario unificado y su resumen por sede.
 */
class LibroDiarioTest extends TestCase
{
    use PreparaLibroDiario, RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararLibroDiario();
        $this->usuario = $this->usuarioCon(['fin_libroDiario']);

        $turno = LdTurnoCaja::factory()->create(['sede_id' => $this->sede->id]);
        $this->reciboPagado(100000, 'efectivo', ['turno_caja_id' => $turno->id]);
        $this->reciboPagado(40000, 'efectivo', ['turno_caja_id' => $turno->id, 'origen' => ReciboPago::ORIGEN_INVENTARIOS]);
        $this->reciboPagado(70000, 'efectivo', ['turno_caja_id' => $turno->id, 'status' => ReciboPago::STATUS_ANULADO]);
        LdMovimiento::factory()->create(['turno_caja_id' => $turno->id, 'valor_total' => 25000, 'tercero_nombre' => 'Papelería Lápiz']);
        LdMovimiento::factory()->create(['turno_caja_id' => $turno->id, 'valor_total' => 99000, 'status' => LdMovimiento::STATUS_ANULADO]);
        LdMovimiento::factory()->create([
            'turno_caja_id' => $turno->id,
            'tipo_movimiento_id' => LdTipoMovimiento::factory()->ingreso(),
            'valor_total' => 15000,
        ]);
        LdConsignacion::factory()->create(['turno_caja_id' => $turno->id, 'valor' => 50000]);

        // Registros de otra sede no visibles para el usuario
        $ajeno = LdTurnoCaja::factory()->create(['sede_id' => Sede::factory()]);
        LdMovimiento::factory()->create(['turno_caja_id' => $ajeno->id]);
    }

    /** @test */
    public function lista_registros_vigentes_de_sus_sedes(): void
    {
        $this->actingAs($this->usuario)->getJson(route('libro-diario.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 5);
    }

    /** @test */
    public function filtra_por_origen_naturaleza_y_busqueda(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('libro-diario.index', ['origen' => LibroDiarioService::ORIGEN_RECIBO_ACADEMICO]))
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.valor', 100000);

        $this->actingAs($this->usuario)
            ->getJson(route('libro-diario.index', ['naturaleza' => LibroDiarioService::NATURALEZA_EGRESO]))
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->usuario)
            ->getJson(route('libro-diario.index', ['search' => 'Lápiz']))
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.origen', LibroDiarioService::ORIGEN_MOVIMIENTO);
    }

    /** @test */
    public function cada_registro_trae_la_sede_y_el_usuario_que_lo_genero(): void
    {
        $registros = collect($this->actingAs($this->usuario)->getJson(route('libro-diario.index'))->json('data'));

        $this->assertTrue($registros->every(fn ($r) => $r['sede'] === $this->sede->nombre));
        $this->assertTrue($registros->every(fn ($r) => filled($r['usuario'])));

        $movimiento = LdMovimiento::where('tercero_nombre', 'Papelería Lápiz')->first();
        $fila = $registros->firstWhere('registro_id', $movimiento->id);
        $this->assertSame($movimiento->usuario->name, $fila['usuario']);

        $this->actingAs($this->usuario)
            ->getJson(route('libro-diario.index', ['search' => $movimiento->usuario->primer_nombre]))
            ->assertOk()
            ->assertJsonPath('data.0.usuario', $movimiento->usuario->name);
    }

    /** @test */
    public function filtra_por_rango_de_fechas(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('libro-diario.index', ['fecha_desde' => today()->addDay()->toDateString()]))
            ->assertJsonPath('meta.total', 0);
    }

    /** @test */
    public function resume_por_sede(): void
    {
        $this->actingAs($this->usuario)->getJson(route('libro-diario.resumen'))
            ->assertOk()
            ->assertJsonCount(1, 'data.sedes')
            ->assertJsonPath('data.sedes.0.sede_id', $this->sede->id)
            ->assertJsonPath('data.sedes.0.ingresos_academico', 100000)
            ->assertJsonPath('data.sedes.0.ingresos_inventario', 40000)
            ->assertJsonPath('data.sedes.0.otros_ingresos', 15000)
            ->assertJsonPath('data.sedes.0.egresos', 25000)
            ->assertJsonPath('data.sedes.0.consignaciones', 50000)
            ->assertJsonPath('data.sedes.0.total_ingresos', 155000)
            ->assertJsonPath('data.totales.neto', 130000);
    }

    /** @test */
    public function filters_devuelve_catalogos_y_sedes_del_usuario(): void
    {
        $this->actingAs($this->usuario)->getJson(route('libro-diario.filters'))
            ->assertOk()
            ->assertJsonCount(1, 'data.sedes')
            ->assertJsonStructure(['data' => ['origenes', 'naturalezas', 'medios_pago', 'tipos_identificacion', 'tipos_documento', 'tipos_soporte']]);
    }

    /** @test */
    public function el_menu_muestra_el_grupo_libro_diario_segun_permisos(): void
    {
        $cajero = $this->usuarioCon(['fin_ldTurnoCaja', 'fin_libroDiario']);

        $financiero = collect($this->actingAs($cajero)->getJson('/api/menu')->assertOk()->json('data'))
            ->firstWhere('id', 'financiero');
        $grupo = collect($financiero['children'])->firstWhere('id', 'financiero-grupo-libro-diario');

        $this->assertSame(
            ['financiero-caja', 'financiero-libro-diario', 'financiero-turnos-caja'],
            array_column($grupo['children'], 'id')
        );
    }

    /** @test */
    public function el_aprobador_ve_turnos_de_caja_en_el_menu(): void
    {
        $aprobador = $this->usuarioCon(['fin_ldCierreAprobar']);

        $financiero = collect($this->actingAs($aprobador)->getJson('/api/menu')->assertOk()->json('data'))
            ->firstWhere('id', 'financiero');
        $grupo = collect($financiero['children'])->firstWhere('id', 'financiero-grupo-libro-diario');

        $this->assertSame(['financiero-turnos-caja'], array_column($grupo['children'], 'id'));
    }

    /** @test */
    public function sin_permiso_no_consulta(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('libro-diario.index'))->assertForbidden();
    }
}
