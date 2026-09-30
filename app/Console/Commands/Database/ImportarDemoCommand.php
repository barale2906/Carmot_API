<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ImportarDemoCommand
 *
 * Restaura la base de datos a partir de un archivo .sql generado por demo:exportar
 * (por defecto database/seeders/demo.sql). Elimina TODAS las tablas actuales antes de
 * cargar el archivo, deja la base exactamente como estaba al exportar (incluida la
 * tabla `migrations`) y limpia la caché de permisos de spatie.
 *
 * Flujo típico en el servidor de pruebas:
 *  php artisan demo:importar --force   → carga el demo
 *  php artisan migrate --force         → aplica solo las migraciones nuevas
 *
 * Con --solo-vacia se niega a importar si alguna tabla (distinta de `migrations`) tiene
 * registros; es el modo que usa la ruta web oculta para no destruir datos reales.
 *
 * El archivo se lee en streaming: cada sentencia termina en una línea que acaba en ";"
 * (los datos exportados nunca contienen saltos de línea literales).
 */
class ImportarDemoCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'demo:importar
                            {--file=demo.sql : Nombre del archivo en database/seeders o ruta absoluta}
                            {--solo-vacia : Abortar si la base de datos ya contiene registros}
                            {--force : Ejecutar sin confirmación en producción}';

    protected $description = 'Reemplaza la base de datos actual con el contenido de un archivo .sql de demo';

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

        $ruta = ExportarDemoCommand::resolverRuta($this->option('file'));

        if (!is_file($ruta) || !is_readable($ruta)) {
            $this->error("No existe o no se puede leer el archivo: {$ruta}");
            return Command::FAILURE;
        }

        if ($this->option('solo-vacia') && ($tabla = $this->primeraTablaConDatos()) !== null) {
            $this->error("La base de datos no está vacía (la tabla '{$tabla}' tiene registros). Importación cancelada.");
            return Command::FAILURE;
        }

        if (!$this->confirmToProceed("Se eliminarán TODAS las tablas de '{$conexion->getDatabaseName()}' y se cargará {$ruta}")) {
            return Command::FAILURE;
        }

        $this->line('Eliminando tablas actuales...');
        Schema::dropAllTables();

        $this->line("Importando {$ruta}...");
        $sentencias = $this->ejecutarArchivo($ruta);

        $this->callSilently('permission:cache-reset');

        $this->info("Importación completa: {$sentencias} sentencias ejecutadas.");
        $this->line('Si hay migraciones nuevas, ejecute: php artisan migrate');

        return Command::SUCCESS;
    }

    /**
     * Devuelve la primera tabla con al menos un registro, o null si la base está vacía.
     * Se ignora `migrations`, que puede tener filas tras un `migrate` sin datos.
     *
     * @return string|null
     */
    private function primeraTablaConDatos(): ?string
    {
        $tablas = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        foreach ($tablas as $fila) {
            $tabla = array_values((array) $fila)[0];

            if ($tabla !== 'migrations' && DB::table($tabla)->exists()) {
                return $tabla;
            }
        }

        return null;
    }

    /**
     * Lee el archivo línea a línea y ejecuta cada sentencia completa.
     * Las variables de sesión del encabezado (FOREIGN_KEY_CHECKS, time_zone, ...)
     * se mantienen porque todas las sentencias usan la misma conexión.
     *
     * @param  string $ruta
     * @return int Cantidad de sentencias ejecutadas
     */
    private function ejecutarArchivo(string $ruta): int
    {
        $archivo = fopen($ruta, 'rb');
        $buffer = '';
        $total = 0;

        try {
            while (($linea = fgets($archivo)) !== false) {
                if ($buffer === '' && (trim($linea) === '' || str_starts_with($linea, '--'))) {
                    continue;
                }

                $buffer .= $linea;

                if (str_ends_with(rtrim($linea), ';')) {
                    DB::unprepared($buffer);
                    $buffer = '';
                    $total++;
                }
            }

            if (trim($buffer) !== '') {
                DB::unprepared($buffer);
                $total++;
            }
        } finally {
            fclose($archivo);
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return $total;
    }
}
