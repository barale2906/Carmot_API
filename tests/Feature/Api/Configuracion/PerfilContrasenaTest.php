<?php

namespace Tests\Feature\Api\Configuracion;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cambio de la contraseña propia desde el perfil (F03).
 */
class PerfilContrasenaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::create(['name' => 'co_usersPerfil', 'descripcion' => 'perfil']);
        Role::create(['name' => 'cajero'])->givePermissionTo('co_usersPerfil');

        $this->usuario = User::factory()->create(['password' => Hash::make('Actual2026*')]);
        $this->usuario->assignRole('cajero');
        Sanctum::actingAs($this->usuario);
    }

    /** @test */
    public function cambia_la_contrasena_con_la_actual_correcta(): void
    {
        $this->putJson(route('perfil.contrasena'), [
            'contrasena_actual' => 'Actual2026*',
            'password' => 'Nueva2026#', 'password_confirmation' => 'Nueva2026#',
        ])->assertOk();

        $this->assertTrue(Hash::check('Nueva2026#', $this->usuario->fresh()->password));
    }

    /** @test */
    public function rechaza_una_contrasena_actual_incorrecta_o_una_nueva_debil(): void
    {
        $this->putJson(route('perfil.contrasena'), [
            'contrasena_actual' => 'otra',
            'password' => '12345678', 'password_confirmation' => '12345678',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contrasena_actual', 'password']);
    }

    /** @test */
    public function sin_el_permiso_de_perfil_responde_403(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('perfil.contrasena'), [])->assertForbidden();
    }
}
