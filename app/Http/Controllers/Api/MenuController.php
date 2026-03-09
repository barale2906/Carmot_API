<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\MenuResponseResource;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * Constructor: protege todas las rutas con autenticación Sanctum.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Obtiene el menú de navegación según los permisos del usuario.
     *
     * @param Request $request
     * @return MenuResponseResource
     */
    public function index(Request $request): MenuResponseResource
    {
        $user = $request->user();
        $menu = [];

        // Dashboard - siempre visible para usuarios autenticados
        $menu[] = [
            'id' => 'dashboard',
            'title' => 'Dashboard',
            'icon' => 'dashboard',
            'route' => '/dashboard',
            'children' => [],
        ];

        // Configuración - módulo con varios submenús
        if ($this->hasAnyPermission($user, ['co_users', 'co_roles_permisos', 'co_poblaciones', 'co_sedes'])) {
            $configChildren = [];

            // Usuarios
            if ($user->can('co_users')) {
                $configChildren[] = [
                    'id' => 'configuracion-usuarios',
                    'title' => 'Usuarios',
                    'icon' => 'people',
                    'route' => '/configuracion/usuarios',
                    'permission' => 'co_users',
                ];
            }

            // Roles y permisos
            if ($user->can('co_roles_permisos')) {
                $configChildren[] = [
                    'id' => 'configuracion-roles-permisos',
                    'title' => 'Roles y permisos',
                    'icon' => 'security',
                    'route' => '/configuracion/roles-permisos',
                    'permission' => 'co_roles_permisos',
                ];
            }

            // Poblaciones
            if ($user->can('co_poblaciones')) {
                $configChildren[] = [
                    'id' => 'configuracion-poblaciones',
                    'title' => 'Poblaciones',
                    'icon' => 'groups',
                    'route' => '/configuracion/poblaciones',
                    'permission' => 'co_poblaciones',
                ];
            }

            // Sedes
            if ($user->can('co_sedes')) {
                $configChildren[] = [
                    'id' => 'configuracion-sedes',
                    'title' => 'Sedes',
                    'icon' => 'location_city',
                    'route' => '/configuracion/sedes',
                    'permission' => 'co_sedes',
                ];
            }

            if (!empty($configChildren)) {
                $menu[] = [
                    'id' => 'configuracion',
                    'title' => 'Configuración',
                    'icon' => 'settings',
                    'route' => '/configuracion',
                    'children' => $configChildren,
                ];
            }
        }

        // CRM - módulo con varios submenús
        if ($this->hasAnyPermission($user, ['crm_seguimientos', 'crm_agendas'])) {
            $crmChildren = [];

            // Seguimientos
            if ($user->can('crm_seguimientos')) {
                $crmChildren[] = [
                    'id' => 'crm-seguimientos',
                    'title' => 'Seguimientos',
                    'icon' => 'track_changes',
                    'route' => '/crm/seguimientos',
                    'permission' => 'crm_seguimientos',
                ];
            }

            // Agendas
            if ($user->can('crm_agendas')) {
                $crmChildren[] = [
                    'id' => 'crm-agendas',
                    'title' => 'Agendas',
                    'icon' => 'calendar_today',
                    'route' => '/crm/agendas',
                    'permission' => 'crm_agendas',
                ];
            }

            if (!empty($crmChildren)) {
                $menu[] = [
                    'id' => 'crm',
                    'title' => 'CRM',
                    'icon' => 'contacts',
                    'route' => '/crm',
                    'children' => $crmChildren,
                ];
            }
        }

        // Académico - módulo con submenús según la estructura requerida
        if ($this->hasAnyPermission($user, [
            'aca_programas', 'aca_programaciones', 'aca_matriculas', 'aca_cursos'
        ])) {
            $academicoChildren = [];

            // Gestión de programas
            if ($user->can('aca_programas')) {
                $academicoChildren[] = [
                    'id' => 'academico-programas',
                    'title' => 'Gestión de programas',
                    'icon' => 'schema',
                    'route' => '/academico/programas',
                    'permission' => 'aca_programas',
                ];
            }

            // Programaciones
            if ($user->can('aca_programaciones')) {
                $academicoChildren[] = [
                    'id' => 'academico-programaciones',
                    'title' => 'Programaciones',
                    'icon' => 'event_note',
                    'route' => '/academico/programaciones',
                    'permission' => 'aca_programaciones',
                ];
            }

            // Matrículas
            if ($user->can('aca_matriculas')) {
                $academicoChildren[] = [
                    'id' => 'academico-matriculas',
                    'title' => 'Matrículas',
                    'icon' => 'assignment_ind',
                    'route' => '/academico/matriculas',
                    'permission' => 'aca_matriculas',
                ];
            }

            // Gestión de cursos
            if ($user->can('aca_cursos')) {
                $academicoChildren[] = [
                    'id' => 'academico-cursos',
                    'title' => 'Gestión de cursos',
                    'icon' => 'school',
                    'route' => '/academico/cursos',
                    'permission' => 'aca_cursos',
                ];
            }

            if (!empty($academicoChildren)) {
                $menu[] = [
                    'id' => 'academico',
                    'title' => 'Académico',
                    'icon' => 'book',
                    'route' => '/academico',
                    'children' => $academicoChildren,
                ];
            }
        }

        // Financiero - módulo con varios submenús
        if ($this->hasAnyPermission($user, [
            'fin_productos', 'fin_listas_precios', 'fin_conceptos_pago', 'fin_recibos_pago'
        ])) {
            $financieroChildren = [];

            // Productos
            if ($user->can('fin_productos')) {
                $financieroChildren[] = [
                    'id' => 'financiero-productos',
                    'title' => 'Productos',
                    'icon' => 'inventory_2',
                    'route' => '/financiero/productos',
                    'permission' => 'fin_productos',
                ];
            }

            // Listas de precios
            if ($user->can('fin_listas_precios')) {
                $financieroChildren[] = [
                    'id' => 'financiero-listas-precios',
                    'title' => 'Listas de precios',
                    'icon' => 'list_alt',
                    'route' => '/financiero/listas-precios',
                    'permission' => 'fin_listas_precios',
                ];
            }

            // Conceptos de pago
            if ($user->can('fin_conceptos_pago')) {
                $financieroChildren[] = [
                    'id' => 'financiero-conceptos-pago',
                    'title' => 'Conceptos de pago',
                    'icon' => 'payments',
                    'route' => '/financiero/conceptos-pago',
                    'permission' => 'fin_conceptos_pago',
                ];
            }

            // Recibos de pago
            if ($user->can('fin_recibos_pago')) {
                $financieroChildren[] = [
                    'id' => 'financiero-recibos-pago',
                    'title' => 'Recibos de pago',
                    'icon' => 'receipt',
                    'route' => '/financiero/recibos-pago',
                    'permission' => 'fin_recibos_pago',
                ];
            }

            if (!empty($financieroChildren)) {
                $menu[] = [
                    'id' => 'financiero',
                    'title' => 'Financiero',
                    'icon' => 'account_balance',
                    'route' => '/financiero',
                    'children' => $financieroChildren,
                ];
            }
        }

        // Inventario - módulo marcado como en desarrollo
        if ($this->hasAnyPermission($user, ['inv_modulo'])) {
            $menu[] = [
                'id' => 'inventario',
                'title' => 'Inventario',
                'icon' => 'warehouse',
                'route' => '/inventario',
                'children' => [
                    [
                        'id' => 'inventario-desarrollo',
                        'title' => 'En desarrollo',
                        'icon' => 'construction',
                        'route' => '/inventario/desarrollo',
                        'permission' => 'inv_modulo',
                        'disabled' => true,
                    ]
                ],
            ];
        }

        return new MenuResponseResource($menu);
    }

    /**
     * Verifica si el usuario tiene al menos uno de los permisos especificados.
     *
     * @param \App\Models\User $user
     * @param array $permissions
     * @return bool
     */
    private function hasAnyPermission($user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }
        return false;
    }
}
