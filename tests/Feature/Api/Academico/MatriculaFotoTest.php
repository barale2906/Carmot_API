<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MatriculaFotoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_matriculaCrear', 'descripcion' => 'crear matricula']);
        Permission::create(['name' => 'aca_matriculaEditar', 'descripcion' => 'editar matricula']);
        Permission::create(['name' => 'aca_matriculaInactivar', 'descripcion' => 'inactivar matricula']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['aca_matriculaCrear', 'aca_matriculaEditar', 'aca_matriculaInactivar']);
    }

    private function datosBase(array $overrides = []): array
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

    /** @test */
    public function permite_crear_una_matricula_subiendo_una_foto(): void
    {
        Storage::fake('public');

        $foto = UploadedFile::fake()->image('estudiante.jpg', 200, 200)->size(500);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosBase(['foto' => $foto]));

        $response->assertCreated();

        $matricula = Matricula::findOrFail($response->json('data.id'));

        $this->assertNotNull($matricula->foto);
        Storage::disk('public')->assertExists($matricula->foto);
    }

    /** @test */
    public function rechaza_un_archivo_que_no_sea_imagen_en_foto(): void
    {
        Storage::fake('public');

        $archivo = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosBase(['foto' => $archivo]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('foto');
    }

    /** @test */
    public function rechaza_una_foto_que_supera_el_tamano_maximo(): void
    {
        Storage::fake('public');

        $foto = UploadedFile::fake()->image('estudiante.jpg')->size(5121);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosBase(['foto' => $foto]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('foto');
    }

    /** @test */
    public function permite_crear_una_matricula_sin_foto(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosBase());

        $response->assertCreated()
            ->assertJsonPath('data.foto', null);
    }

    /** @test */
    public function permite_actualizar_la_foto_de_una_matricula_y_elimina_la_anterior(): void
    {
        Storage::fake('public');

        $fotoInicial = UploadedFile::fake()->image('inicial.jpg')->size(300);

        $creacion = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosBase(['foto' => $fotoInicial]));

        $matricula  = Matricula::findOrFail($creacion->json('data.id'));
        $rutaInicial = $matricula->foto;

        $fotoNueva = UploadedFile::fake()->image('nueva.jpg')->size(300);

        $response = $this->actingAs($this->usuario)
            ->putJson(route('matriculas.update', $matricula->id), ['foto' => $fotoNueva]);

        $response->assertOk();

        $matricula->refresh();

        Storage::disk('public')->assertMissing($rutaInicial);
        Storage::disk('public')->assertExists($matricula->foto);
    }
}
