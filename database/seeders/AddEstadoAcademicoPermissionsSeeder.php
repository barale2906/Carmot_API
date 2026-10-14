<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega el permiso para cambiar el estado académico de las matrículas
 * (aplazado, desertado, retirado, graduado).
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddEstadoAcademicoPermissionsSeeder
 */
class AddEstadoAcademicoPermissionsSeeder extends Seeder
{
    /**
     * Ejecuta el seeder de permisos.
     *
     * @return void
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $superusuario = Role::firstOrCreate(['name' => 'superusuario', 'guard_name' => 'web']);
        $coordinador  = Role::firstOrCreate(['name' => 'coordinador',  'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'aca_matriculaEstado'],
            ['descripcion' => 'Cambiar el estado académico de una matrícula (aplazado, desertado, retirado, graduado)'])
            ->syncRoles([$superusuario, $coordinador]);
    }
}
