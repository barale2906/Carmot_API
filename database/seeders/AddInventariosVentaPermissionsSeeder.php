<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos de precios, ventas, pedidos y entregas del módulo Inventarios.
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddInventariosVentaPermissionsSeeder
 */
class AddInventariosVentaPermissionsSeeder extends Seeder
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
        $cajero       = Role::firstOrCreate(['name' => 'cajero',       'guard_name' => 'web']);
        $coordinador  = Role::firstOrCreate(['name' => 'coordinador',  'guard_name' => 'web']);

        // ── Precios de productos ────────────────────────────────────────────────
        Permission::firstOrCreate(['name' => 'inv_precios'],
            ['descripcion' => 'Ver precios de inventario'])
            ->syncRoles([$superusuario, $coordinador, $cajero]);

        Permission::firstOrCreate(['name' => 'inv_preciosCrear'],
            ['descripcion' => 'Crear precios de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_preciosEditar'],
            ['descripcion' => 'Editar precios de inventario'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'inv_preciosEliminar'],
            ['descripcion' => 'Eliminar/restaurar precios de inventario'])
            ->syncRoles([$superusuario]);

        // ── Ventas ──────────────────────────────────────────────────────────────
        Permission::firstOrCreate(['name' => 'inv_ventas'],
            ['descripcion' => 'Ver ventas del módulo de inventario'])
            ->syncRoles([$superusuario, $coordinador, $cajero]);

        Permission::firstOrCreate(['name' => 'inv_ventasCrear'],
            ['descripcion' => 'Crear pedidos/ventas de inventario'])
            ->syncRoles([$superusuario, $cajero]);

        Permission::firstOrCreate(['name' => 'inv_ventasAbonar'],
            ['descripcion' => 'Abonar a pedidos de inventario activos'])
            ->syncRoles([$superusuario, $cajero]);

        // ── Pedidos ─────────────────────────────────────────────────────────────
        Permission::firstOrCreate(['name' => 'inv_pedidos'],
            ['descripcion' => 'Ver pedidos del módulo de inventario'])
            ->syncRoles([$superusuario, $coordinador, $cajero]);

        Permission::firstOrCreate(['name' => 'inv_pedidosCancelar'],
            ['descripcion' => 'Cancelar pedidos de inventario activos'])
            ->syncRoles([$superusuario, $cajero]);

        Permission::firstOrCreate(['name' => 'inv_pedidosAnular'],
            ['descripcion' => 'Anular pedidos de inventario con reintegro de stock'])
            ->syncRoles([$superusuario, $coordinador]);

        // ── Entregas ─────────────────────────────────────────────────────────────
        Permission::firstOrCreate(['name' => 'inv_entregas'],
            ['descripcion' => 'Ver entregas pendientes y necesidades de compra'])
            ->syncRoles([$superusuario, $coordinador, $cajero]);

        Permission::firstOrCreate(['name' => 'inv_entregasCompletar'],
            ['descripcion' => 'Completar entregas de ítems y kits pendientes'])
            ->syncRoles([$superusuario, $cajero]);
    }
}
