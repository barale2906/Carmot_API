<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos de los plazos nuevos de pago (prórrogas) para estudiantes en mora.
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddProrrogaPagoPermissionsSeeder
 */
class AddProrrogaPagoPermissionsSeeder extends Seeder
{
    /**
     * Ejecuta el seeder de permisos.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = fn (array $nombres) => collect($nombres)
            ->map(fn (string $nombre) => Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']))
            ->all();

        Permission::firstOrCreate(['name' => 'fin_prorrogasPago'], ['descripcion' => 'ver plazos nuevos de pago'])
            ->syncRoles($roles(['superusuario', 'financiero', 'coordinador', 'auxiliar', 'profesor', 'alumno']));

        Permission::firstOrCreate(['name' => 'fin_prorrogaPagoCrear'], ['descripcion' => 'registrar plazo nuevo de pago'])
            ->syncRoles($roles(['superusuario', 'financiero', 'coordinador', 'auxiliar', 'profesor']));

        Permission::firstOrCreate(['name' => 'fin_prorrogaPagoCancelar'], ['descripcion' => 'cancelar plazo nuevo de pago y recibir avisos de incumplimiento'])
            ->syncRoles($roles(['superusuario', 'financiero', 'coordinador', 'auxiliar']));
    }
}
