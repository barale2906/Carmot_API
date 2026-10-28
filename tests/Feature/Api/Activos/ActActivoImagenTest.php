<?php

namespace Tests\Feature\Api\Activos;

use App\Models\Activos\ActActivo;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Imagen del activo: carga, reemplazo, eliminación y limpieza del archivo.
 */
class ActActivoImagenTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private ActActivo $activo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['act_activos', 'act_activoEditar', 'act_activoInactivar'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $sede = Sede::factory()->create();
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['act_activos', 'act_activoEditar', 'act_activoInactivar']);
        $this->usuario->sedes()->attach($sede->id);

        $this->activo = ActActivo::factory()->create(['sede_id' => $sede->id]);
    }

    private function subir(UploadedFile $archivo)
    {
        return $this->actingAs($this->usuario)
            ->post(route('act-activos.imagen', $this->activo), ['imagen' => $archivo], ['Accept' => 'application/json']);
    }

    /** @test */
    public function sube_la_imagen_y_la_expone_con_su_url(): void
    {
        $this->subir(UploadedFile::fake()->image('taladro.jpg', 400, 300))
            ->assertOk()
            ->assertJsonPath('data.id', $this->activo->id);

        $ruta = $this->activo->fresh()->imagen;
        $this->assertStringStartsWith('activos/' . $this->usuario->id . '_', $ruta);
        Storage::disk('public')->assertExists($ruta);

        $this->actingAs($this->usuario)->getJson(route('act-activos.show', $this->activo))
            ->assertJsonPath('data.imagen', $ruta)
            ->assertJsonPath('data.imagen_url', asset('storage/' . $ruta));
    }

    /** @test */
    public function al_reemplazar_la_imagen_borra_la_anterior(): void
    {
        $this->subir(UploadedFile::fake()->image('uno.jpg'))->assertOk();
        $anterior = $this->activo->fresh()->imagen;

        $this->travel(1)->seconds();
        $this->subir(UploadedFile::fake()->image('dos.png'))->assertOk();

        $nueva = $this->activo->fresh()->imagen;
        $this->assertNotSame($anterior, $nueva);
        Storage::disk('public')->assertMissing($anterior);
        Storage::disk('public')->assertExists($nueva);
    }

    /** @test */
    public function elimina_la_imagen_y_el_archivo(): void
    {
        $this->subir(UploadedFile::fake()->image('uno.jpg'))->assertOk();
        $ruta = $this->activo->fresh()->imagen;

        $this->actingAs($this->usuario)->deleteJson(route('act-activos.imagen.eliminar', $this->activo))
            ->assertOk()
            ->assertJsonPath('data.imagen', null)
            ->assertJsonPath('data.imagen_url', null);

        Storage::disk('public')->assertMissing($ruta);
    }

    /** @test */
    public function el_borrado_definitivo_del_activo_borra_su_imagen(): void
    {
        $this->subir(UploadedFile::fake()->image('uno.jpg'))->assertOk();
        $ruta = $this->activo->fresh()->imagen;
        $this->activo->delete();

        $this->actingAs($this->usuario)->deleteJson(route('act-activos.force-delete', $this->activo->id))->assertOk();

        Storage::disk('public')->assertMissing($ruta);
    }

    /** @test */
    public function valida_que_sea_una_imagen_y_que_el_activo_no_este_de_baja(): void
    {
        $this->subir(UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'))
            ->assertUnprocessable()->assertJsonValidationErrors('imagen');

        $this->actingAs($this->usuario)->post(route('act-activos.imagen', $this->activo), [], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('imagen');

        $this->activo->update(['estado' => 'baja']);
        $this->subir(UploadedFile::fake()->image('uno.jpg'))
            ->assertUnprocessable()->assertJsonValidationErrors('imagen');
    }

    /** @test */
    public function sin_permiso_de_edicion_no_sube_imagen(): void
    {
        $lector = User::factory()->create();
        $lector->givePermissionTo('act_activos');
        $lector->sedes()->attach($this->activo->sede_id);

        $this->actingAs($lector)
            ->post(route('act-activos.imagen', $this->activo), ['imagen' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    /** @test */
    public function no_sube_imagen_a_un_activo_de_una_sede_no_asignada(): void
    {
        $ajeno = ActActivo::factory()->create();

        $this->actingAs($this->usuario)
            ->post(route('act-activos.imagen', $ajeno), ['imagen' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])
            ->assertForbidden();
    }
}
