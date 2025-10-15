<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\StoreUserRequest;
use App\Http\Requests\Api\Configuracion\UpdateUserRequest;
use App\Http\Resources\Api\Configuracion\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:co_users')->only(['index', 'show']);
        $this->middleware('permission:co_userCrear')->only(['store']);
        $this->middleware('permission:co_userEditar')->only(['update']);
        $this->middleware('permission:co_userInactivar')->only(['destroy']);

        $this->middleware('role:superusuario')->only(['index', 'show', 'store', 'update', 'destroy']);
    }

    /**
     * Mostrar una lista de usuarios.
     * @queryParam page int Número de página. Ejemplo: 1
     * @queryParam per_page int Número de elementos por página. Ejemplo: 15
     * @queryParam search string Buscar usuarios por nombre o correo electrónico. Ejemplo: John Doe
     *
     * @apiResourceCollection App\Http\Resources\UserResource
     * @apiResourceModel App\Models\User
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        try {
            $query = User::query();

            if ($request->has('search') && !empty($request->input('search'))) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('documento', 'like', "%{$search}%");
                });
            }

            // Preparar relaciones - básicas por defecto
            $relations = $request->has('with')
                ? explode(',', $request->with)
                : ['roles', 'permissions', 'grupos', 'cursos', 'gestores', 'agendadores', 'seguimientos'];

            $users = $query->with($relations)->paginate($request->input('per_page', 15));

            return UserResource::collection($users);

        } catch (\Exception $e) {
            Log::error("Error al realizar la consulta", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al obtener los usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Almacenar un usuario recién creado en el almacenamiento.
     *
     * @apiResource App\Http\Resources\UserResource
     * @apiResourceModel App\Models\User
     *
     * @param  \App\Http\Requests\StoreUserRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreUserRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'documento' => $request->documento,
        ]);

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }
        if ($request->has('permissions')) {
            $user->syncPermissions($request->permissions);
        }

        // Sincronizar cursos si se proporcionan
        if ($request->has('cursos')) {
            $user->cursos()->sync($request->cursos);
        }

        return (new UserResource($user->load('roles', 'permissions', 'cursos')))
                ->response()
                ->setStatusCode(201); // 201 Created
    }

    /**
     * Mostrar el usuario especificado.
     *
     * @apiResource App\Http\Resources\UserResource
     * @apiResourceModel App\Models\User
     *
     * @param  \App\Models\User  $user
     * @return \App\Http\Resources\UserResource
     */
    public function show(Request $request, User $user)
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['roles', 'permissions', 'grupos', 'cursos', 'gestores', 'agendadores', 'seguimientos'];

        // Cargar relaciones y contadores
        $user->load($relations);
        $user->loadCount(['grupos', 'cursos', 'gestores', 'agendadores', 'seguimientos']);

        return new UserResource($user);
    }

    /**
     * Actualizar el usuario especificado en el almacenamiento.
     *
     * @apiResource App\Http\Resources\UserResource
     * @apiResourceModel App\Models\User
     *
     * @param  \App\Http\Requests\UpdateUserRequest  $request
     * @param  \App\Models\User  $user
     * @return \App\Http\Resources\UserResource
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $user->fill($request->except('password'));

        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }
        if ($request->has('permissions')) {
            $user->syncPermissions($request->permissions);
        }

        // Sincronizar cursos si se proporcionan
        if ($request->has('cursos')) {
            $user->cursos()->sync($request->cursos);
        }

        // Cargar relaciones y contadores
        $user->load(['roles', 'permissions', 'cursos']);
        $user->loadCount(['grupos', 'cursos', 'gestores', 'agendadores', 'seguimientos']);

        return new UserResource($user);
    }

    /**
     * Inactivar el usuario especificado.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(User $user)
    {
        // Verificar si tiene relaciones asociadas
        $hasRelations = $user->grupos()->count() > 0 ||
                       $user->cursos()->count() > 0 ||
                       $user->gestores()->count() > 0 ||
                       $user->agendadores()->count() > 0 ||
                       $user->seguimientos()->count() > 0;

        if ($hasRelations) {
            return response()->json([
                'message' => 'No se puede eliminar el usuario porque tiene relaciones asociadas (grupos, cursos, gestores, agendadores o seguimientos).',
            ], 422);
        }

        // Verifica si el usuario ya está "eliminado suavemente"
        if ($user->trashed()) {
            return response()->json(['message' => 'El usuario ya está inactivo.'], 409); // 409 Conflict
        }

        $user->delete(); // Esto establecerá 'deleted_at'

        return response()->json(['message' => 'Usuario inactivado exitosamente.'], 200); // 200 OK o 204 No Content
    }

    // Opcional: Método para restaurar un usuario
    /**
     * Restaurar el usuario especificado.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        // Necesitas buscar usuarios "eliminados suavemente"
        $user = User::onlyTrashed()->findOrFail($id);

        $user->restore(); // Esto establecerá 'deleted_at' a null

        return response()->json(['message' => 'Usuario restaurado exitosamente.'], 200);
    }

    // Opcional: Método para eliminar permanentemente (force delete)
    /**
     * Eliminar permanentemente el usuario especificado.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceDelete($id)
    {
        $user = User::onlyTrashed()->findOrFail($id); // Busca solo entre los eliminados suavemente

        // Verificar si tiene relaciones asociadas
        $hasRelations = $user->grupos()->withTrashed()->count() > 0 ||
                       $user->cursos()->withTrashed()->count() > 0 ||
                       $user->gestores()->withTrashed()->count() > 0 ||
                       $user->agendadores()->withTrashed()->count() > 0 ||
                       $user->seguimientos()->withTrashed()->count() > 0;

        if ($hasRelations) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el usuario porque tiene relaciones asociadas (grupos, cursos, gestores, agendadores o seguimientos).',
            ], 422);
        }

        $user->forceDelete(); // Elimina permanentemente

        return response()->json(null, 204);
    }

    /**
     * Obtiene las opciones de filtros disponibles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function filters()
    {
        $roles = \Spatie\Permission\Models\Role::select('id', 'name')->get();
        $profesores = User::role('profesor')->select('id', 'name')->get();
        $cursos = \App\Models\Academico\Curso::select('id', 'nombre')->get();
        $gestores = User::role('gestor')->select('id', 'name')->get();
        $agendadores = User::role('agendador')->select('id', 'name')->get();

        return response()->json([
            'data' => [
                'roles' => $roles,
                'profesores' => $profesores,
                'cursos' => $cursos,
                'gestores' => $gestores,
                'agendadores' => $agendadores,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de usuarios.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics()
    {
        $stats = [
            'totales' => [
                'total' => User::count(),
                'activos' => User::whereNull('deleted_at')->count(),
                'eliminados' => User::onlyTrashed()->count(),
            ],
            'por_rol' => User::with('roles')
                ->selectRaw('id, count(model_has_roles.role_id) as total_roles')
                ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->groupBy('users.id')
                ->having('total_roles', '>', 0)
                ->get(),
            'con_grupos' => User::with('grupos')
                ->selectRaw('id, count(grupos.id) as total_grupos')
                ->leftJoin('grupos', 'users.id', '=', 'grupos.profesor_id')
                ->groupBy('users.id')
                ->having('total_grupos', '>', 0)
                ->get(),
            'con_cursos' => User::with('cursos')
                ->selectRaw('id, count(curso_user.curso_id) as total_cursos')
                ->leftJoin('curso_user', 'users.id', '=', 'curso_user.user_id')
                ->groupBy('users.id')
                ->having('total_cursos', '>', 0)
                ->get(),
            'con_gestores' => User::with('gestores')
                ->selectRaw('id, count(referidos.id) as total_gestores')
                ->leftJoin('referidos', 'users.id', '=', 'referidos.gestor_id')
                ->groupBy('users.id')
                ->having('total_gestores', '>', 0)
                ->get(),
            'con_agendadores' => User::with('agendadores')
                ->selectRaw('id, count(agendas.id) as total_agendadores')
                ->leftJoin('agendas', 'users.id', '=', 'agendas.agendador_id')
                ->groupBy('users.id')
                ->having('total_agendadores', '>', 0)
                ->get(),
            'con_seguimientos' => User::with('seguimientos')
                ->selectRaw('id, count(seguimientos.id) as total_seguimientos')
                ->leftJoin('seguimientos', 'users.id', '=', 'seguimientos.seguidor_id')
                ->groupBy('users.id')
                ->having('total_seguimientos', '>', 0)
                ->get(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
