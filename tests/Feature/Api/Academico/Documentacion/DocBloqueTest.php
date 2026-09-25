<?php

namespace Tests\Feature\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\User;
use App\Services\Academico\Documentacion\DocGeneracionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas de los bloques de consulta que se imprimen como tabla en un documento.
 */
class DocBloqueTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private DocTipoDocumento $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'aca_docPlantillas'         => 'ver plantillas',
            'aca_docPlantillaEditar'    => 'editar plantillas',
            'aca_docPlantillaClonar'    => 'clonar plantillas',
            'aca_documentos'            => 'ver documentos',
            'aca_documentoGenerar'      => 'generar documentos',
        ];

        foreach ($permisos as $nombre => $descripcion) {
            Permission::create(['name' => $nombre, 'descripcion' => $descripcion]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(array_keys($permisos));

        $this->tipo = DocTipoDocumento::factory()->atadoAMatricula()->create([
            'nombre'         => 'Certificación de cartera',
            'prefijo_numero' => 'CERT',
        ]);
        $this->tipo->variables()->create(['variable_key' => 'estudiante.name']);
    }

    /**
     * Crea una matrícula con tres cuotas de cartera.
     *
     * @return Matricula
     */
    private function matriculaConCartera(): Matricula
    {
        $matricula = Matricula::factory()->create([
            'fecha_matricula' => '2024-06-15',
            'monto'           => 900000,
        ]);

        foreach ([1, 2, 3] as $numero) {
            Cartera::create([
                'matricula_id'      => $matricula->id,
                'sede_id'           => $matricula->sede_id,
                'estudiante_id'     => $matricula->estudiante_id,
                'numero_cuota'      => $numero,
                'valor'             => 300000,
                'saldo'             => $numero === 1 ? 0 : 300000,
                'abono'             => $numero === 1 ? 300000 : 0,
                'descuento'         => 0,
                'mora_acumulada'    => 0,
                'fecha_vencimiento' => '2024-0' . (6 + $numero) . '-15',
                'status'            => $numero === 1 ? 2 : 0,
            ]);
        }

        return $matricula;
    }

    /**
     * Crea una versión activa con el contenido indicado.
     *
     * @param string $contenido
     * @return DocPlantilla
     */
    private function plantillaActiva(string $contenido): DocPlantilla
    {
        return DocPlantilla::factory()->activa('2020-01-01')->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => $contenido,
        ]);
    }

    /** @test */
    public function el_catalogo_expone_los_bloques_con_sus_columnas(): void
    {
        $plantilla = $this->plantillaActiva('<p>Contenido</p>');

        $response = $this->actingAs($this->usuario)
            ->getJson(route('documentacion.plantillas.bloques', $plantilla));

        $response->assertOk();

        $catalogo = collect($response->json('data'));
        $cartera  = $catalogo->firstWhere('clave', 'estado_cartera');

        $this->assertNotNull($catalogo->firstWhere('clave', 'sabana_notas'));
        $this->assertNotNull($catalogo->firstWhere('clave', 'recibos_pago'));
        $this->assertSame('{{ bloque.estado_cartera }}', $cartera['marcador']);
        $this->assertFalse($cartera['configurado']);
        $this->assertContains('saldo', array_column($cartera['columnas'], 'clave'));
    }

    /** @test */
    public function imprime_el_bloque_con_las_columnas_elegidas_y_su_resumen(): void
    {
        $plantilla = $this->plantillaActiva('<p>Estado:</p>{{ bloque.estado_cartera }}');
        $plantilla->bloques()->create([
            'bloque_key'      => 'estado_cartera',
            'columnas'        => ['numero_cuota', 'valor', 'saldo'],
            'titulos'         => ['saldo' => 'Pendiente'],
            'mostrar_resumen' => true,
        ]);

        $matricula = $this->matriculaConCartera();

        $documento = app(DocGeneracionService::class)->generar(
            $this->tipo,
            $plantilla,
            $matricula,
            Carbon::parse('2024-06-15'),
            $this->usuario
        );

        $contenido = $documento->contenido_renderizado;

        $this->assertStringContainsString('<table class="bloque-tabla">', $contenido);
        $this->assertStringContainsString('Pendiente', $contenido);
        $this->assertStringContainsString('$ 300.000', $contenido);
        $this->assertStringContainsString('Totales', $contenido);
        $this->assertStringContainsString('$ 900.000', $contenido);

        // Columnas no elegidas no se imprimen.
        $this->assertStringNotContainsString('Mora', $contenido);
        $this->assertStringNotContainsString('{{', $contenido);
    }

    /** @test */
    public function sin_configuracion_el_bloque_imprime_todas_sus_columnas(): void
    {
        $plantilla = $this->plantillaActiva('{{ bloque.estado_cartera }}');
        $matricula = $this->matriculaConCartera();

        $documento = app(DocGeneracionService::class)->generar(
            $this->tipo,
            $plantilla,
            $matricula,
            Carbon::parse('2024-06-15'),
            $this->usuario
        );

        $this->assertStringContainsString('Mora', $documento->contenido_renderizado);
        $this->assertStringContainsString('Vencimiento', $documento->contenido_renderizado);
    }

    /** @test */
    public function un_bloque_sin_datos_imprime_la_tabla_vacia(): void
    {
        $plantilla = $this->plantillaActiva('{{ bloque.recibos_pago }}');
        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $documento = app(DocGeneracionService::class)->generar(
            $this->tipo,
            $plantilla,
            $matricula,
            Carbon::parse('2024-06-15'),
            $this->usuario
        );

        $this->assertStringContainsString('Sin registros.', $documento->contenido_renderizado);
    }

    /** @test */
    public function guarda_la_configuracion_de_bloques_de_una_version_en_proceso(): void
    {
        $plantilla = DocPlantilla::factory()->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => '{{ bloque.estado_cartera }}',
        ]);

        $response = $this->actingAs($this->usuario)
            ->putJson(route('documentacion.plantillas.bloques.sync', $plantilla), [
                'bloques' => [
                    [
                        'bloque'          => 'estado_cartera',
                        'columnas'        => ['numero_cuota', 'saldo'],
                        'titulos'         => ['saldo' => 'Pendiente'],
                        'mostrar_resumen' => false,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.bloques.0.bloque', 'estado_cartera')
            ->assertJsonPath('data.bloques.0.mostrar_resumen', false);

        $this->assertDatabaseHas('doc_plantilla_bloques', [
            'plantilla_id' => $plantilla->id,
            'bloque_key'   => 'estado_cartera',
        ]);
    }

    /** @test */
    public function rechaza_una_columna_que_no_existe_en_el_bloque(): void
    {
        $plantilla = DocPlantilla::factory()->create(['tipo_documento_id' => $this->tipo->id]);

        $this->actingAs($this->usuario)
            ->putJson(route('documentacion.plantillas.bloques.sync', $plantilla), [
                'bloques' => [
                    ['bloque' => 'estado_cartera', 'columnas' => ['numero_cuota', 'columna_inventada']],
                ],
            ])
            ->assertJsonValidationErrors(['bloques.0.columnas']);
    }

    /** @test */
    public function rechaza_un_bloque_que_no_aplica_a_la_entidad(): void
    {
        $plantilla = DocPlantilla::factory()->create(['tipo_documento_id' => $this->tipo->id]);

        $this->actingAs($this->usuario)
            ->putJson(route('documentacion.plantillas.bloques.sync', $plantilla), [
                'bloques' => [
                    ['bloque' => 'bloque_inventado', 'columnas' => ['algo']],
                ],
            ])
            ->assertJsonValidationErrors(['bloques.0.bloque']);
    }

    /** @test */
    public function no_permite_configurar_bloques_de_una_version_aprobada(): void
    {
        $plantilla = DocPlantilla::factory()->aprobada()->create(['tipo_documento_id' => $this->tipo->id]);

        $this->actingAs($this->usuario)
            ->putJson(route('documentacion.plantillas.bloques.sync', $plantilla), [
                'bloques' => [
                    ['bloque' => 'estado_cartera', 'columnas' => ['numero_cuota']],
                ],
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function rechaza_contenido_con_un_bloque_inexistente(): void
    {
        Permission::create(['name' => 'aca_docPlantillaCrear', 'descripcion' => 'crear plantillas']);
        $this->usuario->givePermissionTo('aca_docPlantillaCrear');

        $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.store'), [
                'tipo_documento_id' => $this->tipo->id,
                'nombre'            => 'Versión con bloque inválido',
                'contenido_html'    => '<p>{{ bloque.no_existe }}</p>',
            ])
            ->assertJsonValidationErrors(['contenido_html']);
    }

    /** @test */
    public function clonar_una_version_copia_la_configuracion_de_bloques(): void
    {
        $plantilla = $this->plantillaActiva('{{ bloque.estado_cartera }}');
        $plantilla->bloques()->create([
            'bloque_key'      => 'estado_cartera',
            'columnas'        => ['numero_cuota', 'saldo'],
            'mostrar_resumen' => false,
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.clonar', $plantilla), [
                'nombre' => 'Versión clonada',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('doc_plantilla_bloques', [
            'plantilla_id'    => $response->json('data.id'),
            'bloque_key'      => 'estado_cartera',
            'mostrar_resumen' => false,
        ]);
    }

    /** @test */
    public function previsualiza_la_version_con_datos_reales_sin_emitir_documento(): void
    {
        $plantilla = $this->plantillaActiva('<p>{{ estudiante.name }}</p>{{ bloque.estado_cartera }}');
        $matricula = $this->matriculaConCartera();

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.previsualizar', $plantilla), [
                'entidad_id' => $matricula->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.plantilla_id', $plantilla->id);

        $contenido = $response->json('data.contenido');

        $this->assertStringContainsString($matricula->estudiante->name, $contenido);
        $this->assertStringContainsString('<table class="bloque-tabla">', $contenido);
        $this->assertDatabaseCount('doc_documentos', 0);
    }

    /** @test */
    public function la_previsualizacion_exige_el_registro_asociado(): void
    {
        $plantilla = $this->plantillaActiva('<p>Contenido</p>');

        $this->actingAs($this->usuario)
            ->postJson(route('documentacion.plantillas.previsualizar', $plantilla), [])
            ->assertJsonValidationErrors(['entidad_id']);
    }
}
