<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Buscador y registro de estudiantes para cajeros, coordinadores y auxiliares (B49):
 * /configuracion/users es solo del superusuario y bloqueaba matrículas, ventas y cobros.
 * El buscador no muestra al personal que no estudia (N01).
 */
class EstudianteTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;
    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['fin_reciboPagoCrear', 'inv_ventasCrear', 'aca_matriculaCrear', 'aca_matriculaEditar'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }
        Role::create(['name' => User::ROL_ALUMNO]);
        Role::create(['name' => 'profesor']);

        $this->cajero = User::factory()->create();
        $this->cajero->givePermissionTo(['fin_reciboPagoCrear', 'inv_ventasCrear']);
        $this->coordinador = User::factory()->create();
        $this->coordinador->givePermissionTo(['aca_matriculaCrear', 'aca_matriculaEditar']);
    }

    /** @test */
    public function el_cajero_busca_estudiantes_por_documento_sin_ver_al_personal(): void
    {
        // N01 (prueba integral 2): sin filtro listaba también al personal y a los superusuarios
        $alumno = User::factory()->create(['documento' => '1077665544']);
        $alumno->assignRole(User::ROL_ALUMNO);
        $profesor = User::factory()->create(['documento' => '1077665599']);
        $profesor->assignRole('profesor');
        $profesorQueEstudia = User::factory()->create(['documento' => '1077665588']);
        $profesorQueEstudia->assignRole('profesor');
        Matricula::factory()->create(['estudiante_id' => $profesorQueEstudia->id]);

        $ids = fn ($response) => collect($response->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame(
            collect([$alumno->id, $profesorQueEstudia->id])->sort()->values()->all(),
            $ids($this->actingAs($this->cajero)->getJson(route('estudiantes.index', ['search' => '10776655'])))
        );

        // incluir_personal solo vale para quien matricula
        $this->assertNotContains($profesor->id, $ids($this->actingAs($this->cajero)
            ->getJson(route('estudiantes.index', ['search' => '10776655', 'incluir_personal' => 1]))));
        $this->assertContains($profesor->id, $ids($this->actingAs($this->coordinador)
            ->getJson(route('estudiantes.index', ['search' => '10776655', 'incluir_personal' => 1]))));

        $this->assertSame(
            collect([$profesor->id, $profesorQueEstudia->id])->sort()->values()->all(),
            $ids($this->actingAs($this->cajero)->getJson(route('estudiantes.index', ['search' => '10776655', 'solo_personal' => 1])))
        );
    }

    /** @test */
    public function la_coordinacion_registra_un_estudiante_con_rol_alumno(): void
    {
        $this->actingAs($this->coordinador)->postJson(route('estudiantes.store'), [
            'primer_nombre' => 'Héctor', 'primer_apellido' => 'Cuervo', 'email' => 'hector@carmot.test',
            'documento' => '1077665544', 'password' => '1077665544', 'password_confirmation' => '1077665544',
        ])->assertCreated()->assertJsonPath('data.roles', [User::ROL_ALUMNO]);
    }

    /** @test */
    public function no_corrige_por_aqui_los_datos_del_personal(): void
    {
        $profesor = User::factory()->create();
        $profesor->assignRole('profesor');

        $this->actingAs($this->coordinador)->putJson(route('estudiantes.update', $profesor), ['primer_nombre' => 'X'])
            ->assertForbidden();
    }

    /** @test */
    public function sin_permisos_de_operacion_responde_403(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('estudiantes.index'))->assertForbidden();
    }
}
