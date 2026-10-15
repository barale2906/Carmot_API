<?php

namespace Tests\Feature\Api\Configuracion;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Política de contraseñas al crear usuarios (F19): el personal necesita una clave fuerte;
 * el alumno puede iniciar con su documento, pero sin espacios.
 */
class UsuarioContrasenaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::create(['name' => 'co_userCrear', 'descripcion' => 'crear usuario']);
        Role::create(['name' => 'cajero']);
        Role::create(['name' => User::ROL_ALUMNO]);
        Role::create(['name' => 'superusuario']);

        $admin = User::factory()->create();
        $admin->givePermissionTo('co_userCrear');
        $admin->assignRole('superusuario');
        $this->actingAs($admin);
    }

    /** @test */
    public function el_personal_no_puede_tener_una_clave_debil(): void
    {
        $this->postJson(route('users.store'), $this->datos('12345678', ['cajero']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson(route('users.store'), $this->datos('Carmot2026*', ['cajero']))->assertCreated();
    }

    /** @test */
    public function el_alumno_puede_iniciar_con_su_documento_pero_no_con_espacios(): void
    {
        $this->postJson(route('users.store'), $this->datos('1077665544', [User::ROL_ALUMNO]))->assertCreated();

        $this->postJson(route('users.store'), $this->datos('Héctor Cuervo', [User::ROL_ALUMNO]))
            ->assertUnprocessable()
            ->assertJsonFragment(['La contraseña no puede tener espacios.']);
    }

    private function datos(string $clave, array $roles): array
    {
        return [
            'primer_nombre' => 'Ana', 'primer_apellido' => 'Pérez',
            'email' => uniqid('u') . '@carmot.test', 'documento' => (string) random_int(10000000, 99999999),
            'password' => $clave, 'password_confirmation' => $clave, 'roles' => $roles,
        ];
    }
}
