<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos del módulo Configuración → Calendario de días no laborables.
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddCalendarioLaboralPermissionsSeeder
 */
class AddCalendarioLaboralPermissionsSeeder extends Seeder
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

        Permission::firstOrCreate(['name' => 'co_diasNoLaborables'],
            ['descripcion' => 'Ver el calendario de días no laborables'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'co_diaNoLaborableCrear'],
            ['descripcion' => 'Crear días no laborables, rangos y generar festivos'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'co_diaNoLaborableEditar'],
            ['descripcion' => 'Editar días no laborables'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'co_diaNoLaborableInactivar'],
            ['descripcion' => 'Inactivar y eliminar días no laborables'])
            ->syncRoles([$superusuario]);
    }
}
