<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $Superusuario = Role::create(['name' => 'superusuario']);
        $financiero = Role::create(['name' => 'financiero']);
        $coordinador = Role::create(['name' => 'coordinador']);
        $profesor = Role::create(['name' => 'profesor']);
        $auxiliar = Role::create(['name' => 'auxiliar']);
        $alumno = Role::create(['name' => 'alumno']);

        // Create permissions
        Permission::create([
                    'name'=>'co_users',
                    'descripcion'=>'ver usuarios',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario]);
        Permission::create([
                    'name'=>'co_userCrear',
                    'descripcion'=>'crear Usuario',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario]);
        Permission::create([
                    'name'=>'co_userEditar',
                    'descripcion'=>'editar usuario',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario]);
        Permission::create([
                    'name'=>'co_userInactivar',
                    'descripcion'=>'inactivar usuario',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario]);

        Permission::create([
                    'name'=>'co_usersPerfil',
                    'descripcion'=>'perfil usuarios',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador,$profesor,$auxiliar,$alumno]);

                    // Create permissions Referido
                    Permission::create([
                                'name'=>'crm_referidos',
                                'descripcion'=>'ver referidos',
                                //'modulo'=>'configuracion'
                                ])->syncRoles([$Superusuario,$financiero,$coordinador,$auxiliar]);
                    Permission::create([
                                'name'=>'crm_referidoCrear',
                                'descripcion'=>'crear referido',
                                //'modulo'=>'configuracion'
                                ])->syncRoles([$Superusuario,$financiero,$coordinador,$auxiliar]);
                    Permission::create([
                                'name'=>'crm_referidoEditar',
                                'descripcion'=>'editar referido',
                                //'modulo'=>'configuracion'
                                ])->syncRoles([$Superusuario,$financiero,$coordinador,$auxiliar]);
                    Permission::create([
                                'name'=>'crm_referidoInactivar',
                                'descripcion'=>'inactivar referido',
                                //'modulo'=>'configuracion'
                                ])->syncRoles([$Superusuario,$financiero,$coordinador]);
    }
}
