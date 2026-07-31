<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos del módulo de transferencias bancarias a una BD existente.
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddTransferenciaPermissionsSeeder
 */
class AddTransferenciaPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $superusuario = Role::findByName('superusuario');
        $financiero   = Role::findByName('financiero');
        $coordinador  = Role::findByName('coordinador');
        $auxiliar     = Role::findByName('auxiliar');

        // Permisos del catálogo de Bancos
        Permission::firstOrCreate(['name' => 'co_bancos'],          ['descripcion' => 'ver bancos'])
            ->syncRoles([$superusuario, $financiero, $coordinador, $auxiliar]);

        Permission::firstOrCreate(['name' => 'co_bancosCrear'],     ['descripcion' => 'crear banco'])
            ->syncRoles([$superusuario, $financiero, $coordinador]);

        Permission::firstOrCreate(['name' => 'co_bancosEditar'],    ['descripcion' => 'editar banco'])
            ->syncRoles([$superusuario, $financiero, $coordinador]);

        Permission::firstOrCreate(['name' => 'co_bancosInactivar'], ['descripcion' => 'inactivar banco'])
            ->syncRoles([$superusuario]);

        // Permiso de aprobación de recibos por transferencia
        Permission::firstOrCreate(['name' => 'fin_reciboPagoAprobar'], [
            'descripcion' => 'aprobar o rechazar recibos de pago por transferencia bancaria',
        ])->syncRoles([$superusuario, $financiero]);

        $this->command->info('Permisos de transferencias bancarias aplicados correctamente.');
    }
}
