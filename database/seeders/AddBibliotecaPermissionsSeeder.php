<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos del módulo Academico → Biblioteca.
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddBibliotecaPermissionsSeeder
 */
class AddBibliotecaPermissionsSeeder extends Seeder
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
        $auxiliar     = Role::firstOrCreate(['name' => 'auxiliar',     'guard_name' => 'web']);
        $profesor     = Role::firstOrCreate(['name' => 'profesor',     'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'ac_biblioteca'],
            ['descripcion' => 'Ver documentos de la biblioteca académica'])
            // El profesor consulta y descarga el material de sus cursos (B43)
            ->syncRoles([$superusuario, $coordinador, $auxiliar, $profesor]);

        Permission::firstOrCreate(['name' => 'ac_bibliotecaCrear'],
            ['descripcion' => 'Subir documentos a la biblioteca académica'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'ac_bibliotecaEditar'],
            ['descripcion' => 'Editar documentos de la biblioteca académica'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'ac_bibliotecaInactivar'],
            ['descripcion' => 'Eliminar y restaurar documentos de la biblioteca académica'])
            ->syncRoles([$superusuario]);
    }
}
