<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos del módulo de traslados de estudiantes (cambio de programación, de curso
 * o de sede). El acuerdo de pago y la condonación del cobro quedan solo para el superusuario.
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddTrasladosPermissionsSeeder
 */
class AddTrasladosPermissionsSeeder extends Seeder
{
    /**
     * Ejecuta el seeder de permisos.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $superusuario = Role::firstOrCreate(['name' => 'superusuario', 'guard_name' => 'web']);
        $coordinador = Role::firstOrCreate(['name' => 'coordinador',  'guard_name' => 'web']);

        $permisos = [
            'tra_traslados' => ['Ver traslados de estudiantes, sus opciones de destino y simularlos', [$superusuario, $coordinador]],
            'tra_trasladoCrear' => ['Registrar traslados de estudiantes (programación, curso o sede)', [$superusuario, $coordinador]],
            'tra_trasladoPagoAcordado' => ['Acordar el plan de pagos de un traslado y condonar su cobro', [$superusuario]],
            'tra_trasladoNivelacion' => ['Marcar como resuelta la nivelación pendiente de un traslado', [$superusuario, $coordinador]],
        ];

        foreach ($permisos as $nombre => [$descripcion, $roles]) {
            Permission::firstOrCreate(['name' => $nombre], ['descripcion' => $descripcion])->syncRoles($roles);
        }
    }
}
