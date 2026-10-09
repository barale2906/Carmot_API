<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MatriculaSaludTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['aca_matriculas' => 'ver', 'aca_matriculaCrear' => 'crear', 'aca_matriculaEditar' => 'editar'] as $name => $desc) {
            Permission::create(['name' => $name, 'descripcion' => "$desc matricula"]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['aca_matriculas', 'aca_matriculaCrear', 'aca_matriculaEditar']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sede_id'            => Sede::factory()->create()->id,
            'curso_id'           => Curso::factory()->create()->id,
            'ciclo_id'           => Ciclo::factory()->create()->id,
            'estudiante_id'      => User::factory()->create()->id,
            'matriculado_por_id' => $this->usuario->id,
            'comercial_id'       => $this->usuario->id,
            'fecha_matricula'    => now()->format('Y-m-d'),
            'fecha_inicio'       => now()->addDay()->format('Y-m-d'),
            'monto'              => 500000,
        ], $overrides);
    }

    private function crear(array $overrides = [])
    {
        return $this->actingAs($this->usuario)->postJson(route('matriculas.store'), $this->payload($overrides));
    }

    /** @test */
    public function guarda_el_tipo_de_discapacidad_y_la_enfermedad(): void
    {
        $this->crear([
            'discapacidad'           => true,
            'tipo_discapacidad'      => 'ceguera',
            'enfermedad_prioritaria' => true,
            'enfermedad_detalle'     => 'Diabetes tipo 2',
        ])->assertCreated()
            ->assertJsonPath('data.tipo_discapacidad', 'ceguera')
            ->assertJsonPath('data.tipo_discapacidad_texto', 'Ceguera')
            ->assertJsonPath('data.enfermedad_detalle', 'Diabetes tipo 2');
    }

    /** @test */
    public function exige_el_tipo_de_discapacidad_cuando_hay_discapacidad(): void
    {
        $this->crear(['discapacidad' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo_discapacidad');
    }

    /** @test */
    public function rechaza_un_tipo_de_discapacidad_fuera_del_catalogo(): void
    {
        $this->crear(['discapacidad' => true, 'tipo_discapacidad' => 'otra'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo_discapacidad');
    }

    /** @test */
    public function exige_digitar_la_enfermedad_cuando_es_prioritaria(): void
    {
        $this->crear(['enfermedad_prioritaria' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('enfermedad_detalle');
    }

    /** @test */
    public function limpia_el_detalle_cuando_la_condicion_se_desmarca(): void
    {
        $id = $this->crear([
            'discapacidad'           => true,
            'tipo_discapacidad'      => 'sordera_profunda',
            'enfermedad_prioritaria' => true,
            'enfermedad_detalle'     => 'Asma',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->usuario)
            ->putJson(route('matriculas.update', $id), ['discapacidad' => false, 'enfermedad_prioritaria' => false])
            ->assertOk()
            ->assertJsonPath('data.tipo_discapacidad', null)
            ->assertJsonPath('data.enfermedad_detalle', null);
    }

    /** @test */
    public function el_catalogo_de_filtros_incluye_los_tipos_de_discapacidad(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('matriculas.filters'))
            ->assertOk()
            ->assertJsonPath('data.tipos_discapacidad', Matricula::TIPOS_DISCAPACIDAD);
    }
}
