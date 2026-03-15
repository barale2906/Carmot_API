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
use App\Http\Controllers\Api\Academico\TemaController;
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

    // -------------------------------------------------------------------------
    // Cursos
    // -------------------------------------------------------------------------
    Route::prefix('cursos')->group(function () {
        Route::get('trashed', [CursoController::class, 'trashed'])->name('cursos.trashed');
        Route::get('filters', [CursoController::class, 'filters'])->name('cursos.filters');
        Route::get('statistics', [CursoController::class, 'statistics'])->name('cursos.statistics');
        Route::post('{id}/restore', [CursoController::class, 'restore'])->name('cursos.restore');
        Route::delete('{id}/force-delete', [CursoController::class, 'forceDelete'])->name('cursos.force-delete');
    });
    Route::apiResource('cursos', CursoController::class);

    // -------------------------------------------------------------------------
    // Módulos
    // -------------------------------------------------------------------------
    Route::prefix('modulos')->group(function () {
        Route::get('trashed', [ModuloController::class, 'trashed'])->name('modulos.trashed');
        Route::get('filters', [ModuloController::class, 'filters'])->name('modulos.filters');
        Route::get('statistics', [ModuloController::class, 'statistics'])->name('modulos.statistics');
        Route::post('{id}/restore', [ModuloController::class, 'restore'])->name('modulos.restore');
        Route::delete('{id}/force-delete', [ModuloController::class, 'forceDelete'])->name('modulos.force-delete');
    });
    Route::apiResource('modulos', ModuloController::class);

    // -------------------------------------------------------------------------
    // Tópicos
    // -------------------------------------------------------------------------
    Route::prefix('topicos')->group(function () {
        Route::get('trashed', [TopicoController::class, 'trashed'])->name('topicos.trashed');
        Route::get('filters', [TopicoController::class, 'filters'])->name('topicos.filters');
        Route::get('statistics', [TopicoController::class, 'statistics'])->name('topicos.statistics');
        Route::post('{id}/restore', [TopicoController::class, 'restore'])->name('topicos.restore');
        Route::delete('{id}/force-delete', [TopicoController::class, 'forceDelete'])->name('topicos.force-delete');
    });
    Route::apiResource('topicos', TopicoController::class);

    // -------------------------------------------------------------------------
    // Temas
    // -------------------------------------------------------------------------
    Route::prefix('temas')->group(function () {
        Route::get('trashed', [TemaController::class, 'trashed'])->name('temas.trashed');
        Route::get('filters', [TemaController::class, 'filters'])->name('temas.filters');
        Route::get('statistics', [TemaController::class, 'statistics'])->name('temas.statistics');
        Route::post('{id}/restore', [TemaController::class, 'restore'])->name('temas.restore');
        Route::delete('{id}/force-delete', [TemaController::class, 'forceDelete'])->name('temas.force-delete');
    });
    Route::apiResource('temas', TemaController::class);

    // -------------------------------------------------------------------------
    // Grupos
    // -------------------------------------------------------------------------
    Route::prefix('grupos')->group(function () {
        Route::get('trashed', [GrupoController::class, 'trashed'])->name('grupos.trashed');
        Route::get('filters', [GrupoController::class, 'filters'])->name('grupos.filters');
        Route::get('statistics', [GrupoController::class, 'statistics'])->name('grupos.statistics');
        Route::post('{id}/restore', [GrupoController::class, 'restore'])->name('grupos.restore');
        Route::delete('{id}/force-delete', [GrupoController::class, 'forceDelete'])->name('grupos.force-delete');

        Route::prefix('{grupo}/horarios')->group(function () {
            Route::get('/', [GrupoController::class, 'getHorarios'])->name('grupos.horarios.index');
            Route::post('/', [GrupoController::class, 'storeHorarios'])->name('grupos.horarios.store');
            Route::put('/', [GrupoController::class, 'updateHorarios'])->name('grupos.horarios.update');
            Route::delete('/', [GrupoController::class, 'destroyHorarios'])->name('grupos.horarios.destroy');
            Route::get('estadisticas', [GrupoController::class, 'getHorariosEstadisticas'])->name('grupos.horarios.statistics');
        });
    });
    Route::apiResource('grupos', GrupoController::class);

    // -------------------------------------------------------------------------
    // Ciclos
    // -------------------------------------------------------------------------
    Route::prefix('ciclos')->group(function () {
        Route::get('trashed', [CicloController::class, 'trashed'])->name('ciclos.trashed');
        Route::get('filters', [CicloController::class, 'filters'])->name('ciclos.filters');
        Route::get('statistics', [CicloController::class, 'statistics'])->name('ciclos.statistics');
        Route::post('{id}/restore', [CicloController::class, 'restore'])->name('ciclos.restore');
        Route::delete('{id}/force-delete', [CicloController::class, 'forceDelete'])->name('ciclos.force-delete');

        Route::post('{ciclo}/asignar-grupos', [CicloController::class, 'asignarGrupos'])->name('ciclos.asignar-grupos');
        Route::post('{ciclo}/desasignar-grupo', [CicloController::class, 'desasignarGrupo'])->name('ciclos.desasignar-grupo');
        Route::post('{ciclo}/calcular-fecha-fin', [CicloController::class, 'calcularFechaFin'])->name('ciclos.calcular-fecha-fin');
        Route::get('{ciclo}/informacion-calculo', [CicloController::class, 'informacionCalculo'])->name('ciclos.informacion-calculo');
        Route::post('{ciclo}/actualizar-orden-grupo', [CicloController::class, 'actualizarOrdenGrupo'])->name('ciclos.actualizar-orden-grupo');
        Route::post('{ciclo}/reordenar-grupos', [CicloController::class, 'reordenarGrupos'])->name('ciclos.reordenar-grupos');
        Route::get('{ciclo}/cronograma', [CicloController::class, 'cronograma'])->name('ciclos.cronograma');
        Route::get('{ciclo}/siguiente-orden', [CicloController::class, 'siguienteOrden'])->name('ciclos.siguiente-orden');
    });
    Route::apiResource('ciclos', CicloController::class);

    // -------------------------------------------------------------------------
    // Programaciones
    // -------------------------------------------------------------------------
    Route::prefix('programaciones')->group(function () {
        Route::get('trashed', [ProgramacionController::class, 'trashed'])->name('programaciones.trashed');
        Route::get('filters', [ProgramacionController::class, 'filters'])->name('programaciones.filters');
        Route::get('statistics', [ProgramacionController::class, 'statistics'])->name('programaciones.statistics');
        Route::post('{id}/restore', [ProgramacionController::class, 'restore'])->name('programaciones.restore');
        Route::delete('{id}/force-delete', [ProgramacionController::class, 'forceDelete'])->name('programaciones.force-delete');

        Route::post('{programacion}/asignar-grupos', [ProgramacionController::class, 'asignarGrupos'])->name('programaciones.asignar-grupos');
        Route::post('{programacion}/desasignar-grupo', [ProgramacionController::class, 'desasignarGrupo'])->name('programaciones.desasignar-grupo');
        Route::get('{programacion}/cronograma', [ProgramacionController::class, 'cronograma'])->name('programaciones.cronograma');
    });
    Route::apiResource('programaciones', ProgramacionController::class);

    // -------------------------------------------------------------------------
    // Matrículas
    // -------------------------------------------------------------------------
    Route::prefix('matriculas')->group(function () {
        Route::get('trashed', [MatriculaController::class, 'trashed'])->name('matriculas.trashed');
        Route::get('filters', [MatriculaController::class, 'filters'])->name('matriculas.filters');
        Route::get('statistics', [MatriculaController::class, 'statistics'])->name('matriculas.statistics');
        Route::post('{id}/restore', [MatriculaController::class, 'restore'])->name('matriculas.restore');
        Route::delete('{id}/force-delete', [MatriculaController::class, 'forceDelete'])->name('matriculas.force-delete');
    });
    Route::apiResource('matriculas', MatriculaController::class);

    // -------------------------------------------------------------------------
    // Esquemas de calificación
    // -------------------------------------------------------------------------
    Route::prefix('esquemas-calificacion')->group(function () {
        Route::post('{id}/restore', [EsquemaCalificacionController::class, 'restore'])->name('esquemas-calificacion.restore');
        Route::get('modulo/{moduloId}/grupo/{grupoId?}', [EsquemaCalificacionController::class, 'getByModuloGrupo'])->name('esquemas-calificacion.by-modulo-grupo');
    });
    Route::apiResource('esquemas-calificacion', EsquemaCalificacionController::class);

    // -------------------------------------------------------------------------
    // Notas de estudiantes
    // -------------------------------------------------------------------------
    Route::prefix('notas-estudiantes')->group(function () {
        Route::post('masivo', [NotaEstudianteController::class, 'storeMasivo'])->name('notas-estudiantes.masivo');
        Route::get('estudiante/{estudianteId}/modulo/{moduloId}/nota-final', [NotaEstudianteController::class, 'calcularNotaFinal'])->name('notas-estudiantes.calcular-nota-final');
        Route::get('estudiante/{estudianteId}/modulo/{moduloId}/grupo/{grupoId}/nota-final', [NotaEstudianteController::class, 'calcularNotaFinal'])->name('notas-estudiantes.calcular-nota-final-grupo');
        Route::get('estudiante/{estudianteId}/sabana', [NotaEstudianteController::class, 'sabanaEstudiante'])->name('notas-estudiantes.sabana-estudiante');
        Route::get('grupo/{grupoId}/modulo/{moduloId}/sabana', [NotaEstudianteController::class, 'sabanaGrupal'])->name('notas-estudiantes.sabana-grupal');
        Route::post('{id}/restore', [NotaEstudianteController::class, 'restore'])->name('notas-estudiantes.restore');
    });
    Route::apiResource('notas-estudiantes', NotaEstudianteController::class);

    // -------------------------------------------------------------------------
    // Asistencias
    // -------------------------------------------------------------------------
    Route::prefix('asistencias')->group(function () {
        Route::post('masivo', [AsistenciaController::class, 'storeMasivo'])->name('asistencias.masivo');
        Route::get('lista-asistencia/{grupoId}', [AsistenciaController::class, 'listaAsistencia'])->name('asistencias.lista-asistencia');
        Route::get('reporte/estudiante/{id}', [AsistenciaController::class, 'reporteEstudiante'])->name('asistencias.reporte-estudiante');
        Route::get('reporte/grupo/{grupoId}', [AsistenciaController::class, 'reporteGrupo'])->name('asistencias.reporte-grupo');
        Route::post('{id}/restore', [AsistenciaController::class, 'restore'])->name('asistencias.restore');
    });
    Route::apiResource('asistencias', AsistenciaController::class);

    // -------------------------------------------------------------------------
    // Asistencia — Clases programadas
    // -------------------------------------------------------------------------
    Route::prefix('asistencia-clases-programadas')->group(function () {
        Route::post('generar-automaticas', [AsistenciaClaseProgramadaController::class, 'generarAutomaticas'])->name('asistencia-clases-programadas.generar-automaticas');
    });
    Route::apiResource('asistencia-clases-programadas', AsistenciaClaseProgramadaController::class);

    // -------------------------------------------------------------------------
    // Asistencia — Configuraciones
    // -------------------------------------------------------------------------
    Route::apiResource('asistencia-configuraciones', AsistenciaConfiguracionController::class);
});
