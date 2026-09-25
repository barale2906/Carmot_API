<?php

namespace Tests\Feature\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del CRUD y del flujo de estados de las versiones de plantilla.
 */
class DocPlantillaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private DocTipoDocumento $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'aca_docPlantillas'          => 'ver plantillas',
            'aca_docPlantillaCrear'      => 'crear plantillas',
            'aca_docPlantillaEditar'     => 'editar plantillas',
            'aca_docPlantillaAprobar'    => 'aprobar y activar plantillas',
            'aca_docPlantillaClonar'     => 'clonar plantillas',
            'aca_docPlantillaInactivar'  => 'inactivar plantillas',
        ];

        foreach ($permisos as $nombre => $descripcion) {
            Permission::create(['name' => $nombre, 'descripcion' => $descripcion]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(array_keys($permisos));

        $this->tipo = DocTipoDocumento::factory()->atadoAMatricula()->create();
        $this->tipo->variables()->create(['variable_key' => 'estudiante.name']);
        $this->tipo->variables()->create(['variable_key' => 'monto_letras']);
    }

    /** @test */
    public function crea_una_version_en_proceso_con_consecutivo_automatico(): void
    {
        DocPlantilla::factory()->create(['tipo_documento_id' => $this->tipo->id, 'version' => 4]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.store'), [
                'tipo_documento_id' => $this->tipo->id,
                'nombre'            => 'Contrato 2026',
                'contenido_html'    => '<p>Yo, {{ estudiante.name }}, me obligo a pagar {{ monto_letras }}.</p>',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.version', 5)
            ->assertJsonPath('data.status', DocPlantilla::STATUS_EN_PROCESO)
            ->assertJsonPath('data.status_text', 'En Proceso');

        $this->assertDatabaseHas('doc_plantillas', [
            'tipo_documento_id' => $this->tipo->id,
            'version'           => 5,
            'creado_por'        => $this->usuario->id,
        ]);
    }

    /** @test */
    public function rechaza_contenido_con_variables_no_habilitadas(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.store'), [
                'tipo_documento_id' => $this->tipo->id,
                'nombre'            => 'Contrato inválido',
                'contenido_html'    => '<p>{{ estudiante.name }} — {{ curso.nombre }}</p>',
            ]);

        $response->assertJsonValidationErrors(['contenido_html']);
    }

    /** @test */
    public function solo_permite_editar_versiones_en_proceso(): void
    {
        $plantilla = DocPlantilla::factory()->aprobada()->create(['tipo_documento_id' => $this->tipo->id]);

        $response = $this->actingAs($this->usuario)
            ->putJson(route('documentacion.plantillas.update', $plantilla), [
                'nombre' => 'Otro nombre',
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function aprueba_una_version_en_proceso(): void
    {
        $plantilla = DocPlantilla::factory()->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => '<p>{{ estudiante.name }}</p>',
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.aprobar', $plantilla));

        $response->assertOk()
            ->assertJsonPath('data.status', DocPlantilla::STATUS_APROBADA);
    }

    /** @test */
    public function rechaza_aprobar_si_una_variable_dejo_de_estar_habilitada(): void
    {
        $plantilla = DocPlantilla::factory()->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => '<p>{{ estudiante.name }} {{ monto_letras }}</p>',
        ]);

        $this->tipo->variables()->where('variable_key', 'monto_letras')->delete();

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.aprobar', $plantilla));

        $response->assertStatus(422)
            ->assertJsonPath('variables.0', 'monto_letras');
    }

    /** @test */
    public function activar_una_version_cierra_la_vigencia_de_la_anterior(): void
    {
        $anterior = DocPlantilla::factory()->activa('2024-01-01')->create([
            'tipo_documento_id' => $this->tipo->id,
        ]);
        $nueva = DocPlantilla::factory()->aprobada()->create(['tipo_documento_id' => $this->tipo->id]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.activar', $nueva), [
                'fecha_inicio' => '2026-01-01',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', DocPlantilla::STATUS_ACTIVA)
            ->assertJsonPath('data.fecha_inicio', '2026-01-01')
            ->assertJsonPath('data.fecha_fin', null);

        $this->assertDatabaseHas('doc_plantillas', [
            'id'        => $anterior->id,
            'status'    => DocPlantilla::STATUS_INACTIVA,
            'fecha_fin' => '2025-12-31',
        ]);
    }

    /** @test */
    public function rechaza_activar_con_fecha_anterior_a_la_version_vigente(): void
    {
        DocPlantilla::factory()->activa('2026-01-01')->create(['tipo_documento_id' => $this->tipo->id]);
        $nueva = DocPlantilla::factory()->aprobada()->create(['tipo_documento_id' => $this->tipo->id]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.activar', $nueva), [
                'fecha_inicio' => '2025-06-01',
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function rechaza_activar_una_version_no_aprobada(): void
    {
        $plantilla = DocPlantilla::factory()->create(['tipo_documento_id' => $this->tipo->id]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.activar', $plantilla));

        $response->assertStatus(422);
    }

    /** @test */
    public function inactivar_cierra_la_vigencia_de_la_version_activa(): void
    {
        $plantilla = DocPlantilla::factory()->activa('2024-01-01')->create([
            'tipo_documento_id' => $this->tipo->id,
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.inactivar', $plantilla));

        $response->assertOk()
            ->assertJsonPath('data.status', DocPlantilla::STATUS_INACTIVA)
            ->assertJsonPath('data.fecha_fin', now()->toDateString());
    }

    /** @test */
    public function clona_una_version_en_un_borrador_nuevo(): void
    {
        $plantilla = DocPlantilla::factory()->activa('2024-01-01')->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => '<p>{{ estudiante.name }}</p>',
            'version'           => 1,
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.clonar', $plantilla), [
                'nombre' => 'Contrato 2027',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.status', DocPlantilla::STATUS_EN_PROCESO)
            ->assertJsonPath('data.version_anterior_id', $plantilla->id)
            ->assertJsonPath('data.contenido_html', '<p>{{ estudiante.name }}</p>');

        $this->assertDatabaseHas('doc_plantillas', [
            'id'     => $plantilla->id,
            'status' => DocPlantilla::STATUS_ACTIVA,
        ]);
    }

    /** @test */
    public function no_permite_eliminar_la_version_activa(): void
    {
        $plantilla = DocPlantilla::factory()->activa('2024-01-01')->create([
            'tipo_documento_id' => $this->tipo->id,
        ]);

        $response = $this->actingAs($this->usuario)
            ->deleteJson(route('documentacion.plantillas.destroy', $plantilla));

        $response->assertStatus(422);
        $this->assertDatabaseHas('doc_plantillas', ['id' => $plantilla->id, 'deleted_at' => null]);
    }

    /** @test */
    public function el_listado_omite_el_contenido_y_el_detalle_lo_incluye(): void
    {
        $plantilla = DocPlantilla::factory()->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => '<p>Contenido largo</p>',
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('documentacion.plantillas.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.contenido_html');

        $this->actingAs($this->usuario)
            ->getJson(route('documentacion.plantillas.show', $plantilla))
            ->assertOk()
            ->assertJsonPath('data.contenido_html', '<p>Contenido largo</p>');
    }

    /** @test */
    public function deniega_la_aprobacion_sin_permiso(): void
    {
        $plantilla = DocPlantilla::factory()->create(['tipo_documento_id' => $this->tipo->id]);
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('documentacion.plantillas.aprobar', $plantilla))
            ->assertForbidden();
    }

    /** @test */
    public function no_permite_eliminar_un_tipo_con_plantillas(): void
    {
        Permission::create(['name' => 'aca_docTipoInactivar', 'descripcion' => 'inactivar tipos']);
        $this->usuario->givePermissionTo('aca_docTipoInactivar');

        DocPlantilla::factory()->create(['tipo_documento_id' => $this->tipo->id]);

        $this->actingAs($this->usuario)
            ->deleteJson(route('documentacion.tipos-documento.destroy', $this->tipo))
            ->assertStatus(422);
    }
}
