<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\AddInventariosOcPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifica que el seeder de órdenes de compra asigne sus permisos a los roles,
 * de modo que una instalación limpia pueda usar el módulo (bug B33).
 */
class AddInventariosOcPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function asigna_los_permisos_de_oc_a_superusuario_y_coordinador(): void
    {
        $this->seed(AddInventariosOcPermissionsSeeder::class);

        $superusuario = Role::findByName('superusuario');
        $coordinador = Role::findByName('coordinador');

        foreach (['inv_oc', 'inv_ocCrear', 'inv_ocEditar', 'inv_ocEnviar', 'inv_ocRecibir', 'inv_ocCancelar'] as $permiso) {
            $this->assertTrue($superusuario->hasPermissionTo($permiso), "superusuario sin {$permiso}");
        }
        $this->assertTrue($coordinador->hasPermissionTo('inv_ocCrear'));
        $this->assertFalse($coordinador->hasPermissionTo('inv_ocCancelar'));
    }

    /** @test */
    public function un_superusuario_de_instalacion_limpia_puede_listar_ordenes_de_compra(): void
    {
        $this->seed(AddInventariosOcPermissionsSeeder::class);

        $usuario = User::factory()->create();
        $usuario->assignRole('superusuario');

        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/inventarios/ordenes-compra')
            ->assertOk();
    }

    /** @test */
    public function se_puede_ejecutar_varias_veces_sin_duplicar(): void
    {
        $this->seed(AddInventariosOcPermissionsSeeder::class);
        $this->seed(AddInventariosOcPermissionsSeeder::class);

        $this->assertSame(6, \Spatie\Permission\Models\Permission::where('name', 'like', 'inv_oc%')->count());
    }
}
