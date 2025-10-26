<?php

use App\Http\Controllers\Api\Academico\CicloController;
use App\Http\Controllers\Api\Academico\CursoController;
use App\Http\Controllers\Api\Academico\GrupoController;
use App\Http\Controllers\Api\Academico\ModuloController;
use App\Http\Controllers\Api\Academico\TopicoController;
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

    // Rutas principales de topicos (CRUD estándar)
    Route::apiResource('topicos', TopicoController::class);

    // Rutas adicionales para funcionalidades específicas de tópicos
    Route::prefix('topicos')->group(function () {
        // Rutas para manejo de soft delete
        Route::post('{id}/restore', [TopicoController::class, 'restore'])->name('topicos.restore');
        Route::delete('{id}/force-delete', [TopicoController::class, 'forceDelete'])->name('topicos.force-delete');
        Route::get('trashed', [TopicoController::class, 'trashed'])->name('topicos.trashed');

        // Rutas para filtros y estadísticas
        Route::get('filters', [TopicoController::class, 'filters'])->name('topicos.filters');
        Route::get('statistics', [TopicoController::class, 'statistics'])->name('topicos.statistics');
    });

    // Rutas principales de grupos (CRUD estándar)
    Route::apiResource('grupos', GrupoController::class);

    // Rutas adicionales para funcionalidades específicas de grupos
    Route::prefix('grupos')->group(function () {
        // Rutas para manejo de soft delete
        Route::post('{id}/restore', [GrupoController::class, 'restore'])->name('grupos.restore');
        Route::delete('{id}/force-delete', [GrupoController::class, 'forceDelete'])->name('grupos.force-delete');
        Route::get('trashed', [GrupoController::class, 'trashed'])->name('grupos.trashed');

        // Rutas para filtros y estadísticas
        Route::get('filters', [GrupoController::class, 'filters'])->name('grupos.filters');
        Route::get('statistics', [GrupoController::class, 'statistics'])->name('grupos.statistics');

        // Rutas para manejo de horarios de grupos
        Route::prefix('{grupo}/horarios')->group(function () {
            Route::get('/', [GrupoController::class, 'getHorarios'])->name('grupos.horarios.index');
            Route::post('/', [GrupoController::class, 'storeHorarios'])->name('grupos.horarios.store');
            Route::put('/', [GrupoController::class, 'updateHorarios'])->name('grupos.horarios.update');
            Route::delete('/', [GrupoController::class, 'destroyHorarios'])->name('grupos.horarios.destroy');
            Route::get('estadisticas', [GrupoController::class, 'getHorariosEstadisticas'])->name('grupos.horarios.statistics');
        });
    });

    // Rutas principales de ciclos (CRUD estándar)
    Route::apiResource('ciclos', CicloController::class);

    // Rutas adicionales para funcionalidades específicas de ciclos
    Route::prefix('ciclos')->group(function () {
        // Rutas para manejo de soft delete
        Route::post('{id}/restore', [CicloController::class, 'restore'])->name('ciclos.restore');
        Route::delete('{id}/force-delete', [CicloController::class, 'forceDelete'])->name('ciclos.force-delete');
        Route::get('trashed', [CicloController::class, 'trashed'])->name('ciclos.trashed');

        // Rutas para filtros y estadísticas
        Route::get('filters', [CicloController::class, 'filters'])->name('ciclos.filters');
        Route::get('statistics', [CicloController::class, 'statistics'])->name('ciclos.statistics');

        // Rutas para gestión de grupos y fechas
        Route::post('{ciclo}/asignar-grupos', [CicloController::class, 'asignarGrupos'])->name('ciclos.asignar-grupos');
        Route::post('{ciclo}/desasignar-grupo', [CicloController::class, 'desasignarGrupo'])->name('ciclos.desasignar-grupo');
        Route::post('{ciclo}/calcular-fecha-fin', [CicloController::class, 'calcularFechaFin'])->name('ciclos.calcular-fecha-fin');
        Route::get('{ciclo}/informacion-calculo', [CicloController::class, 'informacionCalculo'])->name('ciclos.informacion-calculo');

        // Rutas para gestión de orden de grupos
        Route::post('{ciclo}/actualizar-orden-grupo', [CicloController::class, 'actualizarOrdenGrupo'])->name('ciclos.actualizar-orden-grupo');
        Route::post('{ciclo}/reordenar-grupos', [CicloController::class, 'reordenarGrupos'])->name('ciclos.reordenar-grupos');
        Route::get('{ciclo}/cronograma', [CicloController::class, 'cronograma'])->name('ciclos.cronograma');
        Route::get('{ciclo}/siguiente-orden', [CicloController::class, 'siguienteOrden'])->name('ciclos.siguiente-orden');
    });
});
