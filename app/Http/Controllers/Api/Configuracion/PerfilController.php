<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\CambiarContrasenaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Perfil del usuario autenticado (F03). Los datos se consultan con GET /user;
 * aquí solo se cambia la contraseña propia.
 */
class PerfilController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:co_usersPerfil');
    }

    /**
     * Cambia la contraseña del usuario autenticado y cierra sus demás sesiones
     * (conserva el token con el que hizo el cambio).
     *
     * @param CambiarContrasenaRequest $request
     * @return JsonResponse
     */
    public function cambiarContrasena(CambiarContrasenaRequest $request): JsonResponse
    {
        $usuario = $request->user();

        $usuario->update(['password' => Hash::make($request->password)]);
        $usuario->tokens()->whereKeyNot($usuario->currentAccessToken()?->id)->delete();

        return response()->json([
            'message' => 'Contraseña actualizada. Se cerraron sus otras sesiones.',
        ]);
    }
}
