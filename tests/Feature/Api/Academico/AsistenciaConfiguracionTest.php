<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\AsistenciaConfiguracion;
use App\Models\Academico\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del CRUD de configuraciones de asistencia. Cubre el bug B20: las
 * configuraciones generales (sin curso) o por curso (sin módulo) respondían 500.
 */
class AsistenciaConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::create(['name' => 'aca_configuracionAsistencia', 'descripcion' => 'configurar asistencia']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo('aca_configuracionAsistencia');
    }

    /** @test */
    public function crea_una_configuracion_general_sin_curso_ni_modulo(): void
    {
        $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/academico/asistencia-configuraciones', ['porcentaje_minimo' => 80])
            ->assertCreated()
            ->assertJsonPath('data.curso', null)
            ->assertJsonPath('data.modulo', null)
            ->assertJsonPath('data.porcentaje_minimo', 80);

        $this->assertDatabaseCount('asistencia_configuraciones', 1);
    }

    /** @test */
    public function crea_una_configuracion_por_curso_sin_modulo(): void
    {
        $curso = Curso::factory()->create();

        $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/academico/asistencia-configuraciones', [
                'curso_id' => $curso->id,
                'porcentaje_minimo' => 75,
            ])
            ->assertCreated()
            ->assertJsonPath('data.curso.id', $curso->id)
            ->assertJsonPath('data.modulo', null);
    }

    /** @test */
    public function lista_muestra_y_actualiza_configuraciones_sin_relaciones(): void
    {
        $configuracion = AsistenciaConfiguracion::factory()->create([
            'curso_id' => null,
            'modulo_id' => null,
            'porcentaje_minimo' => 80,
        ]);

        $this->actingAs($this->usuario, 'sanctum')
            ->getJson('/api/academico/asistencia-configuraciones')
            ->assertOk()
            ->assertJsonPath('data.0.id', $configuracion->id);

        $this->actingAs($this->usuario, 'sanctum')
            ->getJson("/api/academico/asistencia-configuraciones/{$configuracion->id}")
            ->assertOk()
            ->assertJsonPath('data.curso', null);

        $this->actingAs($this->usuario, 'sanctum')
            ->putJson("/api/academico/asistencia-configuraciones/{$configuracion->id}", ['porcentaje_minimo' => 85])
            ->assertOk()
            ->assertJsonPath('data.porcentaje_minimo', 85);
    }

    /** @test */
    public function valida_el_porcentaje_minimo(): void
    {
        $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/academico/asistencia-configuraciones', ['porcentaje_minimo' => 120])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('porcentaje_minimo');
    }

    /** @test */
    public function niega_el_acceso_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/academico/asistencia-configuraciones')
            ->assertForbidden()
            // Mensaje en español, no el de Spatie (F14)
            ->assertJsonPath('message', 'No tiene permiso para realizar esta acción.');
    }
}
