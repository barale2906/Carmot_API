<?php

namespace Tests\Feature\Console;

use App\Console\Commands\Database\ExportarDemoCommand;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pruebas de los comandos demo:exportar y demo:importar.
 *
 * No usa RefreshDatabase: la importación ejecuta DDL (DROP/CREATE), que en MySQL
 * hace commit implícito y rompería la transacción de la prueba. En su lugar se
 * trabaja con una tabla propia `demo_prueba` que se crea y elimina en cada prueba;
 * la importación deja el resto de la base de pruebas tal como estaba.
 */
class DemoBaseDatosTest extends TestCase
{
    private string $archivo;

    /** Filas con caracteres que deben sobrevivir el escape del volcado. */
    private array $filas = [
        ['id' => 1, 'texto' => "O'Brien \"comillas\" \\ barra", 'monto' => '1500.75', 'nota' => null],
        ['id' => 2, 'texto' => "línea 1\nlínea 2;\r\nfin;", 'monto' => '0.00', 'nota' => 'ñandú — 日本'],
        ['id' => 3, 'texto' => "-- no es comentario\n;", 'monto' => '-3.10', 'nota' => "\0nulo\x1a"],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');

        Schema::dropIfExists('demo_prueba');
        Schema::create('demo_prueba', function (Blueprint $table) {
            $table->id();
            $table->text('texto');
            $table->decimal('monto', 12, 2);
            $table->string('nota')->nullable();
        });
        DB::table('demo_prueba')->insert($this->filas);

        $this->archivo = sys_get_temp_dir() . '/demo_prueba_' . uniqid() . '.sql';
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('demo_prueba');
        @unlink($this->archivo);

        parent::tearDown();
    }

    // ─── demo:exportar ────────────────────────────────────────────────────────

    public function test_exportar_genera_archivo_con_estructura_y_datos(): void
    {
        $this->artisan('demo:exportar', ['--file' => $this->archivo])
            ->assertSuccessful();

        $this->assertFileExists($this->archivo);
        $contenido = file_get_contents($this->archivo);

        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 0;', $contenido);
        $this->assertStringContainsString('CREATE TABLE `demo_prueba`', $contenido);
        $this->assertStringContainsString('CREATE TABLE `migrations`', $contenido);
        $this->assertStringContainsString('INSERT INTO `demo_prueba`', $contenido);
    }

    public function test_exportar_no_deja_saltos_de_linea_literales_dentro_de_los_datos(): void
    {
        $this->artisan('demo:exportar', ['--file' => $this->archivo])->assertSuccessful();

        $lineasInsert = array_filter(
            explode("\n", file_get_contents($this->archivo)),
            fn ($l) => str_contains($l, 'línea 1')
        );

        $this->assertCount(1, $lineasInsert);
        $this->assertStringContainsString('línea 1\\nlínea 2;\\r\\nfin;', reset($lineasInsert));
    }

    public function test_resolver_ruta_usa_carpeta_seeders_y_agrega_extension(): void
    {
        $this->assertSame(database_path('seeders/demo.sql'), ExportarDemoCommand::resolverRuta('demo.sql'));
        $this->assertSame(database_path('seeders/antes_nomina.sql'), ExportarDemoCommand::resolverRuta('antes_nomina'));
        $this->assertSame('/tmp/copia.sql', ExportarDemoCommand::resolverRuta('/tmp/copia.sql'));
    }

    // ─── demo:importar ────────────────────────────────────────────────────────

    public function test_importar_restaura_exactamente_el_estado_exportado(): void
    {
        $tablasAntes = $this->contarTablas();
        $migracionesAntes = DB::table('migrations')->count();

        $this->artisan('demo:exportar', ['--file' => $this->archivo])->assertSuccessful();

        // Se altera la base después de exportar: se borra un registro y se modifica otro
        DB::table('demo_prueba')->where('id', 1)->delete();
        DB::table('demo_prueba')->where('id', 2)->update(['texto' => 'modificado']);

        $this->artisan('demo:importar', ['--file' => $this->archivo])
            ->assertSuccessful();

        $this->assertSame($tablasAntes, $this->contarTablas());
        $this->assertSame($migracionesAntes, DB::table('migrations')->count());

        $restauradas = DB::table('demo_prueba')->orderBy('id')->get()
            ->map(fn ($f) => (array) $f)->all();
        $this->assertSame($this->filas, $restauradas);
    }

    public function test_importar_elimina_tablas_que_no_estaban_en_el_archivo(): void
    {
        $this->artisan('demo:exportar', ['--file' => $this->archivo])->assertSuccessful();

        Schema::create('demo_tabla_nueva', fn (Blueprint $table) => $table->id());

        $this->artisan('demo:importar', ['--file' => $this->archivo])->assertSuccessful();

        $this->assertFalse(Schema::hasTable('demo_tabla_nueva'));
        $this->assertTrue(Schema::hasTable('demo_prueba'));
    }

    public function test_importar_falla_si_el_archivo_no_existe(): void
    {
        $this->artisan('demo:importar', ['--file' => '/tmp/no_existe_' . uniqid() . '.sql'])
            ->expectsOutputToContain('No existe o no se puede leer el archivo')
            ->assertFailed();

        $this->assertTrue(Schema::hasTable('demo_prueba'));
    }

    public function test_importar_en_produccion_sin_confirmar_no_modifica_la_base(): void
    {
        $this->artisan('demo:exportar', ['--file' => $this->archivo])->assertSuccessful();
        DB::table('demo_prueba')->where('id', 1)->delete();

        $this->app['env'] = 'production';

        $this->artisan('demo:importar', ['--file' => $this->archivo])
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();

        $this->assertSame(2, DB::table('demo_prueba')->count());
    }

    public function test_importar_en_produccion_con_force_no_pide_confirmacion(): void
    {
        $this->artisan('demo:exportar', ['--file' => $this->archivo])->assertSuccessful();
        DB::table('demo_prueba')->where('id', 1)->delete();

        $this->app['env'] = 'production';

        $this->artisan('demo:importar', ['--file' => $this->archivo, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(3, DB::table('demo_prueba')->count());
    }

    public function test_importar_solo_vacia_se_niega_si_la_base_tiene_registros(): void
    {
        $this->artisan('demo:exportar', ['--file' => $this->archivo])->assertSuccessful();
        DB::table('demo_prueba')->where('id', 1)->delete();

        $this->artisan('demo:importar', ['--file' => $this->archivo, '--solo-vacia' => true])
            ->expectsOutputToContain("la tabla 'demo_prueba' tiene registros")
            ->assertFailed();

        $this->assertSame(2, DB::table('demo_prueba')->count());
    }

    public function test_importar_solo_vacia_ignora_la_tabla_migrations(): void
    {
        $this->artisan('demo:exportar', ['--file' => $this->archivo])->assertSuccessful();
        DB::table('demo_prueba')->delete();

        $this->artisan('demo:importar', ['--file' => $this->archivo, '--solo-vacia' => true])
            ->assertSuccessful();

        $this->assertSame(3, DB::table('demo_prueba')->count());
    }

    /**
     * Cantidad de tablas base en la base de datos de pruebas.
     */
    private function contarTablas(): int
    {
        return count(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
    }
}
