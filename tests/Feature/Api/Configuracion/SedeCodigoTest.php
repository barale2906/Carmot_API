<?php

namespace Tests\Feature\Api\Configuracion;

use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas para los campos codigo_academico y codigo_inventario en Sede.
 */
class SedeCodigoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'co_sedes',       'descripcion' => 'ver sedes']);
        Permission::create(['name' => 'co_sedeCrear',   'descripcion' => 'crear sede']);
        Permission::create(['name' => 'co_sedeEditar',  'descripcion' => 'editar sede']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['co_sedes', 'co_sedeCrear', 'co_sedeEditar']);
    }

    /** Payload mínimo válido para crear una sede. */
    private function payloadBase(array $overrides = []): array
    {
        return array_merge([
            'nombre'       => 'Sede Test',
            'direccion'    => 'Calle 1 # 2-3',
            'telefono'     => '3101234567',
            'email'        => 'sede.test@example.com',
            'hora_inicio'  => '08:00:00',
            'hora_fin'     => '18:00:00',
            'poblacion_id' => \App\Models\Configuracion\Poblacion::factory()->create()->id,
            'areas'        => [\App\Models\Configuracion\Area::factory()->create()->id],
        ], $overrides);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_persiste_codigo_academico_e_inventario(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('sedes.store'), $this->payloadBase([
                'codigo_academico'  => 'TUN-AC',
                'codigo_inventario' => 'TUN-INV',
            ]));

        $response->assertCreated()
            ->assertJsonPath('data.codigo_academico', 'TUN-AC')
            ->assertJsonPath('data.codigo_inventario', 'TUN-INV');

        $this->assertDatabaseHas('sedes', [
            'codigo_academico'  => 'TUN-AC',
            'codigo_inventario' => 'TUN-INV',
        ]);
    }

    /** @test */
    public function store_permite_crear_sede_sin_codigos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('sedes.store'), $this->payloadBase())
            ->assertCreated()
            ->assertJsonPath('data.codigo_academico', null)
            ->assertJsonPath('data.codigo_inventario', null);
    }

    /** @test */
    public function store_rechaza_codigo_academico_duplicado(): void
    {
        Sede::factory()->create(['codigo_academico' => 'TUN-AC']);

        $this->actingAs($this->usuario)
            ->postJson(route('sedes.store'), $this->payloadBase(['codigo_academico' => 'TUN-AC']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo_academico']);
    }

    /** @test */
    public function store_rechaza_codigo_de_mas_de_10_caracteres(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('sedes.store'), $this->payloadBase(['codigo_academico' => 'CODIGOLARGO1']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo_academico']);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function update_asigna_codigo_academico_a_sede_existente(): void
    {
        $sede = Sede::factory()->create(['codigo_academico' => null]);

        $this->actingAs($this->usuario)
            ->putJson(route('sedes.update', $sede), ['codigo_academico' => 'DUI-AC'])
            ->assertOk()
            ->assertJsonPath('data.codigo_academico', 'DUI-AC');

        $this->assertDatabaseHas('sedes', ['id' => $sede->id, 'codigo_academico' => 'DUI-AC']);
    }

    /** @test */
    public function update_permite_mantener_el_propio_codigo(): void
    {
        $sede = Sede::factory()->create(['codigo_academico' => 'TUN-AC']);

        $this->actingAs($this->usuario)
            ->putJson(route('sedes.update', $sede), ['codigo_academico' => 'TUN-AC'])
            ->assertOk()
            ->assertJsonPath('data.codigo_academico', 'TUN-AC');
    }

    /** @test */
    public function update_rechaza_codigo_academico_de_otra_sede(): void
    {
        Sede::factory()->create(['codigo_academico' => 'TUN-AC']);
        $sede = Sede::factory()->create(['codigo_academico' => null]);

        $this->actingAs($this->usuario)
            ->putJson(route('sedes.update', $sede), ['codigo_academico' => 'TUN-AC'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo_academico']);
    }

    /** @test */
    public function update_permite_limpiar_codigo_con_null(): void
    {
        $sede = Sede::factory()->create(['codigo_academico' => 'TUN-AC']);

        $this->actingAs($this->usuario)
            ->putJson(route('sedes.update', $sede), ['codigo_academico' => null])
            ->assertOk()
            ->assertJsonPath('data.codigo_academico', null);
    }

    // ─── show / resource ──────────────────────────────────────────────────────

    /** @test */
    public function show_expone_los_codigos_en_la_respuesta(): void
    {
        $sede = Sede::factory()->create([
            'codigo_academico'  => 'IPI-AC',
            'codigo_inventario' => 'IPI-INV',
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('sedes.show', $sede))
            ->assertOk()
            ->assertJsonPath('data.codigo_academico', 'IPI-AC')
            ->assertJsonPath('data.codigo_inventario', 'IPI-INV');
    }

    // ─── permisos ─────────────────────────────────────────────────────────────

    /** @test */
    public function update_rechaza_sin_permiso_de_edicion(): void
    {
        $sede = Sede::factory()->create();
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->putJson(route('sedes.update', $sede), ['codigo_academico' => 'TUN-AC'])
            ->assertForbidden();
    }
}
