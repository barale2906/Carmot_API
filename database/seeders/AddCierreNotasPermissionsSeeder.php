<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos para cerrar y reabrir el registro de notas de un ciclo.
 * Cerrar queda para la coordinación; reabrir, solo para el superusuario (se puede
 * asignar a otros roles desde la administración de roles).
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddCierreNotasPermissionsSeeder
 */
class AddCierreNotasPermissionsSeeder extends Seeder
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

        Permission::firstOrCreate(['name' => 'aca_notaCerrar'],
            ['descripcion' => 'Cerrar el registro de notas de un ciclo'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'aca_notaReabrir'],
            ['descripcion' => 'Reabrir el registro de notas de un ciclo cerrado'])
            ->syncRoles([$superusuario]);
    }
}
