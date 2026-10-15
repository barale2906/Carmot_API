<?php

namespace Tests\Feature\Api\Crm;

use App\Models\Academico\Curso;
use App\Models\Crm\Referido;
use App\Models\Crm\Seguimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas Feature del CRUD de referidos (leads) del CRM.
 */
class ReferidoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Curso $curso;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['crm_referidos', 'crm_referidoCrear', 'crm_referidoEditar', 'crm_referidoInactivar'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['crm_referidos', 'crm_referidoCrear', 'crm_referidoEditar', 'crm_referidoInactivar']);
        // El asesor de un lead debe ser del personal (N07)
        Role::create(['name' => User::ROL_ALUMNO]);
        $this->usuario->assignRole(Role::create(['name' => 'auxiliar']));
        $this->curso = Curso::factory()->create();
    }

    private function crearReferido(array $atributos = []): Referido
    {
        return Referido::factory()->create(array_merge([
            'curso_id'  => $this->curso->id,
            'gestor_id' => $this->usuario->id,
            'status'    => 0,
        ], $atributos));
    }

    /** @test */
    public function index_retorna_lista_paginada_con_curso_y_gestor(): void
    {
        $this->crearReferido();

        $this->actingAs($this->usuario)
            ->getJson(route('referidos.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'nombre', 'celular', 'ciudad', 'talla_overol', 'talla_botas', 'status', 'status_text', 'curso', 'gestor']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            ]);
    }

    /** @test */
    public function index_busca_por_nombre_o_celular(): void
    {
        $this->crearReferido(['nombre' => 'Ana Pérez', 'celular' => '3001112233']);
        $this->crearReferido(['nombre' => 'Luis Gómez', 'celular' => '3109998877']);

        $this->actingAs($this->usuario)
            ->getJson(route('referidos.index', ['search' => '3109998']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Luis Gómez');

        $this->actingAs($this->usuario)
            ->getJson(route('referidos.index', ['search' => 'Ana']))
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function index_filtra_por_estado(): void
    {
        $this->crearReferido(['status' => 1]);
        $this->crearReferido(['status' => 4]);

        $this->actingAs($this->usuario)
            ->getJson(route('referidos.index', ['status' => 4]))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status_text', 'Declinado');
    }

    /** @test */
    public function store_crea_referido_con_tallas_y_estado_creado_por_defecto(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('referidos.store'), [
                'curso_id'     => $this->curso->id,
                'gestor_id'    => $this->usuario->id,
                'nombre'       => 'Pedro Ruiz',
                'celular'      => '3205556677',
                'ciudad'       => 'Tunja',
                'talla_overol' => 'M',
                'talla_botas'  => '40',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 0)
            ->assertJsonPath('data.talla_overol', 'M')
            ->assertJsonPath('data.talla_botas', '40');

        $this->assertDatabaseHas('referidos', ['celular' => '3205556677', 'talla_botas' => '40']);
    }

    /** @test */
    public function store_rechaza_celular_duplicado_y_campos_obligatorios(): void
    {
        $this->crearReferido(['celular' => '3001112233']);

        $this->actingAs($this->usuario)
            ->postJson(route('referidos.store'), ['celular' => '3001112233'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['celular', 'curso_id', 'gestor_id', 'ciudad']);
    }

    /** @test */
    public function el_asesor_debe_ser_del_personal_y_el_selector_no_lista_estudiantes(): void
    {
        $estudiante = User::factory()->create();
        $estudiante->assignRole(User::ROL_ALUMNO);

        $this->actingAs($this->usuario)
            ->postJson(route('referidos.store'), [
                'curso_id' => $this->curso->id, 'gestor_id' => $estudiante->id, 'celular' => '3109998877', 'ciudad' => 'Tunja',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gestor_id');

        $gestores = collect($this->actingAs($this->usuario)->getJson(route('referidos.filters'))->assertOk()->json('data.gestores'))->pluck('id');
        $this->assertContains($this->usuario->id, $gestores);
        $this->assertNotContains($estudiante->id, $gestores);
    }

    /** @test */
    public function reasignar_el_lead_queda_en_su_bitacora(): void
    {
        $referido = $this->crearReferido();
        $nuevo = User::factory()->create(['primer_nombre' => 'Laura', 'segundo_nombre' => null, 'primer_apellido' => 'Gómez', 'segundo_apellido' => null]);
        $nuevo->assignRole('auxiliar');

        $this->actingAs($this->usuario)
            ->putJson(route('referidos.update', $referido), ['gestor_id' => $nuevo->id])
            ->assertOk()
            ->assertJsonPath('data.gestor.id', $nuevo->id);

        $bitacora = $referido->seguimientos()->latest('id')->first();
        $this->assertSame($this->usuario->id, $bitacora->seguidor_id);
        $this->assertStringStartsWith('REASIGNACIÓN: de ' . $this->usuario->name . ' a ' . $nuevo->name, $bitacora->seguimiento);

        // Editar otros datos no deja una reasignación
        $this->actingAs($this->usuario)->putJson(route('referidos.update', $referido), ['ciudad' => 'Duitama'])->assertOk();
        $this->assertSame(1, $referido->seguimientos()->count());
    }

    /** @test */
    public function update_cambia_estado_y_tallas(): void
    {
        $referido = $this->crearReferido();

        $this->actingAs($this->usuario)
            ->putJson(route('referidos.update', $referido), ['status' => 1, 'talla_overol' => 'L'])
            ->assertOk()
            ->assertJsonPath('data.status_text', 'Interesado')
            ->assertJsonPath('data.talla_overol', 'L');
    }

    /** @test */
    public function destroy_rechaza_referido_con_seguimientos(): void
    {
        $referido = $this->crearReferido();
        Seguimiento::factory()->create(['referido_id' => $referido->id, 'seguidor_id' => $this->usuario->id]);

        $this->actingAs($this->usuario)
            ->deleteJson(route('referidos.destroy', $referido))
            ->assertUnprocessable();
    }

    /** @test */
    public function destroy_restore_y_trashed(): void
    {
        $referido = $this->crearReferido();

        $this->actingAs($this->usuario)->deleteJson(route('referidos.destroy', $referido))->assertOk();
        $this->assertSoftDeleted('referidos', ['id' => $referido->id]);

        $this->actingAs($this->usuario)->getJson(route('referidos.trashed'))->assertJsonCount(1, 'data');

        $this->actingAs($this->usuario)->postJson(route('referidos.restore', $referido->id))->assertOk();
        $this->assertNotSoftDeleted('referidos', ['id' => $referido->id]);
    }

    /** @test */
    public function filters_y_statistics_responden(): void
    {
        $this->crearReferido(['status' => 3]);

        $this->actingAs($this->usuario)
            ->getJson(route('referidos.filters'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['status_options', 'ciudades', 'cursos', 'gestores']]);

        $this->actingAs($this->usuario)
            ->getJson(route('referidos.statistics'))
            ->assertOk()
            ->assertJsonPath('data.por_status.matriculados', 1);
    }

    /** @test */
    public function usuario_sin_permiso_recibe_403(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)->getJson(route('referidos.index'))->assertForbidden();
        $this->actingAs($sinPermiso)->postJson(route('referidos.store'), [])->assertForbidden();
    }
}
