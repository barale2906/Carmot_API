<?php

use App\Http\Controllers\Api\Configuracion\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User API Routes
|--------------------------------------------------------------------------
|
| Estas rutas son cargadas por RouteServiceProvider dentro de un grupo que
| Se le asigna el grupo de middleware "api". ¡Disfruta creando tu API de usuario!
|
*/

// Todas las rutas de usuarios requieren autenticación con Sanctum.
// Los permisos específicos para cada acción CRUD se manejarán en el UserController.
Route::middleware('auth:sanctum')->group(function () {
    // Define las rutas de recursos API para el controlador UserController.
    // Esto crea automáticamente las rutas para index, store, show, update y destroy.
    Route::apiResource('users', UserController::class);
    Route::post('users/restore/{user}', [UserController::class, 'restore']);

    // Si tuvieras rutas adicionales específicas para usuarios que no encajan en el CRUD estándar,
    // podrías definirlas aquí. Por ejemplo:
    // Route::post('users/{user}/assign-role', [UserController::class, 'assignRole']);
    // Route::get('users/{user}/permissions', [UserController::class, 'getUserPermissions']);
});
