<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\StoreUserRequest;
use App\Http\Requests\Api\Configuracion\UpdateUserRequest;
use App\Http\Resources\Api\Configuracion\UserResource;
use App\Models\user;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
        $query = User::query();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('documento', 'like', "%{$search}%");
        }

        $users = $query->with('roles', 'permissions')->paginate($request->input('per_page', 15));

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user in storage.
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

        return (new UserResource($user->load('roles', 'permissions')))
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
    public function show(User $user)
    {
        return new UserResource($user->load('roles', 'permissions'));
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

        return new UserResource($user->load('roles', 'permissions'));
    }

    /**
     * Inactivar el usuario especificado.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(User $user)
    {
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
        $user->forceDelete(); // Elimina permanentemente

        return response()->json(null, 204);
    }
}
