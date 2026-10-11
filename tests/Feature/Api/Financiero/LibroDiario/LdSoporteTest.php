<?php

namespace Tests\Feature\Api\Financiero\LibroDiario;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdSoporte;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\User;
use App\Services\ArchivoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas de archivos de soporte: carga, descarga protegida y eliminación
 * solo mientras el turno siga abierto.
 */
class LdSoporteTest extends TestCase
{
    use PreparaLibroDiario, RefreshDatabase;

    private User $cajero;

    private LdMovimiento $movimiento;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(LdSoporte::DISCO);
        $this->prepararLibroDiario();
        $this->cajero = $this->usuarioCon(['fin_libroDiario', 'fin_ldRegistroCrear', 'fin_ldRegistroEditar']);
        $turno = LdTurnoCaja::factory()->create(['cajero_id' => $this->cajero->id, 'sede_id' => $this->sede->id]);
        $this->movimiento = LdMovimiento::factory()->create(['turno_caja_id' => $turno->id]);
    }

    private function subir(array $override = [])
    {
        return $this->actingAs($this->cajero)->post(route('libro-diario.soportes.store'), array_merge([
            'soportable_tipo' => 'movimiento',
            'soportable_id' => $this->movimiento->id,
            'tipo' => 'factura',
            'archivo' => UploadedFile::fake()->create('factura.pdf', 200, 'application/pdf'),
        ], $override), ['Accept' => 'application/json']);
    }

    /** @test */
    public function carga_descarga_y_elimina_soporte(): void
    {
        $id = $this->subir()
            ->assertCreated()
            ->assertJsonPath('data.nombre_original', 'factura.pdf')
            ->assertJsonPath('data.tipo', 'factura')
            ->json('data.id');

        $soporte = LdSoporte::find($id);
        $this->assertMatchesRegularExpression('#^libro-diario/'.$this->cajero->id.'_\d{8}_\d{6}\.pdf$#', $soporte->archivo_path);
        $this->assertSame('application/pdf', $soporte->mime_type);
        $this->assertGreaterThan(0, $soporte->tamano_bytes);
        Storage::disk(LdSoporte::DISCO)->assertExists($soporte->archivo_path);

        $this->actingAs($this->cajero)->get(route('libro-diario.soportes.archivo', $id))->assertOk();

        $this->actingAs($this->cajero)->deleteJson(route('libro-diario.soportes.destroy', $id))->assertOk();
        Storage::disk(LdSoporte::DISCO)->assertMissing($soporte->archivo_path);
        $this->assertDatabaseMissing('ld_soportes', ['id' => $id]);
    }

    /** @test */
    public function acepta_soportes_de_hasta_10_mb(): void
    {
        $this->subir(['archivo' => UploadedFile::fake()->create('firmado.pdf', 9 * 1024, 'application/pdf')])
            ->assertCreated();

        $this->subir(['archivo' => UploadedFile::fake()->create('enorme.pdf', ArchivoService::MAX_KB + 1, 'application/pdf')])
            ->assertJsonValidationErrors('archivo');
    }

    /** @test */
    public function rechaza_archivo_no_permitido_y_registro_inexistente(): void
    {
        $this->subir(['archivo' => UploadedFile::fake()->create('script.exe', 10)])
            ->assertJsonValidationErrors('archivo');

        $this->subir(['soportable_id' => 999999])
            ->assertJsonValidationErrors('soportable_id');
    }

    /** @test */
    public function no_elimina_soporte_de_turno_cerrado_pero_si_permite_cargarlo(): void
    {
        $this->movimiento->turnoCaja->update(['status' => LdTurnoCaja::STATUS_CERRADO]);

        $id = $this->subir()->assertCreated()->json('data.id');

        $this->actingAs($this->cajero)->deleteJson(route('libro-diario.soportes.destroy', $id))->assertUnprocessable();
    }

    /** @test */
    public function no_carga_ni_descarga_soportes_de_otra_sede(): void
    {
        $ajeno = LdMovimiento::factory()->create(['turno_caja_id' => LdTurnoCaja::factory()->create(['sede_id' => Sede::factory()])]);

        $this->subir(['soportable_id' => $ajeno->id])->assertForbidden();

        $soporte = $ajeno->soportes()->create(['tipo' => 'factura', 'nombre_original' => 'x.pdf', 'archivo_path' => 'x.pdf']);
        $this->actingAs($this->cajero)->get(route('libro-diario.soportes.archivo', $soporte->id))->assertForbidden();
    }
}
