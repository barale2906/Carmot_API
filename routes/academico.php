<?php

use App\Http\Controllers\Api\Academico\CursoController;
use App\Http\Controllers\Api\Academico\ModuloController;
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

    // Rutas principales de modulos (CRUD estándar)
    Route::apiResource('modulos', ModuloController::class);

    // Rutas adicionales para funcionalidades específicas de módulos
    Route::prefix('modulos')->group(function () {
        // Rutas para manejo de soft delete
        Route::post('{id}/restore', [ModuloController::class, 'restore'])->name('modulos.restore');
        Route::delete('{id}/force-delete', [ModuloController::class, 'forceDelete'])->name('modulos.force-delete');
        Route::get('trashed', [ModuloController::class, 'trashed'])->name('modulos.trashed');

        // Rutas para filtros y estadísticas
        Route::get('filters', [ModuloController::class, 'filters'])->name('modulos.filters');
        Route::get('statistics', [ModuloController::class, 'statistics'])->name('modulos.statistics');
    });
});
