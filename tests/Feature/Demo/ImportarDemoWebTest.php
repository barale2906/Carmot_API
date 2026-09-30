<?php

namespace Tests\Feature\Demo;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pruebas Feature de la ruta oculta /_demo/{token} para importar demo.sql.
 *
 * No usa RefreshDatabase por la misma razón que DemoBaseDatosTest: la importación
 * ejecuta DDL con commit implícito. El caso exitoso vacía la base de pruebas y la
 * repuebla desde un volcado de sí misma, dejándola igual que al inicio.
 */
class ImportarDemoWebTest extends TestCase
{
    private const TOKEN = 'token-secreto-de-prueba-0123456789abcdef';

    private string $volcado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');
        config(['demo.import_token' => self::TOKEN]);

        Schema::dropIfExists('demo_prueba');
        Schema::create('demo_prueba', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });

        $this->volcado = sys_get_temp_dir() . '/demo_web_' . uniqid() . '.sql';
        $this->artisan('demo:exportar', ['--file' => $this->volcado])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('demo_prueba');
        @unlink($this->volcado);

        parent::tearDown();
    }

    private function url(string $token = self::TOKEN): string
    {
        return "/_demo/{$token}";
    }

    private function archivoSql(string $nombre = 'demo.sql'): UploadedFile
    {
        return new UploadedFile($this->volcado, $nombre, 'application/sql', null, true);
    }

    // ─── acceso ───────────────────────────────────────────────────────────────

    public function test_formulario_visible_con_token_correcto(): void
    {
        $this->get($this->url())
            ->assertOk()
            ->assertSee('Importar demo.sql');
    }

    public function test_token_incorrecto_responde_404(): void
    {
        $this->get($this->url('token-incorrecto-0123456789abcdef0123'))->assertNotFound();
        $this->postJson($this->url('token-incorrecto-0123456789abcdef0123'), ['archivo' => $this->archivoSql()])
            ->assertNotFound();
    }

    public function test_ruta_deshabilitada_si_no_hay_token_configurado(): void
    {
        config(['demo.import_token' => null]);

        $this->get($this->url())->assertNotFound();
        $this->get('/_demo/')->assertNotFound();
    }

    public function test_ruta_deshabilitada_si_el_token_configurado_es_corto(): void
    {
        config(['demo.import_token' => 'corto']);

        $this->get($this->url('corto'))->assertNotFound();
    }

    // ─── validación ───────────────────────────────────────────────────────────

    public function test_archivo_es_obligatorio(): void
    {
        $this->postJson($this->url(), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }

    public function test_archivo_debe_tener_extension_sql(): void
    {
        $this->postJson($this->url(), ['archivo' => $this->archivoSql('demo.txt')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }

    // ─── importación ──────────────────────────────────────────────────────────

    public function test_se_niega_a_importar_si_la_base_tiene_registros(): void
    {
        DB::table('demo_prueba')->insert(['nombre' => 'dato real']);

        $this->postJson($this->url(), ['archivo' => $this->archivoSql()])
            ->assertStatus(409)
            ->assertJsonPath('message', 'No se realizó la importación.');

        $this->assertSame('dato real', DB::table('demo_prueba')->value('nombre'));
    }

    public function test_importa_sobre_base_vacia_y_ejecuta_migraciones(): void
    {
        $tablasAntes = $this->contarTablas();
        Schema::dropAllTables();

        $this->postJson($this->url(), ['archivo' => $this->archivoSql()])
            ->assertOk()
            ->assertJsonPath('message', 'Base de datos poblada correctamente con el demo.');

        $this->assertSame($tablasAntes, $this->contarTablas());
        $this->assertTrue(Schema::hasTable('demo_prueba'));
        $this->assertSame([], glob(storage_path('app/demo/importacion_*.sql')));
    }

    public function test_formulario_html_muestra_el_resultado(): void
    {
        $this->post($this->url(), ['archivo' => $this->archivoSql()])
            ->assertOk()
            ->assertSee('Base de datos poblada correctamente con el demo.');
    }

    /**
     * Cantidad de tablas base en la base de datos de pruebas.
     */
    private function contarTablas(): int
    {
        return count(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
    }
}
