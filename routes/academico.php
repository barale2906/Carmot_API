<?php

use App\Http\Controllers\Api\Academico\AsistenciaClaseProgramadaController;
use App\Http\Controllers\Api\Academico\AsistenciaConfiguracionController;
use App\Http\Controllers\Api\Academico\AsistenciaController;
use App\Http\Controllers\Api\Academico\CicloController;
use App\Http\Controllers\Api\Academico\CursoController;
use App\Http\Controllers\Api\Academico\EsquemaCalificacionController;
use App\Http\Controllers\Api\Academico\GrupoController;
use App\Http\Controllers\Api\Academico\MatriculaController;
use App\Http\Controllers\Api\Academico\ModuloController;
use App\Http\Controllers\Api\Academico\NotaEstudianteController;
use App\Http\Controllers\Api\Academico\ProgramacionController;
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

    // Rutas principales de programaciones (CRUD estándar)
    Route::apiResource('programaciones', ProgramacionController::class);

    // Rutas adicionales para funcionalidades específicas de programaciones
    Route::prefix('programaciones')->group(function () {
        // Rutas para manejo de soft delete
        Route::post('{id}/restore', [ProgramacionController::class, 'restore'])->name('programaciones.restore');
        Route::delete('{id}/force-delete', [ProgramacionController::class, 'forceDelete'])->name('programaciones.force-delete');
        Route::get('trashed', [ProgramacionController::class, 'trashed'])->name('programaciones.trashed');

        // Rutas para filtros y estadísticas
        Route::get('filters', [ProgramacionController::class, 'filters'])->name('programaciones.filters');
        Route::get('statistics', [ProgramacionController::class, 'statistics'])->name('programaciones.statistics');

        // Rutas para gestión de grupos y fechas
        Route::post('{programacion}/asignar-grupos', [ProgramacionController::class, 'asignarGrupos'])->name('programaciones.asignar-grupos');
        Route::post('{programacion}/desasignar-grupo', [ProgramacionController::class, 'desasignarGrupo'])->name('programaciones.desasignar-grupo');
        Route::get('{programacion}/cronograma', [ProgramacionController::class, 'cronograma'])->name('programaciones.cronograma');
    });

    // Rutas principales de matrículas (CRUD estándar)
    Route::apiResource('matriculas', MatriculaController::class);

    // Rutas adicionales para funcionalidades específicas de matrículas
    Route::prefix('matriculas')->group(function () {
        // Rutas para manejo de soft delete
        Route::post('{id}/restore', [MatriculaController::class, 'restore'])->name('matriculas.restore');
        Route::delete('{id}/force-delete', [MatriculaController::class, 'forceDelete'])->name('matriculas.force-delete');
        Route::get('trashed', [MatriculaController::class, 'trashed'])->name('matriculas.trashed');

        // Rutas para filtros y estadísticas
        Route::get('filters', [MatriculaController::class, 'filters'])->name('matriculas.filters');
        Route::get('statistics', [MatriculaController::class, 'statistics'])->name('matriculas.statistics');
    });

    // Rutas principales de esquemas de calificación (CRUD estándar)
    Route::apiResource('esquemas-calificacion', EsquemaCalificacionController::class);

    // Rutas adicionales para funcionalidades específicas de esquemas de calificación
    Route::prefix('esquemas-calificacion')->group(function () {
        // Rutas para manejo de soft delete
        Route::post('{id}/restore', [EsquemaCalificacionController::class, 'restore'])->name('esquemas-calificacion.restore');

        // Ruta para obtener esquema activo por módulo y grupo
        Route::get('modulo/{moduloId}/grupo/{grupoId?}', [EsquemaCalificacionController::class, 'getByModuloGrupo'])->name('esquemas-calificacion.by-modulo-grupo');
    });

    // Rutas principales de notas de estudiantes (CRUD estándar)
    Route::apiResource('notas-estudiantes', NotaEstudianteController::class);

    // Rutas adicionales para funcionalidades específicas de notas
    Route::prefix('notas-estudiantes')->group(function () {
        // Rutas para manejo de soft delete
        Route::post('{id}/restore', [NotaEstudianteController::class, 'restore'])->name('notas-estudiantes.restore');

        // Ruta para registro masivo de notas
        Route::post('masivo', [NotaEstudianteController::class, 'storeMasivo'])->name('notas-estudiantes.masivo');

        // Ruta para calcular nota final
        Route::get('estudiante/{estudianteId}/modulo/{moduloId}/nota-final', [NotaEstudianteController::class, 'calcularNotaFinal'])->name('notas-estudiantes.calcular-nota-final');
        Route::get('estudiante/{estudianteId}/modulo/{moduloId}/grupo/{grupoId}/nota-final', [NotaEstudianteController::class, 'calcularNotaFinal'])->name('notas-estudiantes.calcular-nota-final-grupo');

        // Ruta para sabana de notas del estudiante
        Route::get('estudiante/{estudianteId}/sabana', [NotaEstudianteController::class, 'sabanaEstudiante'])->name('notas-estudiantes.sabana-estudiante');

        // Ruta para sabana de notas grupal
        Route::get('grupo/{grupoId}/modulo/{moduloId}/sabana', [NotaEstudianteController::class, 'sabanaGrupal'])->name('notas-estudiantes.sabana-grupal');
    });

    // Rutas principales de asistencias (CRUD estándar)
    Route::apiResource('asistencias', AsistenciaController::class);

    // Rutas adicionales para funcionalidades específicas de asistencias
    Route::prefix('asistencias')->group(function () {
        // Rutas para manejo de soft delete
        Route::post('{id}/restore', [AsistenciaController::class, 'restore'])->name('asistencias.restore');

        // Ruta para registro masivo de asistencias
        Route::post('masivo', [AsistenciaController::class, 'storeMasivo'])->name('asistencias.masivo');

        // Ruta para lista de asistencia por grupo
        Route::get('lista-asistencia/{grupoId}', [AsistenciaController::class, 'listaAsistencia'])->name('asistencias.lista-asistencia');

        // Rutas para reportes
        Route::get('reporte/estudiante/{id}', [AsistenciaController::class, 'reporteEstudiante'])->name('asistencias.reporte-estudiante');
        Route::get('reporte/grupo/{grupoId}', [AsistenciaController::class, 'reporteGrupo'])->name('asistencias.reporte-grupo');
    });

    // Rutas principales de clases programadas (CRUD estándar)
    Route::apiResource('asistencia-clases-programadas', AsistenciaClaseProgramadaController::class);

    // Rutas adicionales para funcionalidades específicas de clases programadas
    Route::prefix('asistencia-clases-programadas')->group(function () {
        // Ruta para generar clases automáticamente
        Route::post('generar-automaticas', [AsistenciaClaseProgramadaController::class, 'generarAutomaticas'])->name('asistencia-clases-programadas.generar-automaticas');
    });

    // Rutas principales de configuraciones de asistencia (CRUD estándar)
    Route::apiResource('asistencia-configuraciones', AsistenciaConfiguracionController::class);
});
