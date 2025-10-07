<?php

use App\Http\Controllers\Api\Crm\ReferidoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Referido API Routes
|--------------------------------------------------------------------------
|
| Estas rutas son cargadas por RouteServiceProvider dentro de un grupo que
| Se le asigna el grupo de middleware "api". ¡Disfruta creando tu API de referido!
|
*/

// Todas las rutas de referidos requieren autenticación con Sanctum.
// Los permisos específicos para cada acción CRUD se manejan en el ReferidoController.
Route::middleware('auth:sanctum')->group(function () {
    // Rutas principales de referidos (CRUD estándar)
    Route::apiResource('referidos', ReferidoController::class);

    // Rutas adicionales para soft delete
    Route::prefix('referidos')->group(function () {
        Route::post('{id}/restore', [ReferidoController::class, 'restore'])
            ->name('referidos.restore');
        Route::delete('{id}/force-delete', [ReferidoController::class, 'forceDelete'])
            ->name('referidos.force-delete');
        Route::get('trashed/list', [ReferidoController::class, 'trashed'])
            ->name('referidos.trashed');
        Route::get('filters/options', [ReferidoController::class, 'filters'])
            ->name('referidos.filters');
        Route::get('statistics', [ReferidoController::class, 'statistics'])
            ->name('referidos.statistics');
    });
});
