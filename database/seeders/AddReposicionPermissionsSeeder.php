<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos de las reposiciones de clase (el estudiante toma una clase en otro grupo).
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddReposicionPermissionsSeeder
 */
class AddReposicionPermissionsSeeder extends Seeder
{
    /**
     * Ejecuta el seeder de permisos.
     *
     * @return void
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = fn (array $nombres) => collect($nombres)
            ->map(fn (string $nombre) => Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']))
            ->all();

        Permission::firstOrCreate(['name' => 'aca_reposiciones'], ['descripcion' => 'ver reposiciones de clase'])
            ->syncRoles($roles(['superusuario', 'coordinador', 'auxiliar', 'profesor', 'alumno']));

        Permission::firstOrCreate(['name' => 'aca_reposicionCrear'], ['descripcion' => 'registrar reposición de clase'])
            ->syncRoles($roles(['superusuario', 'coordinador', 'auxiliar', 'alumno']));

        Permission::firstOrCreate(['name' => 'aca_reposicionInactivar'], ['descripcion' => 'cancelar reposición de clase'])
            ->syncRoles($roles(['superusuario', 'coordinador', 'auxiliar']));
    }
}
