<?php

namespace Tests\Feature\Api\Financiero\LibroDiario;

use App\Models\Configuracion\Banco;
use App\Models\Financiero\LibroDiario\LdImpuesto;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTercero;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\User;
use App\Services\Financiero\LibroDiario\LdMovimientoImportacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Pruebas de la carga masiva de movimientos del libro diario: plantilla descargable,
 * registro en el turno abierto, cálculo de impuestos y rechazo total ante filas con error.
 */
class LdMovimientoImportacionTest extends TestCase
{
    use PreparaLibroDiario, RefreshDatabase;

    private User $cajero;

    private LdTurnoCaja $turno;

    private LdTipoMovimiento $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararLibroDiario();
        $this->cajero = $this->usuarioCon(['fin_libroDiario', 'fin_ldRegistroCrear']);
        $this->turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $this->tipo = LdTipoMovimiento::factory()->create(['nombre' => 'Arriendo']);
    }

    /**
     * Fila completa con los valores mínimos válidos, indexada por columna.
     *
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function fila(array $override = []): array
    {
        return array_merge([
            'tipo_movimiento' => 'Arriendo',
            'tercero_tipo_identificacion' => 'NIT',
            'tercero_identificacion' => '900123456',
            'tercero_nombre' => 'Inmobiliaria Central',
            'documento_tipo' => 'factura',
            'documento_numero' => 'FE-1001',
            'subtotal' => 100000,
        ], $override);
    }

    /**
     * Archivo Excel con los encabezados de la plantilla y las filas indicadas.
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function archivo(array $filas): UploadedFile
    {
        $columnas = LdMovimientoImportacionService::columnas();
        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray($columnas, null, 'A1');
        foreach ($filas as $i => $fila) {
            $hoja->fromArray(array_map(fn ($columna) => $fila[$columna] ?? null, $columnas), null, 'A'.($i + 2));
        }

        $ruta = tempnam(sys_get_temp_dir(), 'ldimp').'.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        return new UploadedFile($ruta, 'movimientos.xlsx', null, null, true);
    }

    private function importar(UploadedFile $archivo, ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->cajero)
            ->post(route('libro-diario.movimientos.importar'), ['archivo' => $archivo], ['Accept' => 'application/json']);
    }

    /** Descarga la plantilla y la abre como libro de Excel. */
    private function descargarPlantilla(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $respuesta = $this->actingAs($this->cajero)
            ->get(route('libro-diario.movimientos.plantilla'))
            ->assertOk()
            ->assertDownload('plantilla-movimientos-libro-diario.xlsx');

        $ruta = tempnam(sys_get_temp_dir(), 'ldpla').'.xlsx';
        file_put_contents($ruta, $respuesta->streamedContent());

        return IOFactory::load($ruta);
    }

    /** @test */
    public function descarga_la_plantilla_con_catalogos_e_instrucciones(): void
    {
        LdImpuesto::factory()->create(['nombre' => 'IVA 19%']);

        $libro = $this->descargarPlantilla();

        $this->assertSame(['Movimientos', 'Instrucciones', 'Ejemplo', 'Catalogos'], $libro->getSheetNames());
        $this->assertSame(LdMovimientoImportacionService::columnas(), array_values(array_filter($libro->getSheet(0)->toArray()[0])));
        $this->assertStringContainsString('Obligatoria: Sí', $libro->getSheet(0)->getComment('B1')->getText()->getPlainText());
        $catalogos = collect($libro->getSheetByName('Catalogos')->toArray())->flatten()->filter()->values();
        $this->assertContains('Arriendo', $catalogos);
        $this->assertContains('IVA 19%', $catalogos);
        $this->assertSame('TODAY()', $libro->getSheet(0)->getCell('A2')->getDataValidation()->getFormula1());
        $this->assertNotNull($libro->getSheet(0)->getCell('B2')->getDataValidation()->getFormula1());
    }

    /** @test */
    public function las_filas_de_la_hoja_ejemplo_usan_el_catalogo_y_se_cargan_sin_errores(): void
    {
        LdImpuesto::factory()->create(['nombre' => 'IVA 19%', 'porcentaje' => 19]);
        LdImpuesto::factory()->retencion(4)->create(['nombre' => 'Retefuente 4%']);
        LdTipoMovimiento::factory()->create(['nombre' => 'Arrendamiento de aulas', 'clase' => LdTipoMovimiento::CLASE_INGRESO]);
        Banco::factory()->create(['nombre' => 'Banco Uno', 'status' => 1]);

        $ejemplo = $this->descargarPlantilla()->getSheetByName('Ejemplo');
        $columnas = LdMovimientoImportacionService::columnas();
        $filas = collect($ejemplo->rangeToArray('A4:'.$ejemplo->getHighestColumn().'6'))
            ->map(fn (array $valores) => array_combine($columnas, array_slice($valores, 0, count($columnas))))
            ->all();

        $this->assertSame(['Arriendo', 'Arriendo', 'Arrendamiento de aulas'], array_column($filas, 'tipo_movimiento'));
        $this->assertSame('IVA 19%', $filas[0]['impuesto_1']);
        $this->assertSame('Banco Uno', $filas[2]['banco']);
        $this->assertStringNotContainsString('[', implode('', array_filter(array_column($filas, 'impuesto_2'))));

        $tesorero = $this->usuarioCon(['fin_ldRegistroCrear', 'fin_ldMedioPagoOtros']);
        LdTurnoCaja::factory()->create(['cajero_id' => $tesorero->id, 'sede_id' => $this->sede->id]);

        $this->importar($this->archivo($filas), $tesorero)
            ->assertCreated()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.valor_total', 115000)
            ->assertJsonPath('data.2.clase', LdTipoMovimiento::CLASE_INGRESO);
    }

    /** @test */
    public function el_ejemplo_usa_nombres_ilustrativos_si_el_catalogo_esta_vacio(): void
    {
        LdTipoMovimiento::query()->delete();

        $ejemplo = $this->descargarPlantilla()->getSheetByName('Ejemplo');

        $columna = fn (string $nombre) => Coordinate::stringFromColumnIndex(array_search($nombre, LdMovimientoImportacionService::columnas()) + 1);
        $this->assertSame('[Arriendo]', $ejemplo->getCell($columna('tipo_movimiento').'4')->getValue());
        $this->assertSame('[IVA 19%]', $ejemplo->getCell($columna('impuesto_1').'4')->getValue());
    }

    /** @test */
    public function registra_todos_los_movimientos_en_el_turno_abierto_calculando_impuestos(): void
    {
        LdImpuesto::factory()->create(['nombre' => 'IVA 19%', 'porcentaje' => 19]);
        LdImpuesto::factory()->retencion(4)->create(['nombre' => 'Retefuente 4%']);

        $this->importar($this->archivo([
            $this->fila(['impuesto_1' => 'iva 19%', 'impuesto_2' => 'Retefuente 4%', 'valor_impuesto_2' => 4000, 'valor_total' => 115000]),
            $this->fila(['tercero_tipo_identificacion' => 'cc', 'tercero_identificacion' => '1010', 'tercero_nombre' => 'Ana', 'documento_tipo' => 'recibo', 'subtotal' => 25000]),
        ]))
            ->assertCreated()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.valor_total', 115000)
            ->assertJsonPath('data.0.total_impuestos', 19000)
            ->assertJsonPath('data.0.total_retenciones', 4000)
            ->assertJsonPath('data.1.medio_pago', 'efectivo');

        $this->assertDatabaseCount('ld_movimientos', 2);
        $this->assertDatabaseHas('ld_movimientos', [
            'turno_caja_id' => $this->turno->id, 'sede_id' => $this->sede->id,
            'tercero_tipo_identificacion' => 'CC', 'clase' => LdTipoMovimiento::CLASE_EGRESO, 'valor_total' => 25000,
        ]);
        $this->assertDatabaseCount('ld_movimiento_impuesto', 2);
    }

    /** @test */
    public function carga_movimientos_de_varios_dias_con_su_fecha_en_el_turno_abierto(): void
    {
        $antier = today()->subDays(2);
        $ayer = today()->subDay();

        $this->importar($this->archivo([
            $this->fila(['fecha' => $antier->toDateString()]),
            $this->fila(['fecha' => $ayer->format('d/m/Y')]),
            $this->fila(['fecha' => ExcelDate::PHPToExcel($ayer)]),
            $this->fila(),
        ]))->assertCreated()->assertJsonPath('meta.total', 4);

        $fechas = LdMovimiento::orderBy('id')->pluck('fecha')->map->toDateString()->all();
        $this->assertSame([$antier->toDateString(), $ayer->toDateString(), $ayer->toDateString(), today()->toDateString()], $fechas);
        $this->assertSame(4, LdMovimiento::where('turno_caja_id', $this->turno->id)->count());

        $this->actingAs($this->cajero)
            ->getJson(route('libro-diario.index', ['fecha_desde' => $antier->toDateString(), 'fecha_hasta' => $antier->toDateString(), 'origen' => 'movimiento']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function rechaza_fecha_futura_o_con_formato_no_reconocido(): void
    {
        $this->importar($this->archivo([
            $this->fila(['fecha' => today()->addDay()->toDateString()]),
            $this->fila(['fecha' => '31/02/2026']),
            $this->fila(['fecha' => 'ayer']),
        ]))
            ->assertUnprocessable()
            ->assertJsonCount(3, 'errores')
            ->assertJsonPath('errores.0.mensajes.0', 'La fecha no puede ser posterior a hoy.')
            ->assertJsonPath('errores.1.mensajes.0', 'La fecha "31/02/2026" no es válida; use el formato AAAA-MM-DD o DD/MM/AAAA.')
            ->assertJsonPath('errores.2.fila', 4);

        $this->assertDatabaseCount('ld_movimientos', 0);
    }

    /** @test */
    public function crea_los_terceros_nuevos_una_sola_vez_y_reutiliza_los_existentes(): void
    {
        $existente = LdTercero::factory()->create(['tipo_identificacion' => 'NIT', 'identificacion' => '900123456', 'nombre' => 'Inmobiliaria Central']);

        $this->importar($this->archivo([
            $this->fila(['tercero_nombre' => 'Otro nombre en el archivo']),
            $this->fila(['tercero_tipo_identificacion' => 'CC', 'tercero_identificacion' => '1010', 'tercero_nombre' => 'Ana Ruiz', 'tercero_telefono' => '3001112233']),
            $this->fila(['tercero_tipo_identificacion' => 'CC', 'tercero_identificacion' => '1010', 'tercero_nombre' => 'Ana Ruiz']),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.0.tercero_id', $existente->id)
            ->assertJsonPath('data.0.tercero_nombre', 'Inmobiliaria Central');

        $this->assertDatabaseCount('ld_terceros', 2);
        $this->assertDatabaseHas('ld_terceros', ['identificacion' => '1010', 'telefono' => '3001112233']);
        $this->assertSame(2, LdTercero::where('identificacion', '1010')->first()->movimientos()->count());
    }

    /** @test */
    public function rechaza_filas_con_tercero_inactivo(): void
    {
        LdTercero::factory()->inactivo()->create(['tipo_identificacion' => 'NIT', 'identificacion' => '900123456', 'nombre' => 'Inmobiliaria Central']);

        $this->importar($this->archivo([$this->fila()]))
            ->assertUnprocessable()
            ->assertJsonPath('errores.0.mensajes.0', 'El tercero Inmobiliaria Central (NIT 900123456) está inactivo.');
    }

    /** @test */
    public function no_registra_nada_si_alguna_fila_tiene_errores(): void
    {
        $this->importar($this->archivo([
            $this->fila(),
            $this->fila(['tipo_movimiento' => 'No existe', 'tercero_nombre' => null]),
            $this->fila(['subtotal' => 1000, 'valor_total' => 5000]),
        ]))
            ->assertUnprocessable()
            ->assertJsonCount(2, 'errores')
            ->assertJsonPath('errores.0.fila', 3)
            ->assertJsonPath('errores.1.fila', 4)
            ->assertJsonFragment(['El tipo de movimiento "No existe" no existe o está inactivo.'])
            ->assertJsonFragment(['El nombre del tercero es obligatorio.']);

        $this->assertDatabaseCount('ld_movimientos', 0);
    }

    /** @test */
    public function sin_permiso_de_otros_medios_solo_carga_efectivo_y_exige_banco(): void
    {
        $this->importar($this->archivo([$this->fila(['medio_pago' => 'transferencia'])]))
            ->assertUnprocessable()
            ->assertJsonFragment(['Solo puede registrar movimientos en efectivo.'])
            ->assertJsonFragment(['El banco es obligatorio para transferencias y consignaciones.']);

        $tesorero = $this->usuarioCon(['fin_ldRegistroCrear', 'fin_ldMedioPagoOtros']);
        LdTurnoCaja::factory()->create(['cajero_id' => $tesorero->id, 'sede_id' => $this->sede->id]);
        $banco = Banco::factory()->create(['nombre' => 'Banco Uno', 'status' => 1]);

        $this->importar($this->archivo([$this->fila(['medio_pago' => 'transferencia', 'banco' => 'banco uno'])]), $tesorero)
            ->assertCreated()
            ->assertJsonPath('data.0.banco_id', $banco->id);
    }

    /** @test */
    public function rechaza_archivo_sin_columnas_vacio_o_sin_turno_abierto(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray(['nombre', 'valor'], null, 'A1');
        $ruta = tempnam(sys_get_temp_dir(), 'ldimp').'.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        $this->importar(new UploadedFile($ruta, 'otro.xlsx', null, null, true))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo');

        $this->importar($this->archivo([]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo');

        $this->importar(UploadedFile::fake()->create('movimientos.pdf', 10, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo');

        $this->turno->delete();
        $this->importar($this->archivo([$this->fila()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('turno');
    }

    /** @test */
    public function acepta_csv(): void
    {
        $columnas = LdMovimientoImportacionService::columnas();
        $contenido = "\xEF\xBB\xBF".implode(',', $columnas)."\n"
            .implode(',', array_map(fn ($c) => $this->fila()[$c] ?? '', $columnas))."\n";

        $this->importar(UploadedFile::fake()->createWithContent('movimientos.csv', $contenido))
            ->assertCreated()
            ->assertJsonPath('meta.total', 1);
    }

    /** @test */
    public function requiere_permiso_de_registro(): void
    {
        $consulta = $this->usuarioCon(['fin_libroDiario']);

        $this->actingAs($consulta)->getJson(route('libro-diario.movimientos.plantilla'))->assertForbidden();
        $this->importar($this->archivo([$this->fila()]), $consulta)->assertForbidden();
    }
}
