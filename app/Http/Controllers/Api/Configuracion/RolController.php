<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\StoreRolRequest;
use App\Http\Requests\Api\Configuracion\SyncPermisosRolRequest;
use App\Http\Requests\Api\Configuracion\UpdateRolRequest;
use App\Http\Resources\Api\Configuracion\RolResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Controlador para la gestión de roles del sistema.
 *
 * Permite crear, consultar, actualizar y eliminar roles, así como
 * gestionar sus permisos de forma individual o mediante sync completo.
 * Integra el paquete Spatie Permission para el manejo de autorización.
 *
 * @package App\Http\Controllers\Api\Configuracion
 */
class RolController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:co_roles')->only(['index', 'show']);
        $this->middleware('permission:co_rolCrear')->only(['store']);
        $this->middleware('permission:co_rolEditar')->only(['update', 'toggleStatus']);
        $this->middleware('permission:co_rolEliminar')->only(['destroy']);
        $this->middleware('permission:co_rolPermisos')->only(['syncPermisos', 'addPermiso', 'removePermiso']);
    }

    /**
     * Listar todos los roles con sus permisos y contadores.
     *
     * @queryParam search string Filtrar por nombre de rol. Ejemplo: profesor
     * @queryParam status boolean Filtrar por estado (1 activo, 0 inactivo). Ejemplo: 1
     * @queryParam per_page int Elementos por página (0 = sin paginación). Ejemplo: 15
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Role::addSelect([
                'permissions_count' => DB::table('role_has_permissions')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('role_id', 'roles.id'),
                'users_count' => DB::table('model_has_roles')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('role_id', 'roles.id'),
            ]);

            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            }

            if ($request->has('status')) {
                $query->where('status', filter_var($request->input('status'), FILTER_VALIDATE_BOOLEAN));
            }

            $perPage = (int) $request->input('per_page', 0);

            if ($perPage === 0) {
                $roles = $query->with('permissions')->orderBy('name')->get();
                return RolResource::collection($roles);
            }

            $roles = $query->with('permissions')->orderBy('name')->paginate($perPage);

            return RolResource::collection($roles);

        } catch (\Exception $e) {
            Log::error('Error al obtener los roles', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al obtener los roles',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear un nuevo rol.
     *
     * @param  \App\Http\Requests\Api\Configuracion\StoreRolRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRolRequest $request)
    {
        $rol = Role::create([
            'name'       => $request->name,
            'guard_name' => $request->input('guard_name', 'web'),
            'status'     => $request->input('status', true),
        ]);

        if ($request->has('permissions')) {
            $rol->syncPermissions($request->permissions);
        }

        $this->cargarContadores($rol);

        return (new RolResource($rol))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Mostrar un rol específico con sus permisos.
     *
     * @param  \Spatie\Permission\Models\Role  $rol
     * @return \App\Http\Resources\Api\Configuracion\RolResource
     */
    public function show(Role $rol)
    {
        $this->cargarContadores($rol);

        return new RolResource($rol);
    }

    /**
     * Actualizar un rol existente.
     *
     * @param  \App\Http\Requests\Api\Configuracion\UpdateRolRequest  $request
     * @param  \Spatie\Permission\Models\Role  $rol
     * @return \App\Http\Resources\Api\Configuracion\RolResource
     */
    public function update(UpdateRolRequest $request, Role $rol)
    {
        $rol->fill($request->only(['name', 'status']));
        $rol->save();

        if ($request->has('permissions')) {
            $rol->syncPermissions($request->permissions);
        }

        $this->cargarContadores($rol);

        return new RolResource($rol);
    }

    /**
     * Eliminar un rol. No se puede eliminar si tiene usuarios asignados.
     *
     * @param  \Spatie\Permission\Models\Role  $rol
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Role $rol)
    {
        $usersCount = DB::table('model_has_roles')->where('role_id', $rol->id)->count();

        if ($usersCount > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el rol porque tiene usuarios asignados.',
            ], 422);
        }

        $rol->delete();

        return response()->json(['message' => 'Rol eliminado exitosamente.'], 200);
    }

    /**
     * Alternar el estado activo/inactivo de un rol.
     *
     * @param  \Spatie\Permission\Models\Role  $rol
     * @return \App\Http\Resources\Api\Configuracion\RolResource
     */
    public function toggleStatus(Role $rol)
    {
        $rol->status = !$rol->status;
        $rol->save();

        $this->cargarContadores($rol);

        return new RolResource($rol);
    }

    /**
     * Reemplazar completamente los permisos de un rol (sync).
     * Enviar un array vacío [] para quitar todos los permisos.
     *
     * @param  \App\Http\Requests\Api\Configuracion\SyncPermisosRolRequest  $request
     * @param  \Spatie\Permission\Models\Role  $rol
     * @return \App\Http\Resources\Api\Configuracion\RolResource
     */
    public function syncPermisos(SyncPermisosRolRequest $request, Role $rol)
    {
        $rol->syncPermissions($request->permissions);

        $this->cargarContadores($rol);

        return new RolResource($rol);
    }

    /**
     * Agregar un permiso individual a un rol sin afectar los demás.
     *
     * @param  \Spatie\Permission\Models\Role        $rol
     * @param  \Spatie\Permission\Models\Permission  $permission
     * @return \App\Http\Resources\Api\Configuracion\RolResource|\Illuminate\Http\JsonResponse
     */
    public function addPermiso(Role $rol, Permission $permission)
    {
        if ($rol->hasPermissionTo($permission)) {
            return response()->json([
                'message' => "El rol '{$rol->name}' ya tiene el permiso '{$permission->name}'.",
            ], 409);
        }

        $rol->givePermissionTo($permission);

        $this->cargarContadores($rol);

        return new RolResource($rol);
    }

    /**
     * Eliminar un permiso individual de un rol.
     *
     * @param  \Spatie\Permission\Models\Role        $rol
     * @param  \Spatie\Permission\Models\Permission  $permission
     * @return \App\Http\Resources\Api\Configuracion\RolResource|\Illuminate\Http\JsonResponse
     */
    public function removePermiso(Role $rol, Permission $permission)
    {
        if (!$rol->hasPermissionTo($permission)) {
            return response()->json([
                'message' => "El rol '{$rol->name}' no tiene el permiso '{$permission->name}'.",
            ], 409);
        }

        $rol->revokePermissionTo($permission);

        $this->cargarContadores($rol);

        return new RolResource($rol);
    }

    /**
     * Carga permisos y calcula contadores evitando loadCount/withCount
     * sobre modelos de Spatie (que usa morphedByMany y falla sin guard_name
     * en el contexto del query builder).
     */
    private function cargarContadores(Role $rol): void
    {
        $rol->load('permissions');
        $rol->permissions_count = $rol->permissions->count();
        $rol->users_count = DB::table('model_has_roles')->where('role_id', $rol->id)->count();
    }
}
