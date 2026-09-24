<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos de listas de precios del módulo Inventarios (origen=0).
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddInventariosListaPrecioPermissionsSeeder
 */
class AddInventariosListaPrecioPermissionsSeeder extends Seeder
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

        Permission::firstOrCreate(['name' => 'inv_listas'],
            ['descripcion' => 'Ver listas de precios de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_listasCrear'],
            ['descripcion' => 'Crear listas de precios de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_listasEditar'],
            ['descripcion' => 'Editar listas de precios de inventario (solo En Proceso)'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_listasAprobar'],
            ['descripcion' => 'Aprobar y activar listas de precios de inventario'])
            ->syncRoles([$superusuario]);

        Permission::firstOrCreate(['name' => 'inv_listasInactivar'],
            ['descripcion' => 'Inactivar y eliminar listas de precios de inventario'])
            ->syncRoles([$superusuario]);

        Permission::firstOrCreate(['name' => 'inv_listasClonar'],
            ['descripcion' => 'Clonar una lista de precios de inventario'])
            ->syncRoles([$superusuario, $coordinador]);
    }
}
