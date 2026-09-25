<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos del módulo Academico → Documentación.
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddDocumentacionPermissionsSeeder
 */
class AddDocumentacionPermissionsSeeder extends Seeder
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

        Permission::firstOrCreate(['name' => 'aca_docTipos'],
            ['descripcion' => 'Ver tipos de documento'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'aca_docTipoCrear'],
            ['descripcion' => 'Crear tipos de documento'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'aca_docTipoEditar'],
            ['descripcion' => 'Editar tipos de documento'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'aca_docTipoVariables'],
            ['descripcion' => 'Definir las variables habilitadas de un tipo de documento'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'aca_docTipoInactivar'],
            ['descripcion' => 'Inactivar y eliminar tipos de documento'])
            ->syncRoles([$superusuario]);

        Permission::firstOrCreate(['name' => 'aca_docPlantillas'],
            ['descripcion' => 'Ver versiones de plantilla de documentos'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'aca_docPlantillaCrear'],
            ['descripcion' => 'Crear versiones de plantilla de documentos'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'aca_docPlantillaEditar'],
            ['descripcion' => 'Editar versiones de plantilla en proceso'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'aca_docPlantillaAprobar'],
            ['descripcion' => 'Aprobar y activar versiones de plantilla'])
            ->syncRoles([$superusuario]);

        Permission::firstOrCreate(['name' => 'aca_docPlantillaClonar'],
            ['descripcion' => 'Clonar una versión de plantilla'])
            ->syncRoles([$superusuario, $coordinador]);

        Permission::firstOrCreate(['name' => 'aca_docPlantillaInactivar'],
            ['descripcion' => 'Inactivar y eliminar versiones de plantilla'])
            ->syncRoles([$superusuario]);

        $auxiliar = Role::firstOrCreate(['name' => 'auxiliar', 'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'aca_documentos'],
            ['descripcion' => 'Ver documentos generados'])
            ->syncRoles([$superusuario, $coordinador, $auxiliar]);

        Permission::firstOrCreate(['name' => 'aca_documentoGenerar'],
            ['descripcion' => 'Generar documentos desde una plantilla'])
            ->syncRoles([$superusuario, $coordinador, $auxiliar]);

        Permission::firstOrCreate(['name' => 'aca_documentoAnular'],
            ['descripcion' => 'Anular y eliminar documentos'])
            ->syncRoles([$superusuario, $coordinador]);
    }
}
