<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * POST matriculas/validar: valida matrícula y estudiante nuevo sin crear registros.
 */
class MatriculaValidarTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::create(['name' => 'aca_matriculaCrear', 'descripcion' => 'crear matricula']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo('aca_matriculaCrear');
    }

    private function datosMatricula(array $overrides = []): array
    {
        $ciclo = Ciclo::factory()->create();

        return array_merge([
            'sede_id'            => $ciclo->sede_id,
            'curso_id'           => $ciclo->curso_id,
            'ciclo_id'           => $ciclo->id,
            'matriculado_por_id' => $this->usuario->id,
            'comercial_id'       => $this->usuario->id,
            'fecha_matricula'    => now()->format('Y-m-d'),
            'fecha_inicio'       => now()->addDay()->format('Y-m-d'),
            'monto'              => 500000,
        ], $overrides);
    }

    private function estudianteNuevo(array $overrides = []): array
    {
        return array_merge([
            'primer_nombre'         => 'Ana',
            'primer_apellido'       => 'Pérez',
            'email'                 => 'ana@example.com',
            'documento'             => '1030535869',
            'password'              => '1030535869',
            'password_confirmation' => '1030535869',
        ], $overrides);
    }

    private function validar(array $datos)
    {
        return $this->actingAs($this->usuario)->postJson(route('matriculas.validar'), $datos);
    }

    /** @test */
    public function acepta_matricula_valida_de_estudiante_nuevo_sin_crear_registros(): void
    {
        $datos    = $this->datosMatricula(['estudiante' => $this->estudianteNuevo()]);
        $usuarios = User::count();

        $this->validar($datos)->assertOk();

        $this->assertSame($usuarios, User::count());
        $this->assertSame(0, Matricula::count());
    }

    /** @test */
    public function rechaza_errores_de_la_matricula_para_estudiante_nuevo(): void
    {
        $this->validar($this->datosMatricula([
            'estudiante'   => $this->estudianteNuevo(),
            'fecha_inicio' => now()->subDay()->format('Y-m-d'),
        ]))->assertStatus(422)->assertJsonValidationErrors(['fecha_inicio']);
    }

    /** @test */
    public function rechaza_estudiante_nuevo_con_documento_o_correo_repetidos(): void
    {
        $existente = User::factory()->create();

        $this->validar($this->datosMatricula([
            'estudiante' => $this->estudianteNuevo(['email' => $existente->email, 'documento' => $existente->documento]),
        ]))->assertStatus(422)->assertJsonValidationErrors(['estudiante.email', 'estudiante.documento']);
    }

    /** @test */
    public function exige_datos_del_estudiante_cuando_no_hay_estudiante_id(): void
    {
        $this->validar($this->datosMatricula())
            ->assertStatus(422)->assertJsonValidationErrors(['estudiante']);
    }

    /** @test */
    public function valida_matricula_de_estudiante_existente(): void
    {
        $estudiante = User::factory()->create();

        $this->validar($this->datosMatricula(['estudiante_id' => $estudiante->id]))->assertOk();
    }
}
