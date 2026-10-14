<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos del módulo de activos fijos (activos y sus categorías).
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddActivosPermissionsSeeder
 */
class AddActivosPermissionsSeeder extends Seeder
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

        $permisos = [
            'act_activos'            => ['Ver los activos fijos de sus sedes', [$superusuario, $coordinador]],
            'act_activoCrear'        => ['Registrar activos fijos', [$superusuario, $coordinador]],
            'act_activoEditar'       => ['Editar activos fijos y cambiar su estado (incluida la baja)', [$superusuario, $coordinador]],
            'act_activoTrasladar'    => ['Trasladar activos entre sedes y áreas', [$superusuario, $coordinador]],
            'act_activoInactivar'    => ['Eliminar y restaurar activos fijos', [$superusuario]],
            'act_categorias'         => ['Ver las categorías de activos', [$superusuario, $coordinador]],
            'act_categoriaCrear'     => ['Crear categorías de activos', [$superusuario, $coordinador]],
            'act_categoriaEditar'    => ['Editar categorías de activos', [$superusuario, $coordinador]],
            'act_categoriaInactivar' => ['Eliminar y restaurar categorías de activos', [$superusuario]],
        ];

        foreach ($permisos as $nombre => [$descripcion, $roles]) {
            Permission::firstOrCreate(['name' => $nombre], ['descripcion' => $descripcion])->syncRoles($roles);
        }
    }
}
