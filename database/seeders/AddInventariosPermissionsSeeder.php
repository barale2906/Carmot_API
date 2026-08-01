<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos del módulo Inventarios a una base de datos existente.
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddInventariosPermissionsSeeder
 */
class AddInventariosPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $superusuario = Role::firstOrCreate(['name' => 'superusuario', 'guard_name' => 'web']);
        $cajero       = Role::firstOrCreate(['name' => 'cajero',       'guard_name' => 'web']);
        $coordinador  = Role::firstOrCreate(['name' => 'coordinador',  'guard_name' => 'web']);

        // ── Categorías ──────────────────────────────────────────────────────────
        Permission::firstOrCreate(['name' => 'inv_categorias'],
            ['descripcion' => 'ver categorías de inventario'])
            ->syncRoles([$superusuario, $coordinador, $cajero]);

        Permission::firstOrCreate(['name' => 'inv_categoriasCrear'],
            ['descripcion' => 'crear categorías de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_categoriasEditar'],
            ['descripcion' => 'editar categorías de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_categoriasInactivar'],
            ['descripcion' => 'inactivar/eliminar categorías de inventario'])
            ->syncRoles([$superusuario]);

        // ── Unidades de medida ──────────────────────────────────────────────────
        Permission::firstOrCreate(['name' => 'inv_unidades'],
            ['descripcion' => 'ver unidades de medida de inventario'])
            ->syncRoles([$superusuario, $coordinador, $cajero]);

        Permission::firstOrCreate(['name' => 'inv_unidadesCrear'],
            ['descripcion' => 'crear unidades de medida de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_unidadesEditar'],
            ['descripcion' => 'editar unidades de medida de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_unidadesInactivar'],
            ['descripcion' => 'inactivar/eliminar unidades de medida de inventario'])
            ->syncRoles([$superusuario]);

        // ── Productos ───────────────────────────────────────────────────────────
        Permission::firstOrCreate(['name' => 'inv_productos'],
            ['descripcion' => 'ver catálogo de productos de inventario'])
            ->syncRoles([$superusuario, $coordinador, $cajero]);

        Permission::firstOrCreate(['name' => 'inv_productosCrear'],
            ['descripcion' => 'crear productos de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_productosEditar'],
            ['descripcion' => 'editar productos de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_productosInactivar'],
            ['descripcion' => 'inactivar/eliminar productos de inventario'])
            ->syncRoles([$superusuario]);

        Permission::firstOrCreate(['name' => 'inv_productosImportar'],
            ['descripcion' => 'importar catálogo de productos de inventario desde CSV'])
            ->syncRoles([$superusuario, $coordinador]);

        // ── Almacenes ───────────────────────────────────────────────────────────
        Permission::firstOrCreate(['name' => 'inv_almacenes'],
            ['descripcion' => 'ver almacenes de inventario'])
            ->syncRoles([$superusuario, $coordinador, $cajero]);

        Permission::firstOrCreate(['name' => 'inv_almacenesCrear'],
            ['descripcion' => 'crear almacenes de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_almacenesEditar'],
            ['descripcion' => 'editar almacenes de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_almacenesInactivar'],
            ['descripcion' => 'inactivar/eliminar almacenes de inventario'])
            ->syncRoles([$superusuario]);

        $this->command->info('Permisos del módulo Inventarios aplicados correctamente.');
    }
}
