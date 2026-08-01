<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Agrega los permisos de precios, ventas, pedidos y entregas del módulo Inventarios.
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
        $permisos = [
            // Precios de productos
            ['name' => 'inv_precios',         'descripcion' => 'Ver precios de inventario'],
            ['name' => 'inv_preciosCrear',     'descripcion' => 'Crear precios de inventario'],
            ['name' => 'inv_preciosEditar',    'descripcion' => 'Editar precios de inventario'],
            ['name' => 'inv_preciosEliminar',  'descripcion' => 'Eliminar/restaurar precios de inventario'],

            // Ventas
            ['name' => 'inv_ventas',           'descripcion' => 'Ver ventas del módulo de inventario'],
            ['name' => 'inv_ventasCrear',      'descripcion' => 'Crear pedidos/ventas de inventario'],
            ['name' => 'inv_ventasAbonar',     'descripcion' => 'Abonar a pedidos de inventario activos'],

            // Pedidos
            ['name' => 'inv_pedidos',          'descripcion' => 'Ver pedidos del módulo de inventario'],
            ['name' => 'inv_pedidosCancelar',  'descripcion' => 'Cancelar pedidos de inventario activos'],

            // Entregas
            ['name' => 'inv_entregas',         'descripcion' => 'Ver entregas pendientes y necesidades de compra'],
            ['name' => 'inv_entregasCompletar','descripcion' => 'Completar entregas de ítems y kits pendientes'],
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso['name']], $permiso);
        }
    }
}
