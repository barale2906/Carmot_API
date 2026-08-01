<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Agrega los permisos de órdenes de compra del módulo Inventarios.
 */
class AddInventariosOcPermissionsSeeder extends Seeder
{
    /**
     * Ejecuta el seeder de permisos.
     *
     * @return void
     */
    public function run(): void
    {
        $permisos = [
            ['name' => 'inv_oc',         'descripcion' => 'Ver órdenes de compra de inventario'],
            ['name' => 'inv_ocCrear',    'descripcion' => 'Crear órdenes de compra de inventario'],
            ['name' => 'inv_ocEditar',   'descripcion' => 'Editar órdenes de compra en borrador'],
            ['name' => 'inv_ocEnviar',   'descripcion' => 'Enviar órdenes de compra al proveedor'],
            ['name' => 'inv_ocRecibir',  'descripcion' => 'Registrar recepción de mercancía en OC'],
            ['name' => 'inv_ocCancelar', 'descripcion' => 'Cancelar órdenes de compra abiertas'],
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso['name']], $permiso);
        }
    }
}
