<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Curso;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MatriculaPrecargaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_matriculas', 'descripcion' => 'ver matriculas']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo('aca_matriculas');
    }

    /** @test */
    public function retorna_datos_personales_de_la_matricula_mas_reciente(): void
    {
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create();
        $ciclo      = Ciclo::factory()->create();

        Matricula::factory()
            ->conDatosPersonales()
            ->create([
                'estudiante_id'  => $estudiante->id,
                'curso_id'       => $curso->id,
                'ciclo_id'       => $ciclo->id,
                'created_at'     => now()->subDays(10),
            ]);

        $reciente = Matricula::factory()
            ->conDatosPersonales()
            ->create([
                'estudiante_id'  => $estudiante->id,
                'curso_id'       => Curso::factory()->create()->id,
                'ciclo_id'       => $ciclo->id,
                'celular'        => '3009999999',
                'created_at'     => now(),
            ]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('matriculas.precarga-estudiante', $estudiante->id));

        $response->assertOk()
            ->assertJsonPath('data.matricula_referencia_id', $reciente->id)
            ->assertJsonPath('data.celular', '3009999999');
    }

    /** @test */
    public function retorna_todos_los_campos_personales_esperados(): void
    {
        $estudiante = User::factory()->create();

        Matricula::factory()
            ->conDatosPersonales()
            ->create(['estudiante_id' => $estudiante->id]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('matriculas.precarga-estudiante', $estudiante->id));

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'matricula_referencia_id',
                'tipo_identificacion', 'tipo_identificacion_texto',
                'departamento_expedicion', 'ciudad_expedicion',
                'fecha_nacimiento',
                'genero', 'genero_texto',
                'estado_civil', 'estado_civil_texto',
                'grupo_sanguineo', 'rh', 'rh_texto',
                'direccion', 'celular', 'telefono',
                'lugar_origen_id', 'lugar_origen',
                'nivel_educacion', 'nivel_educacion_texto',
                'ocupacion', 'empresa', 'estrato',
                'regimen_salud', 'regimen_salud_texto',
                'enfermedad_prioritaria', 'discapacidad',
                'conocimiento_curso', 'como_entero_curso',
                'talla_overol', 'talla_botas',
                'nombre_contacto', 'telefono_contacto', 'correo_contacto',
                'aprueba_uso_imagen', 'multiculturalidad', 'foto',
            ]]);
    }

    /** @test */
    public function no_expone_campos_de_pago_ni_de_curso(): void
    {
        $estudiante = User::factory()->create();

        Matricula::factory()
            ->conDatosPersonales()
            ->create(['estudiante_id' => $estudiante->id]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('matriculas.precarga-estudiante', $estudiante->id));

        $data = $response->assertOk()->json('data');

        $this->assertArrayNotHasKey('curso_id', $data);
        $this->assertArrayNotHasKey('ciclo_id', $data);
        $this->assertArrayNotHasKey('monto', $data);
        $this->assertArrayNotHasKey('valor_cuota', $data);
        $this->assertArrayNotHasKey('fecha_matricula', $data);
        $this->assertArrayNotHasKey('fecha_inicio', $data);
        $this->assertArrayNotHasKey('observaciones', $data);
        $this->assertArrayNotHasKey('status', $data);
        $this->assertArrayNotHasKey('matriculado_por_id', $data);
        $this->assertArrayNotHasKey('comercial_id', $data);
    }

    /** @test */
    public function retorna_404_cuando_el_estudiante_no_tiene_matriculas_previas(): void
    {
        $estudiante = User::factory()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('matriculas.precarga-estudiante', $estudiante->id))
            ->assertNotFound()
            ->assertJsonPath('message', 'El estudiante no tiene matrículas previas registradas.');
    }

    /** @test */
    public function retorna_404_cuando_el_id_de_estudiante_no_existe(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('matriculas.precarga-estudiante', 99999))
            ->assertNotFound()
            ->assertJsonPath('message', 'El estudiante no existe.');
    }

    /** @test */
    public function requiere_autenticacion(): void
    {
        $this->getJson(route('matriculas.precarga-estudiante', 1))
            ->assertUnauthorized();
    }

    /** @test */
    public function incluye_lugar_origen_cuando_esta_registrado(): void
    {
        $estudiante  = User::factory()->create();
        $lugarOrigen = \App\Models\Configuracion\Poblacion::factory()->create();

        Matricula::factory()
            ->conDatosPersonales()
            ->create([
                'estudiante_id'  => $estudiante->id,
                'lugar_origen_id' => $lugarOrigen->id,
            ]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('matriculas.precarga-estudiante', $estudiante->id));

        $response->assertOk()
            ->assertJsonPath('data.lugar_origen_id', $lugarOrigen->id)
            ->assertJsonStructure(['data' => ['lugar_origen' => ['id', 'pais', 'provincia', 'nombre']]]);
    }
}
