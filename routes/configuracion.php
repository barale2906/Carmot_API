<?php

use App\Http\Controllers\Api\Configuracion\AreaController;
use App\Http\Controllers\Api\Configuracion\PoblacionController;
use App\Http\Controllers\Api\Configuracion\SedeController;
use App\Http\Controllers\Api\Configuracion\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Configuración API Routes
|--------------------------------------------------------------------------
|
| Estas rutas son cargadas por RouteServiceProvider dentro de un grupo que
| Se le asigna el grupo de middleware "api". ¡Disfruta creando tu API de configuración!
|
*/

// Todas las rutas de configuración requieren autenticación con Sanctum.
// Los permisos específicos para cada acción se manejan en los controladores.
Route::middleware('auth:sanctum')->group(function () {
    // Rutas de usuarios
    Route::apiResource('users', UserController::class);
    Route::post('users/restore/{user}', [UserController::class, 'restore']);

    // Rutas de poblaciones (solo lectura)
    Route::get('poblaciones', [PoblacionController::class, 'index'])
        ->name('poblaciones.index');
    Route::get('poblaciones/{poblacion}', [PoblacionController::class, 'show'])
        ->name('poblaciones.show');

    // Rutas adicionales para poblaciones
    Route::prefix('poblaciones')->group(function () {
        Route::get('filters/options', [PoblacionController::class, 'filters'])
            ->name('poblaciones.filters');
        Route::get('statistics', [PoblacionController::class, 'statistics'])
            ->name('poblaciones.statistics');
    });

    // Rutas de sedes
    Route::apiResource('sedes', SedeController::class);
    Route::post('sedes/restore/{sede}', [SedeController::class, 'restore'])
        ->name('sedes.restore');
    Route::delete('sedes/force/{sede}', [SedeController::class, 'forceDelete'])
        ->name('sedes.force-delete');

    // Rutas adicionales para sedes
    Route::prefix('sedes')->group(function () {
        Route::get('trashed', [SedeController::class, 'trashed'])
            ->name('sedes.trashed');
        Route::get('filters/options', [SedeController::class, 'filters'])
            ->name('sedes.filters');
        Route::get('statistics', [SedeController::class, 'statistics'])
            ->name('sedes.statistics');
    });

    // Rutas de áreas
    Route::apiResource('areas', AreaController::class);
    Route::post('areas/restore/{area}', [AreaController::class, 'restore'])
        ->name('areas.restore');
    Route::delete('areas/force/{area}', [AreaController::class, 'forceDelete'])
        ->name('areas.force-delete');

    // Rutas adicionales para áreas
    Route::prefix('areas')->group(function () {
        Route::get('trashed', [AreaController::class, 'trashed'])
            ->name('areas.trashed');
        Route::get('filters/options', [AreaController::class, 'filters'])
            ->name('areas.filters');
        Route::get('statistics', [AreaController::class, 'statistics'])
            ->name('areas.statistics');
    });
});
