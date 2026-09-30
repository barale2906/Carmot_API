<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * ExportarDemoCommand
 *
 * Genera una copia completa (estructura + datos) de la base de datos MySQL actual
 * en un archivo .sql, por defecto database/seeders/demo.sql. Se implementa en PHP
 * puro (sin mysqldump) para que funcione igual dentro del contenedor de la app y
 * en el servidor de pruebas.
 *
 * Formato del archivo:
 *  - Cada tabla: DROP TABLE IF EXISTS + CREATE TABLE (SHOW CREATE TABLE) + INSERTs.
 *  - Cada INSERT ocupa una sola línea y termina en ";" (los saltos de línea de los
 *    datos se escapan), lo que permite a demo:importar leerlo en streaming.
 *  - Incluye la tabla `migrations`, de modo que tras importar basta con
 *    `php artisan migrate` para aplicar solo las migraciones más recientes.
 *
 * Uso:
 *  php artisan demo:exportar                      → database/seeders/demo.sql
 *  php artisan demo:exportar --file=antes_nomina  → database/seeders/antes_nomina.sql
 */
class ExportarDemoCommand extends Command
{
    /** Filas máximas por sentencia INSERT. */
    private const FILAS_POR_INSERT = 500;

    /** Tamaño máximo aproximado (bytes) de una sentencia INSERT. */
    private const BYTES_POR_INSERT = 1048576;

    protected $signature = 'demo:exportar
                            {--file=demo.sql : Nombre del archivo en database/seeders o ruta absoluta}';

    protected $description = 'Exporta la base de datos actual (estructura y datos) a un archivo .sql de demo';

    /**
     * Ejecuta el comando.
     *
     * @return int Código de salida
     */
    public function handle(): int
    {
        $conexion = DB::connection();

        if ($conexion->getDriverName() !== 'mysql') {
            $this->error('Este comando solo soporta conexiones MySQL.');
            return Command::FAILURE;
        }

        $ruta = self::resolverRuta($this->option('file'));
        $archivo = @fopen($ruta, 'wb');

        if ($archivo === false) {
            $this->error("No se pudo escribir el archivo: {$ruta}");
            return Command::FAILURE;
        }

        // Lectura en UTC para que las columnas TIMESTAMP se exporten sin conversión
        $conexion->statement("SET time_zone = '+00:00'");

        $tablas = $this->obtenerTablas($conexion);

        fwrite($archivo, $this->encabezado($conexion->getDatabaseName(), count($tablas)));

        $totalFilas = 0;
        foreach ($tablas as $tabla) {
            $filas = $this->exportarTabla($conexion, $tabla, $archivo);
            $totalFilas += $filas;
            $this->line("  {$tabla}: {$filas} filas");
        }

        fwrite($archivo, "SET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n");
        fclose($archivo);

        $this->info(sprintf(
            'Exportación completa: %d tablas, %d filas → %s (%s KB)',
            count($tablas),
            $totalFilas,
            $ruta,
            number_format(filesize($ruta) / 1024, 1)
        ));

        return Command::SUCCESS;
    }

    /**
     * Resuelve el nombre de archivo: si no es ruta absoluta se ubica en database/seeders
     * y se le agrega la extensión .sql cuando falta. Compartido con demo:importar.
     *
     * @param  string $archivo Nombre o ruta recibida por opción
     * @return string Ruta absoluta del archivo .sql
     */
    public static function resolverRuta(string $archivo): string
    {
        if (!str_ends_with(strtolower($archivo), '.sql')) {
            $archivo .= '.sql';
        }

        return str_starts_with($archivo, DIRECTORY_SEPARATOR)
            ? $archivo
            : database_path('seeders' . DIRECTORY_SEPARATOR . $archivo);
    }

    /**
     * Lista las tablas base (sin vistas) de la base de datos actual.
     *
     * @param  Connection $conexion
     * @return array<int, string>
     */
    private function obtenerTablas(Connection $conexion): array
    {
        $filas = $conexion->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        return array_map(fn ($fila) => array_values((array) $fila)[0], $filas);
    }

    /**
     * Construye el encabezado del archivo con las variables de sesión necesarias
     * para importar sin conflictos de llaves foráneas ni conversión de zona horaria.
     *
     * @param  string $baseDatos
     * @param  int    $cantidadTablas
     * @return string
     */
    private function encabezado(string $baseDatos, int $cantidadTablas): string
    {
        $fecha = now()->toDateTimeString();

        return <<<SQL
        -- Carmot API — copia de datos demo
        -- Base de datos origen: {$baseDatos}
        -- Generado: {$fecha} — {$cantidadTablas} tablas
        -- Importar con: php artisan demo:importar

        SET NAMES utf8mb4;
        SET time_zone = '+00:00';
        SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
        SET FOREIGN_KEY_CHECKS = 0;
        SET UNIQUE_CHECKS = 0;


        SQL;
    }

    /**
     * Escribe la estructura y los datos de una tabla en el archivo.
     *
     * @param  Connection $conexion
     * @param  string     $tabla
     * @param  resource   $archivo
     * @return int Cantidad de filas exportadas
     */
    private function exportarTabla(Connection $conexion, string $tabla, $archivo): int
    {
        $crear = array_values((array) $conexion->selectOne("SHOW CREATE TABLE `{$tabla}`"))[1];

        fwrite($archivo, "-- Tabla `{$tabla}`\nDROP TABLE IF EXISTS `{$tabla}`;\n{$crear};\n");

        // Se excluyen columnas generadas (VIRTUAL/STORED): MySQL no permite insertarlas
        $columnas = array_map(fn ($c) => $c->COLUMN_NAME, $conexion->select(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
               AND EXTRA NOT LIKE '%VIRTUAL GENERATED%' AND EXTRA NOT LIKE '%STORED GENERATED%'
             ORDER BY ORDINAL_POSITION",
            [$conexion->getDatabaseName(), $tabla]
        ));

        $listaColumnas = implode(', ', array_map(fn ($c) => "`{$c}`", $columnas));
        $prefijo = "INSERT INTO `{$tabla}` ({$listaColumnas}) VALUES\n";
        $lote = [];
        $bytesLote = 0;
        $total = 0;

        foreach ($conexion->cursor("SELECT {$listaColumnas} FROM `{$tabla}`") as $fila) {
            $valores = '(' . implode(',', array_map([$this, 'valorSql'], (array) $fila)) . ')';
            $lote[] = $valores;
            $bytesLote += strlen($valores);
            $total++;

            if (count($lote) >= self::FILAS_POR_INSERT || $bytesLote >= self::BYTES_POR_INSERT) {
                fwrite($archivo, $prefijo . implode(",\n", $lote) . ";\n");
                $lote = [];
                $bytesLote = 0;
            }
        }

        if ($lote !== []) {
            fwrite($archivo, $prefijo . implode(",\n", $lote) . ";\n");
        }

        fwrite($archivo, "\n");

        return $total;
    }

    /**
     * Convierte un valor PHP en literal SQL escapado (equivalente a mysql_real_escape_string).
     * Los saltos de línea se escapan, por lo que ninguna fila de datos contiene "\n" literal.
     *
     * @param  mixed $valor
     * @return string
     */
    private function valorSql(mixed $valor): string
    {
        if ($valor === null) {
            return 'NULL';
        }

        if (is_int($valor)) {
            return (string) $valor;
        }

        // var_export respeta serialize_precision: no pierde decimales como (string)
        if (is_float($valor)) {
            return var_export($valor, true);
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        return "'" . strtr((string) $valor, [
            '\\'   => '\\\\',
            "\0"   => '\\0',
            "\n"   => '\\n',
            "\r"   => '\\r',
            "'"    => "\\'",
            "\x1a" => '\\Z',
        ]) . "'";
    }
}
