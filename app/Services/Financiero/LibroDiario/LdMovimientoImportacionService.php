<?php

namespace App\Services\Financiero\LibroDiario;

use App\Http\Requests\Api\Financiero\LibroDiario\StoreLdMovimientoRequest;
use App\Models\Configuracion\Banco;
use App\Models\Financiero\LibroDiario\LdImpuesto;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Servicio LdMovimientoImportacionService
 *
 * Carga masiva de movimientos manuales del libro diario desde Excel (o CSV) y
 * generación de la plantilla para diligenciarla.
 *
 * Los catálogos se diligencian por nombre (tipo de movimiento, impuestos, banco) o
 * por código (tipo de identificación, tipo de documento, medio de pago); la plantilla
 * los trae como listas desplegables. Cada fila pasa por las mismas reglas del registro
 * individual y la carga es todo o nada: si una fila falla no se registra ninguna.
 * El tercero se busca por tipo + número de identificación y se crea si no existe.
 *
 * Todos los movimientos quedan en el turno abierto de quien carga (el efectivo cuadra
 * en ese turno), pero cada uno con la fecha que trae su fila —vacía es hoy, nunca
 * futura—, para cargar de una vez lo de varios días: el libro diario los muestra en su fecha.
 */
class LdMovimientoImportacionService
{
    /** Máximo de filas por archivo. */
    public const MAX_FILAS = 500;

    /** Formatos de fecha aceptados como texto (las celdas de fecha de Excel se leen directo). */
    private const FORMATOS_FECHA = ['Y-m-d', 'd/m/Y', 'd-m-Y'];

    /** Impuestos o retenciones que admite cada fila. */
    public const MAX_IMPUESTOS = 3;

    private const COLOR_ENCABEZADO = '1F4E79';

    private const HOJA_DATOS = 'Movimientos';

    private const HOJA_CATALOGOS = 'Catalogos';

    private const COLUMNAS_OBLIGATORIAS = [
        'tipo_movimiento', 'tercero_tipo_identificacion', 'tercero_identificacion',
        'tercero_nombre', 'documento_tipo', 'subtotal',
    ];

    public function __construct(private readonly LdMovimientoService $movimientoService) {}

    /**
     * Columnas de la hoja de datos, en orden.
     *
     * @return array<int, string>
     */
    public static function columnas(): array
    {
        $impuestos = [];
        for ($i = 1; $i <= self::MAX_IMPUESTOS; $i++) {
            $impuestos[] = "impuesto_{$i}";
            $impuestos[] = "valor_impuesto_{$i}";
        }

        return array_merge(
            [
                'fecha', 'tipo_movimiento', 'tercero_tipo_identificacion', 'tercero_identificacion', 'tercero_nombre',
                'tercero_telefono', 'tercero_email', 'tercero_direccion', 'documento_tipo', 'documento_numero', 'subtotal',
            ],
            $impuestos,
            ['valor_total', 'medio_pago', 'banco', 'observaciones'],
        );
    }

    /**
     * Plantilla con la hoja de datos (listas desplegables), los catálogos vigentes e instrucciones.
     */
    public function plantilla(): Spreadsheet
    {
        $tipos = LdTipoMovimiento::where('status', 1)->orderBy('nombre')->get();
        $impuestos = LdImpuesto::where('status', 1)->orderBy('nombre')->get();
        $bancos = Banco::where('status', 1)->orderBy('nombre')->get();
        $catalogos = [
            'Tipos de movimiento' => $tipos->map(fn ($t) => [$t->nombre, LdTipoMovimiento::getClaseOptions()[$t->clase] ?? ''])->all(),
            'Impuestos' => $impuestos->map(fn ($i) => [$i->nombre, (float) $i->porcentaje.' % · '.(LdImpuesto::getEfectoOptions()[$i->efecto] ?? '')])->all(),
            'Tipos de identificación' => $this->pares(LdMovimiento::TIPOS_IDENTIFICACION),
            'Tipos de documento' => $this->pares(LdMovimiento::TIPOS_DOCUMENTO),
            'Medios de pago' => $this->pares(ReciboPagoMedioPago::MEDIOS),
            'Bancos' => $bancos->map(fn ($b) => [$b->nombre, ''])->all(),
        ];

        $spreadsheet = new Spreadsheet;
        $datos = $spreadsheet->getActiveSheet();
        $datos->setTitle(self::HOJA_DATOS);
        $this->encabezado($datos, self::columnas());

        $this->hojaInstrucciones($spreadsheet->createSheet());
        $this->hojaEjemplo($spreadsheet->createSheet(), $tipos, $impuestos, $bancos);
        $rangos = $this->hojaCatalogos($spreadsheet->createSheet(), $catalogos);
        $listas = [
            'tipo_movimiento' => $rangos['Tipos de movimiento'],
            'tercero_tipo_identificacion' => $rangos['Tipos de identificación'],
            'documento_tipo' => $rangos['Tipos de documento'],
            'medio_pago' => $rangos['Medios de pago'],
            'banco' => $rangos['Bancos'],
        ];
        for ($i = 1; $i <= self::MAX_IMPUESTOS; $i++) {
            $listas["impuesto_{$i}"] = $rangos['Impuestos'];
        }

        $descripciones = $this->descripciones();
        foreach (self::columnas() as $indice => $columna) {
            $letra = Coordinate::stringFromColumnIndex($indice + 1);
            $datos->getColumnDimension($letra)->setWidth(max(14, strlen($columna) + 4));
            [$obligatoria, $descripcion] = $descripciones[preg_replace('/^(valor_)?impuesto_\d+$/', 'impuesto_N / valor_impuesto_N', $columna)];
            $nota = $datos->getComment("{$letra}1");
            $nota->setWidth('260pt')->setHeight('90pt');
            $nota->getText()->createTextRun("Obligatoria: {$obligatoria}\n{$descripcion}");

            if (isset($listas[$columna])) {
                $this->listaDesplegable($datos, "{$letra}2:{$letra}".(self::MAX_FILAS + 1), $listas[$columna]);
            }
        }
        $datos->getStyle('A2:A'.(self::MAX_FILAS + 1))->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $this->validacionFecha($datos, 'A2:A'.(self::MAX_FILAS + 1));
        $datos->freezePane('A2');
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Valida el archivo y, si todas las filas son válidas, registra los movimientos en el turno.
     *
     * @return array{errores: array<int, array{fila: int, mensajes: array<int, string>}>, movimientos: Collection<int, LdMovimiento>}
     */
    public function importar(string $ruta, User $usuario, LdTurnoCaja $turno): array
    {
        $filas = $this->leerFilas($ruta);
        $catalogos = $this->catalogos();

        $validos = [];
        $errores = [];
        foreach ($filas as $numero => $fila) {
            [$datos, $mensajes] = $this->validarFila($fila, $catalogos, $usuario);
            if ($mensajes) {
                $errores[] = ['fila' => $numero, 'mensajes' => $mensajes];
            } else {
                $validos[] = $datos;
            }
        }

        if ($errores) {
            return ['errores' => $errores, 'movimientos' => collect()];
        }

        $movimientos = DB::transaction(fn () => collect($validos)
            ->map(fn (array $datos) => $this->movimientoService->crear($turno, $datos)));

        return ['errores' => [], 'movimientos' => $movimientos];
    }

    /**
     * Lee la primera hoja y devuelve las filas con datos indexadas por su número en el archivo.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function leerFilas(string $ruta): array
    {
        try {
            $hoja = IOFactory::load($ruta)->getSheet(0);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer el archivo. Use la plantilla en formato Excel (.xlsx) o CSV.']);
        }

        $contenido = $hoja->toArray(null, true, false, false);
        $encabezados = array_map(fn ($valor) => strtolower(trim((string) $valor)), array_shift($contenido) ?? []);

        $faltantes = array_diff(self::COLUMNAS_OBLIGATORIAS, $encabezados);
        if ($faltantes) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no tiene el formato de la plantilla. Columnas faltantes: '.implode(', ', $faltantes).'.']);
        }

        $filas = [];
        foreach ($contenido as $indice => $valores) {
            $fila = [];
            foreach ($encabezados as $posicion => $columna) {
                if ($columna !== '') {
                    $fila[$columna] = is_string($valores[$posicion] ?? null) ? trim($valores[$posicion]) : ($valores[$posicion] ?? null);
                }
            }

            if (collect($fila)->filter(fn ($valor) => $valor !== null && $valor !== '')->isNotEmpty()) {
                $filas[$indice + 2] = $fila;
            }
        }

        if (! $filas) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no tiene movimientos para cargar.']);
        }
        if (count($filas) > self::MAX_FILAS) {
            throw ValidationException::withMessages(['archivo' => 'El archivo supera el máximo de '.self::MAX_FILAS.' movimientos por carga.']);
        }

        return $filas;
    }

    /**
     * Catálogos activos indexados por nombre en minúscula, para resolver los nombres del archivo.
     *
     * @return array{tipos: Collection, impuestos: Collection, bancos: Collection}
     */
    private function catalogos(): array
    {
        $porNombre = fn (Collection $items) => $items->groupBy(fn ($item) => mb_strtolower(trim($item->nombre)));

        return [
            'tipos' => $porNombre(LdTipoMovimiento::where('status', 1)->get()),
            'impuestos' => $porNombre(LdImpuesto::where('status', 1)->get()),
            'bancos' => $porNombre(Banco::where('status', 1)->get()),
        ];
    }

    /**
     * Convierte la fila al formato del registro individual y la valida con sus mismas reglas.
     *
     * @param  array<string, mixed>  $fila
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validarFila(array $fila, array $catalogos, User $usuario): array
    {
        $mensajes = [];
        $texto = fn (string $columna) => ($fila[$columna] ?? null) === null || $fila[$columna] === '' ? null : (string) $fila[$columna];
        $resolver = function (string $catalogo, ?string $nombre, string $etiqueta) use ($catalogos, &$mensajes) {
            if ($nombre === null) {
                return null;
            }
            $coincidencias = $catalogos[$catalogo]->get(mb_strtolower($nombre), collect());
            if ($coincidencias->count() !== 1) {
                $mensajes[] = $coincidencias->isEmpty()
                    ? "{$etiqueta} \"{$nombre}\" no existe o está inactivo."
                    : "{$etiqueta} \"{$nombre}\" está repetido en el catálogo; corríjalo antes de cargar.";

                return null;
            }

            return $coincidencias->first();
        };

        $tipo = $resolver('tipos', $texto('tipo_movimiento'), 'El tipo de movimiento');
        $banco = $resolver('bancos', $texto('banco'), 'El banco');
        $subtotal = is_numeric($fila['subtotal'] ?? null) ? (float) $fila['subtotal'] : null;

        $impuestos = [];
        for ($i = 1; $i <= self::MAX_IMPUESTOS; $i++) {
            $impuesto = $resolver('impuestos', $texto("impuesto_{$i}"), "El impuesto {$i}");
            $valor = $texto("valor_impuesto_{$i}");
            if (! $impuesto) {
                if ($valor !== null && $texto("impuesto_{$i}") === null) {
                    $mensajes[] = "Hay valor del impuesto {$i} sin indicar el impuesto.";
                }

                continue;
            }
            $impuestos[] = [
                'impuesto_id' => $impuesto->id,
                'porcentaje' => (float) $impuesto->porcentaje,
                'valor' => $valor ?? ($subtotal === null ? null : round($subtotal * (float) $impuesto->porcentaje / 100, 2)),
            ];
        }

        $fecha = $this->fecha($fila['fecha'] ?? null);
        if ($fecha === false) {
            $mensajes[] = 'La fecha "'.$fila['fecha'].'" no es válida; use el formato AAAA-MM-DD o DD/MM/AAAA.';
        } elseif ($fecha->isFuture()) {
            $mensajes[] = 'La fecha no puede ser posterior a hoy.';
        }

        $datos = [
            'fecha' => $fecha ? $fecha->toDateString() : null,
            'tipo_movimiento_id' => $tipo?->id,
            'tercero_tipo_identificacion' => $texto('tercero_tipo_identificacion') === null ? null : strtoupper($texto('tercero_tipo_identificacion')),
            'tercero_identificacion' => $texto('tercero_identificacion'),
            'tercero_nombre' => $texto('tercero_nombre'),
            'tercero_telefono' => $texto('tercero_telefono'),
            'tercero_email' => $texto('tercero_email'),
            'tercero_direccion' => $texto('tercero_direccion'),
            'documento_tipo' => $texto('documento_tipo') === null ? null : strtolower($texto('documento_tipo')),
            'documento_numero' => $texto('documento_numero'),
            'subtotal' => $fila['subtotal'] ?? null,
            'impuestos' => $impuestos,
            'medio_pago' => strtolower($texto('medio_pago') ?? 'efectivo'),
            'banco_id' => $banco?->id,
            'observaciones' => $texto('observaciones'),
        ];

        $request = new StoreLdMovimientoRequest;
        $reglas = collect($request->rules())->except(['tipo_movimiento_id', 'banco_id', 'valor_total'])->all();
        $reglas['banco_id'] = ['nullable', 'required_if:medio_pago,transferencia,consignacion'];
        $validador = Validator::make($datos, $reglas, array_merge($request->messages(), [
            'impuestos.*.valor.numeric' => 'El valor de cada impuesto debe ser numérico.',
        ]), $request->attributes());
        if (! $tipo && $texto('tipo_movimiento') === null) {
            $mensajes[] = 'El tipo de movimiento es obligatorio.';
        }
        $mensajes = array_merge($mensajes, $validador->errors()->all());

        if ($error = StoreLdMovimientoRequest::errorTerceroInactivo($datos['tercero_tipo_identificacion'], $datos['tercero_identificacion'])) {
            $mensajes[] = $error;
        }

        if ($datos['medio_pago'] !== 'efectivo' && ! $usuario->can('fin_ldMedioPagoOtros')) {
            $mensajes[] = 'Solo puede registrar movimientos en efectivo.';
        }

        if (! $mensajes) {
            $calculado = $this->movimientoService->calcularTotales((float) $subtotal, $impuestos)['valor_total'];
            $informado = $texto('valor_total');
            if ($informado !== null && (! is_numeric($informado) || abs($calculado - (float) $informado) > LdMovimientoService::TOLERANCIA)) {
                $mensajes[] = "El valor total no cuadra con el subtotal, impuestos y retenciones (calculado: {$calculado}).";
            }
        }

        return [$datos, array_values(array_unique($mensajes))];
    }

    /**
     * Fecha de la fila: hoy si viene vacía, false si no se reconoce. Acepta la fecha de
     * Excel (número de serie) o texto en los formatos de FORMATOS_FECHA.
     */
    private function fecha(mixed $valor): Carbon|false
    {
        if ($valor === null || $valor === '') {
            return today();
        }
        if (is_numeric($valor)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $valor))->startOfDay();
        }
        foreach (self::FORMATOS_FECHA as $formato) {
            $fecha = \DateTime::createFromFormat("!{$formato}", (string) $valor);
            if ($fecha && $fecha->format($formato) === (string) $valor) {
                return Carbon::instance($fecha);
            }
        }

        return false;
    }

    /**
     * Escribe la fila de encabezados con el estilo de los Excel del sistema.
     *
     * @param  array<int, string>  $encabezados
     */
    private function encabezado(Worksheet $hoja, array $encabezados, int $fila = 1, int $columna = 1): void
    {
        $inicio = Coordinate::stringFromColumnIndex($columna);
        $fin = Coordinate::stringFromColumnIndex($columna + count($encabezados) - 1);
        $hoja->fromArray($encabezados, null, "{$inicio}{$fila}");
        $hoja->getStyle("{$inicio}{$fila}:{$fin}{$fila}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_ENCABEZADO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    /**
     * Escribe cada catálogo en un par de columnas (valor, detalle) y devuelve el rango de sus valores.
     *
     * @param  array<string, array<int, array{0: string, 1: string}>>  $catalogos
     * @return array<string, string>
     */
    private function hojaCatalogos(Worksheet $hoja, array $catalogos): array
    {
        $hoja->setTitle(self::HOJA_CATALOGOS);
        $rangos = [];
        $columna = 1;
        foreach ($catalogos as $titulo => $valores) {
            $this->encabezado($hoja, [$titulo, 'Detalle'], 1, $columna);
            if ($valores) {
                $hoja->fromArray($valores, null, Coordinate::stringFromColumnIndex($columna).'2');
            }

            $letra = Coordinate::stringFromColumnIndex($columna);
            $rangos[$titulo] = "'".self::HOJA_CATALOGOS."'!\${$letra}\$2:\${$letra}\$".max(2, count($valores) + 1);
            $hoja->getColumnDimension($letra)->setAutoSize(true);
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($columna + 1))->setAutoSize(true);
            $columna += 3;
        }

        return $rangos;
    }

    /**
     * Lista desplegable sobre un rango de celdas a partir de un rango del catálogo.
     */
    private function listaDesplegable(Worksheet $hoja, string $celdas, string $origen): void
    {
        $validacion = new DataValidation;
        $validacion->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Valor no válido')
            ->setError('Seleccione un valor de la lista (hoja '.self::HOJA_CATALOGOS.').')
            ->setFormula1($origen);
        $hoja->setDataValidation($celdas, $validacion);
    }

    /**
     * Restringe la columna de fecha a fechas hasta hoy.
     */
    private function validacionFecha(Worksheet $hoja, string $celdas): void
    {
        $validacion = new DataValidation;
        $validacion->setType(DataValidation::TYPE_DATE)
            ->setOperator(DataValidation::OPERATOR_LESSTHANOREQUAL)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Fecha no válida')
            ->setError('Escriba una fecha (AAAA-MM-DD) que no sea posterior a hoy.')
            ->setFormula1('TODAY()');
        $hoja->setDataValidation($celdas, $validacion);
    }

    /**
     * Hoja con la descripción de cada columna y las reglas de la carga.
     */
    private function hojaInstrucciones(Worksheet $hoja): void
    {
        $hoja->setTitle('Instrucciones');
        $this->encabezado($hoja, ['Columna', 'Obligatoria', 'Descripción']);
        $hoja->fromArray(collect($this->descripciones())
            ->map(fn (array $d, string $columna) => [$columna, ...$d])->values()->all(), null, 'A2');

        $hoja->setCellValue('A17', 'Reglas de la carga');
        $hoja->getStyle('A17')->getFont()->setBold(true);
        $hoja->fromArray([
            ['Puede cargar movimientos de varios días: cada uno queda con su fecha en el libro diario.'],
            ['Todos se registran en su turno de caja abierto: los pagos en efectivo se descuentan del efectivo de ese turno.'],
            ['Si alguna fila tiene errores no se registra ninguna: corrija las filas indicadas y vuelva a cargar el archivo.'],
            ['Máximo '.self::MAX_FILAS.' movimientos por archivo. Las filas vacías se ignoran.'],
            ['Vea la hoja Ejemplo: muestra filas diligenciadas y qué representa cada una.'],
        ], null, 'A18');

        $hoja->getColumnDimension('A')->setWidth(32);
        $hoja->getColumnDimension('B')->setWidth(22);
        $hoja->getColumnDimension('C')->setWidth(95);
    }

    /**
     * Obligatoriedad y descripción de cada columna (las de impuestos se agrupan).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function descripciones(): array
    {
        return [
            'fecha' => ['No', 'Fecha del movimiento (AAAA-MM-DD o DD/MM/AAAA). Vacía = hoy. No puede ser posterior a hoy.'],
            'tipo_movimiento' => ['Sí', 'Nombre del tipo de movimiento (define si es egreso u otro ingreso). Elíjalo de la lista.'],
            'tercero_tipo_identificacion' => ['Sí', 'Código del tipo de identificación: CC, NIT, CE, PA o TI.'],
            'tercero_identificacion' => ['Sí', 'Número de identificación del tercero, sin puntos ni dígito de verificación.'],
            'tercero_nombre' => ['Sí', 'Nombre o razón social del tercero. Si ese tipo y número de identificación ya está registrado, se usa el tercero registrado (con su nombre); si no, se crea.'],
            'tercero_telefono' => ['No', 'Teléfono del tercero. Solo se guarda cuando el tercero es nuevo.'],
            'tercero_email' => ['No', 'Correo del tercero. Solo se guarda cuando el tercero es nuevo.'],
            'tercero_direccion' => ['No', 'Dirección del tercero. Solo se guarda cuando el tercero es nuevo.'],
            'documento_tipo' => ['Sí', 'Código del documento soporte (ver hoja Catalogos).'],
            'documento_numero' => ['No', 'Número de la factura, cuenta de cobro o recibo.'],
            'subtotal' => ['Sí', 'Valor antes de impuestos, sin puntos de miles ni signo $.'],
            'impuesto_N / valor_impuesto_N' => ['No', 'Hasta '.self::MAX_IMPUESTOS.' impuestos o retenciones por nombre. Si el valor queda vacío se calcula con el porcentaje del catálogo.'],
            'valor_total' => ['No', 'Si se informa, se verifica contra subtotal + impuestos - retenciones (tolerancia $1).'],
            'medio_pago' => ['No', 'Código del medio de pago. Vacío = efectivo. Otros medios requieren permiso.'],
            'banco' => ['Si es transferencia o consignación', 'Nombre del banco.'],
            'observaciones' => ['No', 'Texto libre.'],
        ];
    }

    /**
     * Hoja con filas de ejemplo diligenciadas con los catálogos vigentes y la explicación
     * de cada una. Si un catálogo no tiene el valor que el ejemplo necesita, se muestra
     * un nombre ilustrativo entre corchetes.
     */
    private function hojaEjemplo(Worksheet $hoja, Collection $tipos, Collection $impuestos, Collection $bancos): void
    {
        $hoja->setTitle('Ejemplo');
        $hoja->setCellValue('A1', 'Ejemplo de diligenciamiento. Esta hoja NO se carga: escriba sus movimientos en la hoja Movimientos.');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $egreso = $tipos->firstWhere('clase', LdTipoMovimiento::CLASE_EGRESO)?->nombre ?? '[Arriendo]';
        $otroEgreso = $tipos->where('clase', LdTipoMovimiento::CLASE_EGRESO)->skip(1)->first()?->nombre ?? $egreso;
        $ingreso = $tipos->firstWhere('clase', LdTipoMovimiento::CLASE_INGRESO)?->nombre ?? '[Arrendamiento de aulas]';
        $impuesto = $impuestos->firstWhere('efecto', LdImpuesto::EFECTO_SUMA);
        $retencion = $impuestos->firstWhere('efecto', LdImpuesto::EFECTO_RESTA);
        $banco = $bancos->first()?->nombre ?? '[Bancolombia]';

        $subtotal = 100000;
        $valorImpuesto = round($subtotal * (float) ($impuesto->porcentaje ?? 19) / 100, 2);
        $valorRetencion = round($subtotal * (float) ($retencion->porcentaje ?? 4) / 100, 2);
        $total = $subtotal + $valorImpuesto - $valorRetencion;

        $filas = [
            [
                'fecha' => today()->subDays(2)->toDateString(), 'tipo_movimiento' => $egreso,
                'tercero_tipo_identificacion' => 'NIT', 'tercero_identificacion' => '900123456',
                'tercero_nombre' => 'Inmobiliaria Central S.A.S.', 'documento_tipo' => 'factura', 'documento_numero' => 'FE-1001',
                'subtotal' => $subtotal,
                'impuesto_1' => $impuesto->nombre ?? '[IVA 19%]', 'valor_impuesto_1' => $valorImpuesto,
                'impuesto_2' => $retencion->nombre ?? '[Retefuente 4%]',
                'valor_total' => $total, 'medio_pago' => 'efectivo', 'observaciones' => 'Arriendo de la sede, mes de octubre',
                'explicacion' => "Egreso de hace dos días pagado en efectivo. El valor del impuesto se escribió; el de la retención se dejó vacío y se calcula solo. valor_total = {$subtotal} + {$valorImpuesto} - {$valorRetencion} = {$total}.",
            ],
            [
                'tipo_movimiento' => $otroEgreso, 'tercero_tipo_identificacion' => 'CC', 'tercero_identificacion' => '1052345678',
                'tercero_nombre' => 'Pedro Gómez', 'tercero_telefono' => '3001234567',
                'documento_tipo' => 'cuenta_cobro', 'subtotal' => 35000,
                'explicacion' => 'Egreso de hoy (fecha vacía), en efectivo (medio vacío), sin impuestos ni número de documento. Si Pedro Gómez no está registrado como tercero, se crea con su teléfono.',
            ],
            [
                'fecha' => today()->subDay()->format('d/m/Y'), 'tipo_movimiento' => $ingreso,
                'tercero_tipo_identificacion' => 'NIT', 'tercero_identificacion' => '800987654',
                'tercero_nombre' => 'Fundación Educar', 'documento_tipo' => 'recibo', 'documento_numero' => 'RC-55',
                'subtotal' => 250000, 'medio_pago' => 'transferencia', 'banco' => $banco,
                'explicacion' => 'Otro ingreso de ayer (fecha en formato DD/MM/AAAA) recibido por transferencia: el banco es obligatorio y se requiere permiso para medios distintos de efectivo.',
            ],
        ];

        $columnas = [...self::columnas(), 'explicacion'];
        $this->encabezado($hoja, array_map(fn ($c) => $c === 'explicacion' ? '¿Qué representa esta fila?' : $c, $columnas), 3);
        foreach ($filas as $i => $fila) {
            $hoja->fromArray(array_map(fn ($c) => $fila[$c] ?? null, $columnas), null, 'A'.($i + 4), true);
        }

        $ultima = Coordinate::stringFromColumnIndex(count($columnas));
        $hoja->getStyle("{$ultima}3")->getFill()->setStartColor(new Color('C55A11'));
        $hoja->getStyle("{$ultima}4:{$ultima}6")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $hoja->getStyle('A4:A6')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        foreach ($columnas as $indice => $columna) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($indice + 1))->setWidth(max(14, strlen($columna) + 4));
        }
        $hoja->getColumnDimension($ultima)->setWidth(70);

        $hoja->setCellValue('A8', 'Los nombres de tipos, impuestos y bancos salen de su catálogo actual (hoja Catalogos); los que aparecen entre [corchetes] son ilustrativos porque ese catálogo aún no los tiene.');
        $hoja->setCellValue('A9', 'Las celdas vacías también son válidas: solo las columnas marcadas como obligatorias en la hoja Instrucciones deben diligenciarse.');
        $hoja->freezePane('B4');
    }

    /**
     * Pares [código, descripción] de un arreglo constante.
     *
     * @param  array<string, string>  $opciones
     * @return array<int, array{0: string, 1: string}>
     */
    private function pares(array $opciones): array
    {
        return collect($opciones)->map(fn ($etiqueta, $codigo) => [$codigo, $etiqueta])->values()->all();
    }
}
