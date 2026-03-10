<?php

use App\Http\Controllers\Api\Configuracion\AreaController;
use App\Http\Controllers\Api\Configuracion\HorarioController;
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
    // Rutas de usuarios (rutas específicas antes del apiResource para evitar conflictos con {user})
    Route::prefix('users')->group(function () {
        Route::get('filters', [UserController::class, 'filters'])
            ->name('users.filters');
        Route::get('statistics', [UserController::class, 'statistics'])
            ->name('users.statistics');
    });
    Route::post('users/restore/{user}', [UserController::class, 'restore']);
    Route::delete('users/force/{user}', [UserController::class, 'forceDelete'])
        ->name('users.force-delete');
    Route::apiResource('users', UserController::class);

    // Rutas de poblaciones (solo lectura)
    Route::prefix('poblaciones')->group(function () {
        Route::get('filters/options', [PoblacionController::class, 'filters'])
            ->name('poblaciones.filters');
        Route::get('statistics', [PoblacionController::class, 'statistics'])
            ->name('poblaciones.statistics');
    });
    Route::get('poblaciones', [PoblacionController::class, 'index'])
        ->name('poblaciones.index');
    Route::get('poblaciones/{poblacion}', [PoblacionController::class, 'show'])
        ->name('poblaciones.show');

    // Rutas de sedes (rutas específicas antes del apiResource para evitar conflictos con {sede})
    Route::prefix('sedes')->group(function () {
        Route::get('trashed', [SedeController::class, 'trashed'])
            ->name('sedes.trashed');
        Route::get('filters/options', [SedeController::class, 'filters'])
            ->name('sedes.filters');
        Route::get('statistics', [SedeController::class, 'statistics'])
            ->name('sedes.statistics');
    });
    Route::post('sedes/restore/{sede}', [SedeController::class, 'restore'])
        ->name('sedes.restore');
    Route::delete('sedes/force/{sede}', [SedeController::class, 'forceDelete'])
        ->name('sedes.force-delete');
    Route::apiResource('sedes', SedeController::class);

    // Rutas de áreas (rutas específicas antes del apiResource para evitar conflictos con {area})
    Route::prefix('areas')->group(function () {
        Route::get('trashed', [AreaController::class, 'trashed'])
            ->name('areas.trashed');
        Route::get('filters/options', [AreaController::class, 'filters'])
            ->name('areas.filters');
        Route::get('statistics', [AreaController::class, 'statistics'])
            ->name('areas.statistics');
    });
    Route::post('areas/restore/{area}', [AreaController::class, 'restore'])
        ->name('areas.restore');
    Route::delete('areas/force/{area}', [AreaController::class, 'forceDelete'])
        ->name('areas.force-delete');
    Route::apiResource('areas', AreaController::class);

    // Rutas de horarios (rutas específicas antes del apiResource para evitar conflictos con {horario})
    Route::prefix('horarios')->group(function () {
        Route::get('trashed', [HorarioController::class, 'trashed'])
            ->name('horarios.trashed');
        Route::get('filters/options', [HorarioController::class, 'filters'])
            ->name('horarios.filters');
        Route::get('statistics', [HorarioController::class, 'statistics'])
            ->name('horarios.statistics');
    });
    Route::post('horarios/restore/{horario}', [HorarioController::class, 'restore'])
        ->name('horarios.restore');
    Route::delete('horarios/force/{horario}', [HorarioController::class, 'forceDelete'])
        ->name('horarios.force-delete');
    Route::apiResource('horarios', HorarioController::class);
});
