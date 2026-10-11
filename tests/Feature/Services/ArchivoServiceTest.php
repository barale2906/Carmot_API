<?php

namespace Tests\Feature\Services;

use App\Services\ArchivoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas de la convención de guardado de archivos: carpeta base única y
 * nombre {idUsuario}_{fecha}_{hora}.{ext}, sin duplicados.
 */
class ArchivoServiceTest extends TestCase
{
    private ArchivoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-10-10 17:09:28');
        $this->servicio = new ArchivoService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @test */
    public function guarda_en_la_carpeta_base_con_usuario_fecha_y_hora(): void
    {
        $ruta = $this->servicio->guardar(UploadedFile::fake()->image('Captura.PNG'), 'local', ArchivoService::CARPETA_LIBRO_DIARIO, 12);

        $this->assertSame('libro-diario/12_20261010_170928.png', $ruta);
        Storage::disk('local')->assertExists($ruta);
    }

    /** @test */
    public function no_sobrescribe_si_el_mismo_usuario_sube_dos_archivos_en_el_mismo_segundo(): void
    {
        $primera = $this->servicio->guardar(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'local', ArchivoService::CARPETA_LIBRO_DIARIO, 12);
        $segunda = $this->servicio->guardar(UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'), 'local', ArchivoService::CARPETA_LIBRO_DIARIO, 12);

        $this->assertSame('libro-diario/12_20261010_170928.pdf', $primera);
        $this->assertSame('libro-diario/12_20261010_170928_2.pdf', $segunda);
        Storage::disk('local')->assertExists([$primera, $segunda]);
    }
}
