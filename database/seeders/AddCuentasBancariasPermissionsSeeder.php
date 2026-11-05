<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos del catálogo de cuentas bancarias del instituto (cuentas donde se
 * recibe el dinero de las transferencias, por sede). Seguro de correr múltiples veces.
 *
 * Ejecutar con: php artisan db:seed --class=AddCuentasBancariasPermissionsSeeder
 */
class AddCuentasBancariasPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $superusuario = Role::findByName('superusuario');
        $financiero = Role::findByName('financiero');
        $coordinador = Role::findByName('coordinador');

        $gestion = [$superusuario, $financiero, $coordinador];

        $permisos = [
            'co_cuentasBancarias' => ['ver cuentas bancarias del instituto', $gestion],
            'co_cuentasBancariasCrear' => ['crear cuenta bancaria del instituto', $gestion],
            'co_cuentasBancariasEditar' => ['editar cuenta bancaria del instituto', $gestion],
            'co_cuentasBancariasInactivar' => ['eliminar o restaurar cuenta bancaria del instituto', [$superusuario]],
        ];

        foreach ($permisos as $nombre => [$descripcion, $roles]) {
            Permission::firstOrCreate(['name' => $nombre], ['descripcion' => $descripcion])->syncRoles($roles);
        }

        $this->command?->info('Permisos de cuentas bancarias aplicados correctamente.');
    }
}
