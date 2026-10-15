<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos de órdenes de compra del módulo Inventarios y los asigna
 * a los roles que gestionan compras (superusuario y coordinador).
 * Seguro de correr múltiples veces (usa firstOrCreate + syncRoles).
 *
 * Ejecutar con: php artisan db:seed --class=AddInventariosOcPermissionsSeeder
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
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $superusuario = Role::firstOrCreate(['name' => 'superusuario', 'guard_name' => 'web']);
        $coordinador  = Role::firstOrCreate(['name' => 'coordinador',  'guard_name' => 'web']);

        $gestion = [$superusuario, $coordinador];

        $permisos = [
            'inv_oc'         => ['Ver órdenes de compra de inventario', $gestion],
            'inv_ocCrear'    => ['Crear órdenes de compra de inventario', $gestion],
            'inv_ocEditar'   => ['Editar órdenes de compra en borrador', $gestion],
            'inv_ocEnviar'   => ['Enviar órdenes de compra al proveedor', $gestion],
            'inv_ocRecibir'  => ['Registrar recepción de mercancía en OC', $gestion],
            'inv_ocCancelar' => ['Cancelar órdenes de compra abiertas', [$superusuario]],
        ];

        foreach ($permisos as $nombre => [$descripcion, $roles]) {
            Permission::firstOrCreate(['name' => $nombre], ['descripcion' => $descripcion])->syncRoles($roles);
        }

        $this->command?->info('Permisos de órdenes de compra aplicados correctamente.');
    }
}
