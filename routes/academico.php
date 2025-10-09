<?php

use App\Http\Controllers\Api\Academico\CursoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Acádemico API Routes
|--------------------------------------------------------------------------
|
| Estas rutas son cargadas por RouteServiceProvider dentro de un grupo que
| Se le asigna el grupo de middleware "api". ¡Disfruta creando tu API de Acádemico!
|
*/

// Todas las rutas de Acádemicos requieren autenticación con Sanctum.
// Los permisos específicos para cada acción CRUD se manejan en el AcádemicoController.
Route::middleware('auth:sanctum')->group(function () {
    // Rutas principales de Acádemicos (CRUD estándar)
    Route::apiResource('cursos', CursoController::class);

    // Rutas adicionales para funcionalidades específicas
    Route::prefix('cursos')->group(function () {
        // Rutas para manejo de soft delete
        Route::post('{id}/restore', [CursoController::class, 'restore'])->name('cursos.restore');
        Route::delete('{id}/force-delete', [CursoController::class, 'forceDelete'])->name('cursos.force-delete');
        Route::get('trashed', [CursoController::class, 'trashed'])->name('cursos.trashed');

        // Rutas para filtros y estadísticas
        Route::get('filters', [CursoController::class, 'filters'])->name('cursos.filters');
        Route::get('statistics', [CursoController::class, 'statistics'])->name('cursos.statistics');
    });
});
