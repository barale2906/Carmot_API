<?php

namespace Tests\Feature\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del CRUD de tipos de documento y de la selección de variables habilitadas.
 */
class DocTipoDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'aca_docTipos'         => 'ver tipos de documento',
            'aca_docTipoCrear'     => 'crear tipos de documento',
            'aca_docTipoEditar'    => 'editar tipos de documento',
            'aca_docTipoVariables' => 'definir variables habilitadas',
            'aca_docTipoInactivar' => 'inactivar tipos de documento',
        ];

        foreach ($permisos as $nombre => $descripcion) {
            Permission::create(['name' => $nombre, 'descripcion' => $descripcion]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(array_keys($permisos));
    }

    /** @test */
    public function lista_los_tipos_de_documento(): void
    {
        DocTipoDocumento::factory()->count(3)->create();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('documentacion.tipos-documento.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'codigo', 'nombre', 'status', 'status_text']], 'meta']);
    }

    /** @test */
    public function crea_un_tipo_de_documento_con_sus_variables(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.tipos-documento.store'), [
                'codigo'                 => 'CONTRATO',
                'nombre'                 => 'Contrato de matrícula',
                'entidad_type'           => Matricula::class,
                'se_ata_fecha'           => true,
                'campo_fecha_referencia' => 'fecha_matricula',
                'prefijo_numero'         => 'CONT',
                'variables'              => ['estudiante.name', 'monto_letras', 'documento.fecha_larga'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.codigo', 'CONTRATO')
            ->assertJsonPath('data.se_ata_fecha', true)
            ->assertJsonPath('data.entidad_nombre', 'Matrícula')
            ->assertJsonCount(3, 'data.variables');

        $this->assertDatabaseHas('doc_tipos_documento', ['codigo' => 'CONTRATO']);
        $this->assertDatabaseHas('doc_tipo_documento_variables', ['variable_key' => 'monto_letras']);
    }

    /** @test */
    public function rechaza_la_creacion_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $response = $this->actingAs($sinPermiso)
            ->postJson(route('documentacion.tipos-documento.store'), [
                'codigo'         => 'CARTA',
                'nombre'         => 'Carta',
                'prefijo_numero' => 'CAR',
            ]);

        $response->assertForbidden();
    }

    /** @test */
    public function valida_los_campos_requeridos(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.tipos-documento.store'), []);

        $response->assertJsonValidationErrors(['codigo', 'nombre', 'prefijo_numero']);
    }

    /** @test */
    public function rechaza_un_codigo_duplicado(): void
    {
        DocTipoDocumento::factory()->create(['codigo' => 'PAGARE']);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.tipos-documento.store'), [
                'codigo'         => 'PAGARE',
                'nombre'         => 'Otro pagaré',
                'prefijo_numero' => 'PAG',
            ]);

        $response->assertJsonValidationErrors(['codigo']);
    }

    /** @test */
    public function rechaza_un_prefijo_de_numeracion_duplicado(): void
    {
        DocTipoDocumento::factory()->create(['prefijo_numero' => 'CONT']);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.tipos-documento.store'), [
                'codigo'         => 'CONTRATO_2',
                'nombre'         => 'Otro contrato',
                'prefijo_numero' => 'CONT',
            ]);

        $response->assertJsonValidationErrors(['prefijo_numero']);
    }

    /** @test */
    public function rechaza_variables_fuera_del_catalogo_de_la_entidad(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.tipos-documento.store'), [
                'codigo'         => 'CERTIFICADO',
                'nombre'         => 'Certificado',
                'entidad_type'   => Matricula::class,
                'prefijo_numero' => 'CERT',
                'variables'      => ['estudiante.name', 'variable.inventada'],
            ]);

        $response->assertJsonValidationErrors(['variables']);
    }

    /** @test */
    public function rechaza_un_campo_de_fecha_invalido_para_la_entidad(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.tipos-documento.store'), [
                'codigo'                 => 'HOJA',
                'nombre'                 => 'Hoja de matrícula',
                'entidad_type'           => Matricula::class,
                'se_ata_fecha'           => true,
                'campo_fecha_referencia' => 'fecha_inexistente',
                'prefijo_numero'         => 'HM',
            ]);

        $response->assertJsonValidationErrors(['campo_fecha_referencia']);
    }

    /** @test */
    public function actualiza_un_tipo_de_documento(): void
    {
        $tipo = DocTipoDocumento::factory()->create(['nombre' => 'Nombre viejo']);

        $response = $this->actingAs($this->usuario)
            ->putJson(route('documentacion.tipos-documento.update', $tipo), [
                'nombre' => 'Nombre nuevo',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre nuevo');

        $this->assertDatabaseHas('doc_tipos_documento', [
            'id'     => $tipo->id,
            'nombre' => 'Nombre nuevo',
        ]);
    }

    /** @test */
    public function lista_las_variables_disponibles_marcando_las_habilitadas(): void
    {
        $tipo = DocTipoDocumento::factory()->atadoAMatricula()->create();
        $tipo->variables()->create(['variable_key' => 'estudiante.name']);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('documentacion.tipos-documento.variables', $tipo));

        $response->assertOk();

        $catalogo = collect($response->json('data'));

        $this->assertTrue($catalogo->firstWhere('clave', 'estudiante.name')['habilitada']);
        $this->assertFalse($catalogo->firstWhere('clave', 'curso.nombre')['habilitada']);
        $this->assertNotNull($catalogo->firstWhere('clave', 'instituto.nombre'));
    }

    /** @test */
    public function sincroniza_las_variables_habilitadas_reemplazando_la_seleccion(): void
    {
        $tipo = DocTipoDocumento::factory()->atadoAMatricula()->create();
        $tipo->variables()->create(['variable_key' => 'estudiante.name']);

        $response = $this->actingAs($this->usuario)
            ->putJson(route('documentacion.tipos-documento.variables.sync', $tipo), [
                'variables' => ['curso.nombre', 'monto'],
            ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.variables');

        $this->assertDatabaseMissing('doc_tipo_documento_variables', [
            'tipo_documento_id' => $tipo->id,
            'variable_key'      => 'estudiante.name',
        ]);
        $this->assertDatabaseHas('doc_tipo_documento_variables', [
            'tipo_documento_id' => $tipo->id,
            'variable_key'      => 'curso.nombre',
        ]);
    }

    /** @test */
    public function elimina_lista_y_restaura_un_tipo_de_documento(): void
    {
        $tipo = DocTipoDocumento::factory()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('documentacion.tipos-documento.destroy', $tipo))
            ->assertOk();

        $this->assertSoftDeleted('doc_tipos_documento', ['id' => $tipo->id]);

        $this->actingAs($this->usuario)
            ->getJson(route('documentacion.tipos-documento.trashed'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->usuario)
            ->postJson(route('documentacion.tipos-documento.restore', $tipo->id))
            ->assertOk();

        $this->assertDatabaseHas('doc_tipos_documento', [
            'id'         => $tipo->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function los_filtros_exponen_estados_y_entidades_disponibles(): void
    {
        $response = $this->actingAs($this->usuario)
            ->getJson(route('documentacion.tipos-documento.filters'));

        $response->assertOk()
            ->assertJsonStructure(['data' => ['status_options', 'entidades' => [['entidad_type', 'nombre', 'campos_fecha']]]]);

        $entidades = collect($response->json('data.entidades'))->pluck('entidad_type');

        $this->assertTrue($entidades->contains(Matricula::class));
    }
}
