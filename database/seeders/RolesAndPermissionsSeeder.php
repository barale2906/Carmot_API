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

        // Create permissions Seguimiento
        Permission::create([
                    'name'=>'crm_seguimientos',
                    'descripcion'=>'ver seguimientos',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador,$auxiliar]);
        Permission::create([
                    'name'=>'crm_seguimientoCrear',
                    'descripcion'=>'crear seguimiento',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador,$auxiliar]);
        Permission::create([
                    'name'=>'crm_seguimientoEditar',
                    'descripcion'=>'editar seguimiento',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador,$auxiliar]);
        Permission::create([
                    'name'=>'crm_seguimientoInactivar',
                    'descripcion'=>'inactivar seguimiento',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);


        // Create permissions agenda
        Permission::create([
                    'name'=>'crm_agendas',
                    'descripcion'=>'ver agendas',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador,$auxiliar]);
        Permission::create([
                    'name'=>'crm_agendaCrear',
                    'descripcion'=>'crear agenda',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador,$auxiliar]);
        Permission::create([
                    'name'=>'crm_agendaEditar',
                    'descripcion'=>'editar agenda',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador,$auxiliar]);
        Permission::create([
                    'name'=>'crm_agendaInactivar',
                    'descripcion'=>'inactivar agenda',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);

        // Create permissions curso
        Permission::create([
                    'name'=>'aca_cursos',
                    'descripcion'=>'ver cursos',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_cursoCrear',
                    'descripcion'=>'crear curso',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_cursoEditar',
                    'descripcion'=>'editar curso',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_cursoInactivar',
                    'descripcion'=>'inactivar curso',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);

        // Create permissions modulo
        Permission::create([
                    'name'=>'aca_modulos',
                    'descripcion'=>'ver modulos',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_moduloCrear',
                    'descripcion'=>'crear modulo',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_moduloEditar',
                    'descripcion'=>'editar modulo',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_moduloInactivar',
                    'descripcion'=>'inactivar modulo',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);

        // Create permissions modulo
        Permission::create([
                    'name'=>'aca_topicos',
                    'descripcion'=>'ver topicos',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_topicoCrear',
                    'descripcion'=>'crear topico',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_topicoEditar',
                    'descripcion'=>'editar topico',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_topicoInactivar',
                    'descripcion'=>'inactivar topico',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);

        // Create permissions poblaciones
        Permission::create([
            'name'=>'co_poblaciones',
            'descripcion'=>'ver poblaciones',
            //'modulo'=>'configuracion'
            ])->syncRoles([$Superusuario,$financiero,$coordinador,$auxiliar]);

        // Create permissions sede
        Permission::create([
            'name'=>'co_sedes',
            'descripcion'=>'ver sedes',
            //'modulo'=>'configuracion'
            ])->syncRoles([$Superusuario,$financiero,$coordinador,$auxiliar]);
        Permission::create([
                    'name'=>'co_sedeCrear',
                    'descripcion'=>'crear sede',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'co_sedeEditar',
                    'descripcion'=>'editar sede',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'co_sedeInactivar',
                    'descripcion'=>'inactivar sede',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);

        // Create permissions area
        Permission::create([
            'name'=>'co_areas',
            'descripcion'=>'ver areas',
            //'modulo'=>'configuracion'
            ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'co_areaCrear',
                    'descripcion'=>'crear area',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'co_areaEditar',
                    'descripcion'=>'editar area',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'co_areaInactivar',
                    'descripcion'=>'inactivar area',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);

        // Create permissions horario
        Permission::create([
                            'name'=>'co_horarios',
                            'descripcion'=>'ver horarios',
                            //'modulo'=>'configuracion'
                            ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'co_horarioCrear',
                    'descripcion'=>'crear horario',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'co_horarioEditar',
                    'descripcion'=>'editar horario',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'co_horarioInactivar',
                    'descripcion'=>'inactivar horario',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);

        // Create permissions grupos
        Permission::create([
            'name'=>'aca_grupos',
            'descripcion'=>'ver grupos',
            //'modulo'=>'configuracion'
            ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_grupoCrear',
                    'descripcion'=>'crear grupo',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_grupoEditar',
                    'descripcion'=>'editar grupo',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_grupoInactivar',
                    'descripcion'=>'inactivar grupo',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);

        // Create permissions ciclos
        Permission::create([
                    'name'=>'aca_ciclos',
                    'descripcion'=>'ver ciclos',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_cicloCrear',
                    'descripcion'=>'crear ciclo',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_cicloEditar',
                    'descripcion'=>'editar ciclo',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_cicloInactivar',
                    'descripcion'=>'inactivar ciclo',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);

        // Create permissions temas
        Permission::create([
                    'name'=>'aca_temas',
                    'descripcion'=>'ver temas',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_temaCrear',
                    'descripcion'=>'crear tema',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_temaEditar',
                    'descripcion'=>'editar tema',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_temaInactivar',
                    'descripcion'=>'inactivar tema',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);

        // Create permissions programacion
        Permission::create([
                    'name'=>'aca_programaciones',
                    'descripcion'=>'ver programaciones',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_programacionCrear',
                    'descripcion'=>'crear programacion',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_programacionEditar',
                    'descripcion'=>'editar programacion',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_programacionInactivar',
                    'descripcion'=>'inactivar programacion',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);


        // Create permissions matriculas
        Permission::create([
                    'name'=>'aca_matriculas',
                    'descripcion'=>'ver matriculas',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_matriculaCrear',
                    'descripcion'=>'crear matricula',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_matriculaEditar',
                    'descripcion'=>'editar matricula',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);
        Permission::create([
                    'name'=>'aca_matriculaInactivar',
                    'descripcion'=>'inactivar matricula',
                    //'modulo'=>'configuracion'
                    ])->syncRoles([$Superusuario,$financiero,$coordinador]);


            }


}
