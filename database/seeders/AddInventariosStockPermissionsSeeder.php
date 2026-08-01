<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos de Stock, Proveedores y Movimientos del módulo Inventarios.
 *
 * Ejecutar con: php artisan db:seed --class=AddInventariosStockPermissionsSeeder
 */
class AddInventariosStockPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            // Proveedores
            ['name' => 'inv_proveedores',         'descripcion' => 'Ver proveedores de inventario'],
            ['name' => 'inv_proveedoresCrear',     'descripcion' => 'Crear proveedores de inventario'],
            ['name' => 'inv_proveedoresEditar',    'descripcion' => 'Editar proveedores de inventario'],
            ['name' => 'inv_proveedoresInactivar', 'descripcion' => 'Inactivar/eliminar proveedores de inventario'],

            // Stock
            ['name' => 'inv_stock',        'descripcion' => 'Ver stock de inventario por almacén'],
            ['name' => 'inv_stockImportar','descripcion' => 'Importar stock inicial desde CSV'],

            // Movimientos
            ['name' => 'inv_movimientos',          'descripcion' => 'Ver documentos y movimientos de inventario'],
            ['name' => 'inv_movimientosRegistrar', 'descripcion' => 'Registrar documentos de movimiento de inventario'],
            ['name' => 'inv_movimientosAnular',    'descripcion' => 'Anular documentos de movimiento de inventario'],
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso['name']], $permiso);
        }

        $superusuario = Role::firstOrCreate(['name' => 'superusuario', 'guard_name' => 'web']);
        $superusuario->givePermissionTo(array_column($permisos, 'name'));

        $coordinador = Role::firstOrCreate(['name' => 'coordinador', 'guard_name' => 'web']);
        $coordinador->givePermissionTo([
            'inv_proveedores',
            'inv_proveedoresCrear',
            'inv_proveedoresEditar',
            'inv_stock',
            'inv_stockImportar',
            'inv_movimientos',
            'inv_movimientosRegistrar',
        ]);

        $cajero = Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
        $cajero->givePermissionTo([
            'inv_stock',
            'inv_movimientos',
            'inv_movimientosRegistrar',
        ]);

        $this->command->info('Permisos de Stock/Proveedores/Movimientos aplicados correctamente.');
    }
}
