<?php

namespace App\Services\Activos;

use App\Http\Requests\Api\Activos\StoreActActivoRequest;
use App\Models\Activos\ActActivo;
use App\Models\Activos\ActCategoria;
use App\Models\Configuracion\Sede;
use App\Models\User;
use App\Services\Concerns\ArmaPlantillaExcel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Carga masiva de activos fijos desde Excel (o CSV) y generación de la plantilla.
 *
 * La categoría, la sede y el área se diligencian por nombre (listas desplegables) y el
 * responsable por su número de documento (debe ser personal, no estudiante). Cada fila
 * pasa por las mismas reglas del registro individual (StoreActActivoRequest): sede
 * asignada al usuario, área de esa sede, fecha de ingreso no futura, estado distinto de
 * baja. La carga es todo o nada y cada activo queda con su movimiento de ingreso.
 */
class ActActivoImportacionService
{
    use ArmaPlantillaExcel;

    /** Máximo de filas por archivo. */
    public const MAX_FILAS = 500;

    private const HOJA_DATOS = 'Activos';

    private const COLUMNAS_OBLIGATORIAS = ['nombre', 'categoria', 'sede', 'fecha_ingreso', 'valor'];

    public function __construct(private readonly ActActivoService $activos) {}

    /**
     * Columnas de la hoja de datos, en orden.
     *
     * @return array<int, string>
     */
    public static function columnas(): array
    {
        return [
            'nombre', 'descripcion', 'categoria', 'sede', 'area', 'responsable_documento',
            'marca', 'modelo', 'serial', 'fecha_ingreso', 'valor', 'estado', 'observaciones',
        ];
    }

    /**
     * Plantilla con la hoja de datos (listas desplegables), instrucciones, ejemplo y los catálogos
     * vigentes: categorías activas, sedes del usuario con sus áreas, estados y responsables.
     */
    public function plantilla(User $usuario): Spreadsheet
    {
        $categorias = ActCategoria::where('status', 1)->orderBy('nombre')->get();
        $sedes = $this->sedesDe($usuario);
        $responsables = User::personal()->whereNotNull('documento')->orderBy('primer_nombre')->get();
        $estados = $this->estadosCargables();

        $spreadsheet = new Spreadsheet;
        $datos = $spreadsheet->getActiveSheet();
        $datos->setTitle(self::HOJA_DATOS);
        $this->encabezado($datos, self::columnas());

        $this->hojaInstrucciones($spreadsheet->createSheet());
        $this->hojaEjemplo($spreadsheet->createSheet(), $categorias, $sedes);
        $rangos = $this->hojaCatalogos($spreadsheet->createSheet(), [
            'Categorías' => $categorias->map(fn ($c) => [$c->nombre, (string) $c->descripcion])->all(),
            'Sedes' => $sedes->map(fn ($s) => [$s->nombre, ''])->all(),
            'Áreas por sede' => $sedes->flatMap(fn ($s) => $s->areas->map(fn ($a) => [$a->nombre, $s->nombre]))->all(),
            'Estados' => $this->pares($estados),
            'Responsables' => $responsables->map(fn ($u) => [(string) $u->documento, $u->name])->all(),
        ]);

        $listas = ['categoria' => $rangos['Categorías'], 'sede' => $rangos['Sedes'], 'estado' => $rangos['Estados']];
        $ultima = self::MAX_FILAS + 1;
        $descripciones = $this->descripciones();
        foreach (self::columnas() as $indice => $columna) {
            $letra = Coordinate::stringFromColumnIndex($indice + 1);
            $datos->getColumnDimension($letra)->setWidth(max(16, strlen($columna) + 4));
            [$obligatoria, $descripcion] = $descripciones[$columna];
            $nota = $datos->getComment("{$letra}1");
            $nota->setWidth('260pt')->setHeight('90pt');
            $nota->getText()->createTextRun("Obligatoria: {$obligatoria}\n{$descripcion}");

            if (isset($listas[$columna])) {
                $this->listaDesplegable($datos, "{$letra}2:{$letra}{$ultima}", $listas[$columna]);
            }
        }
        $datos->getStyle("J2:J{$ultima}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $this->validacionFecha($datos, "J2:J{$ultima}");
        $datos->freezePane('B2');
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Valida el archivo y, si todas las filas son válidas, registra los activos.
     *
     * @return array{errores: array<int, array{fila: int, mensajes: array<int, string>}>, activos: Collection<int, ActActivo>}
     */
    public function importar(string $ruta, User $usuario): array
    {
        $filas = $this->leerFilas($ruta);
        $catalogos = $this->catalogos($usuario);

        $validos = [];
        $errores = [];
        foreach ($filas as $numero => $fila) {
            [$datos, $mensajes] = $this->validarFila($fila, $catalogos);
            if ($mensajes) {
                $errores[] = ['fila' => $numero, 'mensajes' => $mensajes];
            } else {
                $validos[] = $datos;
            }
        }

        if ($errores) {
            return ['errores' => $errores, 'activos' => collect()];
        }

        $activos = DB::transaction(fn () => collect($validos)
            ->map(fn (array $datos) => $this->activos->registrar($datos, $usuario->id)));

        return ['errores' => [], 'activos' => $activos];
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
            throw ValidationException::withMessages(['archivo' => 'El archivo no tiene activos para cargar.']);
        }
        if (count($filas) > self::MAX_FILAS) {
            throw ValidationException::withMessages(['archivo' => 'El archivo supera el máximo de '.self::MAX_FILAS.' activos por carga.']);
        }

        return $filas;
    }

    /**
     * Sedes del usuario con sus áreas activas, ordenadas por nombre.
     *
     * @return Collection<int, Sede>
     */
    private function sedesDe(User $usuario): Collection
    {
        return Sede::whereIn('id', $usuario->idsSedesAccesibles())
            ->with(['areas' => fn ($q) => $q->where('areas.status', 1)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Estados con los que puede nacer un activo (todos menos baja).
     *
     * @return array<string, string>
     */
    private function estadosCargables(): array
    {
        return array_diff_key(ActActivo::ESTADOS, [ActActivo::ESTADO_BAJA => true]);
    }

    /**
     * Catálogos para resolver los textos del archivo: categorías activas y sedes del usuario por
     * nombre en minúscula, y el personal por documento.
     *
     * @return array{categorias: Collection, sedes: Collection, responsables: Collection}
     */
    private function catalogos(User $usuario): array
    {
        $porNombre = fn (Collection $items) => $items->groupBy(fn ($item) => mb_strtolower(trim($item->nombre)));

        return [
            'categorias' => $porNombre(ActCategoria::where('status', 1)->get()),
            'sedes' => $porNombre($this->sedesDe($usuario)),
            'responsables' => User::personal()->whereNotNull('documento')->get()->groupBy(fn ($u) => trim((string) $u->documento)),
        ];
    }

    /**
     * Convierte la fila al formato del registro individual y la valida con sus mismas reglas.
     *
     * @param  array<string, mixed>  $fila
     * @param  array{categorias: Collection, sedes: Collection, responsables: Collection}  $catalogos
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validarFila(array $fila, array $catalogos): array
    {
        $mensajes = [];
        $texto = fn (string $columna) => ($fila[$columna] ?? null) === null || $fila[$columna] === '' ? null : (string) $fila[$columna];
        $resolver = function (Collection $coincidencias, ?string $valor, string $etiqueta, string $problema) use (&$mensajes) {
            if ($valor === null) {
                return null;
            }
            if ($coincidencias->count() !== 1) {
                $mensajes[] = $coincidencias->isEmpty()
                    ? "{$etiqueta} \"{$valor}\" {$problema}"
                    : "{$etiqueta} \"{$valor}\" está repetido en el catálogo; corríjalo antes de cargar.";

                return null;
            }

            return $coincidencias->first();
        };
        $buscar = fn (string $catalogo, ?string $valor) => $valor === null ? collect() : $catalogos[$catalogo]->get($catalogo === 'responsables' ? $valor : mb_strtolower($valor), collect());

        $categoria = $resolver($buscar('categorias', $texto('categoria')), $texto('categoria'), 'La categoría', 'no existe o está inactiva.');
        $sede = $resolver($buscar('sedes', $texto('sede')), $texto('sede'), 'La sede', 'no existe o no está asignada a su usuario.');
        $responsable = $resolver($buscar('responsables', $texto('responsable_documento')), $texto('responsable_documento'), 'El responsable con documento', 'no existe o no es personal del instituto.');

        $area = null;
        if ($texto('area') !== null && $sede) {
            $coincidencias = $sede->areas->filter(fn ($a) => mb_strtolower(trim($a->nombre)) === mb_strtolower($texto('area')));
            $area = $resolver($coincidencias, $texto('area'), 'El área', "no existe o no pertenece a la sede {$sede->nombre}.");
        } elseif ($texto('area') !== null) {
            $mensajes[] = 'No se puede validar el área porque la sede no es válida.';
        }

        $fecha = null;
        // Vacía no se valida aquí: la regla `required` del registro individual ya lo reporta.
        if ($texto('fecha_ingreso') !== null && ($fecha = $this->fecha($fila['fecha_ingreso'])) === false) {
            $mensajes[] = 'La fecha de ingreso "'.$fila['fecha_ingreso'].'" no es válida; use el formato AAAA-MM-DD o DD/MM/AAAA.';
        }

        $estado = $texto('estado') === null ? null : $this->estadoPorNombre($texto('estado'));
        if ($texto('estado') !== null && $estado === null) {
            $mensajes[] = 'El estado "'.$texto('estado').'" no es válido; use uno de: '.implode(', ', $this->estadosCargables()).'.';
        }

        $datos = array_filter([
            'nombre' => $texto('nombre'),
            'descripcion' => $texto('descripcion'),
            'categoria_id' => $categoria?->id,
            'sede_id' => $sede?->id,
            'area_id' => $area?->id,
            'responsable_id' => $responsable?->id,
            'marca' => $texto('marca'),
            'modelo' => $texto('modelo'),
            'serial' => $texto('serial'),
            'fecha_ingreso' => $fecha ? $fecha->toDateString() : null,
            'valor' => $fila['valor'] ?? null,
            'estado' => $estado,
            'observaciones' => $texto('observaciones'),
        ], fn ($valor) => $valor !== null && $valor !== '');

        $request = new StoreActActivoRequest;
        // La categoría, la sede, el área y el responsable ya se resolvieron contra sus catálogos.
        $reglas = collect($request->rules())->except(['categoria_id', 'sede_id', 'area_id', 'responsable_id'])->all();
        $validador = Validator::make($datos, $reglas, $request->messages(), $request->attributes());
        if (! $categoria && $texto('categoria') === null) {
            $mensajes[] = 'La categoría es obligatoria.';
        }
        if (! $sede && $texto('sede') === null) {
            $mensajes[] = 'La sede es obligatoria.';
        }

        return [$datos, array_values(array_unique(array_merge($mensajes, $validador->errors()->all())))];
    }

    /**
     * Estado según el texto del archivo: acepta la clave (operativo) o la etiqueta (En mantenimiento).
     */
    private function estadoPorNombre(string $texto): ?string
    {
        $buscado = mb_strtolower($texto);
        foreach ($this->estadosCargables() as $clave => $etiqueta) {
            if ($buscado === $clave || $buscado === mb_strtolower($etiqueta)) {
                return $clave;
            }
        }

        return null;
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

        $fila = count($this->descripciones()) + 3;
        $hoja->setCellValue("A{$fila}", 'Reglas de la carga');
        $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
        $hoja->fromArray([
            ['Cada fila es un activo. No hay columna de código: el sistema asigna el ID y lo muestra en el inventario.'],
            ['Cada activo queda en la sede y el área indicadas y con su movimiento de ingreso en el historial; solo puede cargar en sus sedes asignadas.'],
            ['La imagen del activo se agrega después, desde la ficha de cada activo.'],
            ['Un activo nuevo no puede cargarse de baja. Los traslados se hacen desde la pantalla de activos, no por carga masiva.'],
            ['Si alguna fila tiene errores no se registra ninguna: corrija las filas indicadas y vuelva a cargar el archivo.'],
            ['Máximo '.self::MAX_FILAS.' activos por archivo. Las filas vacías se ignoran.'],
            ['Vea la hoja Ejemplo: muestra filas diligenciadas. Las listas de valores permitidos están en la hoja Catalogos.'],
        ], null, 'A'.($fila + 1));

        $hoja->getColumnDimension('A')->setWidth(26);
        $hoja->getColumnDimension('B')->setWidth(14);
        $hoja->getColumnDimension('C')->setWidth(100);
    }

    /**
     * Obligatoriedad y descripción de cada columna.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function descripciones(): array
    {
        return [
            'nombre' => ['Sí', 'Nombre del activo (máx. 200 caracteres). Ej.: Elevador de dos columnas.'],
            'descripcion' => ['No', 'Descripción libre (máx. 2000 caracteres).'],
            'categoria' => ['Sí', 'Nombre de una categoría activa. Elíjala de la lista.'],
            'sede' => ['Sí', 'Nombre de una de sus sedes asignadas. Elíjala de la lista.'],
            'area' => ['No', 'Nombre del área dentro de esa sede (ver hoja Catalogos, "Áreas por sede"). Vacía = sin área.'],
            'responsable_documento' => ['No', 'Número de documento del responsable; debe ser personal del instituto, no un estudiante (ver hoja Catalogos).'],
            'marca' => ['No', 'Marca (máx. 100 caracteres).'],
            'modelo' => ['No', 'Modelo (máx. 100 caracteres).'],
            'serial' => ['No', 'Serial del fabricante (máx. 100 caracteres).'],
            'fecha_ingreso' => ['Sí', 'Fecha de ingreso al inventario (AAAA-MM-DD o DD/MM/AAAA). No puede ser posterior a hoy.'],
            'valor' => ['Sí', 'Valor de adquisición, sin puntos de miles ni signo $. Puede ser 0.'],
            'estado' => ['No', 'Operativo, En mantenimiento o Disponible. Vacío = Operativo. No se admite De baja.'],
            'observaciones' => ['No', 'Texto libre (máx. 2000 caracteres).'],
        ];
    }

    /**
     * Hoja con filas de ejemplo diligenciadas con los catálogos vigentes; si un catálogo está
     * vacío se muestra un nombre ilustrativo entre corchetes.
     *
     * @param  Collection<int, ActCategoria>  $categorias
     * @param  Collection<int, Sede>  $sedes
     */
    private function hojaEjemplo(Worksheet $hoja, Collection $categorias, Collection $sedes): void
    {
        $hoja->setTitle('Ejemplo');
        $hoja->setCellValue('A1', 'Ejemplo de diligenciamiento. Esta hoja NO se carga: escriba sus activos en la hoja '.self::HOJA_DATOS.'.');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $sede = $sedes->first();
        $categoria = $categorias->first()?->nombre ?? '[Herramienta mayor]';
        $area = $sede?->areas->first()?->nombre;

        $filas = [
            [
                'nombre' => 'Elevador de dos columnas', 'categoria' => $categoria, 'sede' => $sede?->nombre ?? '[Sede Centro]',
                'area' => $area ?? '[Taller de motores]', 'marca' => 'Rotary', 'modelo' => 'SPOA10', 'serial' => 'RT-2024-0183',
                'fecha_ingreso' => today()->subMonths(3)->toDateString(), 'valor' => 18500000, 'estado' => 'operativo',
            ],
            [
                'nombre' => 'Proyector de aula', 'categoria' => $categoria, 'sede' => $sede?->nombre ?? '[Sede Centro]',
                'fecha_ingreso' => today()->subDays(10)->format('d/m/Y'), 'valor' => 2400000,
            ],
        ];

        $columnas = self::columnas();
        $this->encabezado($hoja, $columnas, 3);
        foreach ($filas as $i => $fila) {
            $hoja->fromArray(array_map(fn ($c) => $fila[$c] ?? null, $columnas), null, 'A'.($i + 4), true);
        }
        foreach ($columnas as $indice => $columna) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($indice + 1))->setWidth(max(16, strlen($columna) + 4));
        }
        $hoja->setCellValue('A7', 'Fila 1: activo completo en un área y operativo. Fila 2: solo lo obligatorio; queda sin área ni responsable, operativo, con la fecha en formato DD/MM/AAAA.');
        $hoja->setCellValue('A8', 'Los nombres de categorías, sedes y áreas salen de su catálogo actual (hoja Catalogos); los que aparecen entre [corchetes] son ilustrativos porque ese catálogo aún no los tiene.');
        $hoja->freezePane('B4');
    }
}
