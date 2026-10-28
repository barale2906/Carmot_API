<?php

use App\Http\Controllers\Api\Activos\ActActivoController;
use App\Http\Controllers\Api\Activos\ActCategoriaController;
use App\Http\Controllers\Api\Activos\ActTrasladoController;
use App\Models\Activos\ActMovimiento;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Activos API Routes
|--------------------------------------------------------------------------
|
| Rutas del módulo de activos fijos. Se cargan automáticamente por
| RouteServiceProvider con el prefijo api/activos.
| Todas las rutas quedan bajo el nombre base act- (ej. act-activos.index).
|
*/

// {traslado} solo resuelve movimientos de tipo traslado (los demás responden 404).
Route::bind('traslado', fn (string $id) => ActMovimiento::where('tipo', ActMovimiento::TIPO_TRASLADO)->findOrFail($id));

Route::middleware('auth:sanctum')
    ->name('act-')
    ->group(function () {

        // ──────────────────────────────────────────────────────────────────────
        // Categorías de activos
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('categorias')->group(function () {
            Route::get('activas', [ActCategoriaController::class, 'activas'])->name('categorias.activas');
            Route::get('trashed', [ActCategoriaController::class, 'trashed'])->name('categorias.trashed');
        });
        Route::post('categorias/{id}/restore', [ActCategoriaController::class, 'restore'])
            ->name('categorias.restore');
        Route::delete('categorias/{id}/force-delete', [ActCategoriaController::class, 'forceDelete'])
            ->name('categorias.force-delete');
        Route::apiResource('categorias', ActCategoriaController::class)
            ->parameters(['categorias' => 'categoria']);

        // ──────────────────────────────────────────────────────────────────────
        // Activos fijos
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('activos')->group(function () {
            Route::get('filters', [ActActivoController::class, 'filters'])->name('activos.filters');
            Route::get('statistics', [ActActivoController::class, 'statistics'])->name('activos.statistics');
            Route::get('trashed', [ActActivoController::class, 'trashed'])->name('activos.trashed');
            Route::post('{id}/restore', [ActActivoController::class, 'restore'])->name('activos.restore');
            Route::delete('{id}/force-delete', [ActActivoController::class, 'forceDelete'])->name('activos.force-delete');
            Route::post('{activo}/trasladar', [ActActivoController::class, 'trasladar'])->name('activos.trasladar');
            Route::post('{activo}/imagen', [ActActivoController::class, 'subirImagen'])->name('activos.imagen');
            Route::delete('{activo}/imagen', [ActActivoController::class, 'eliminarImagen'])->name('activos.imagen.eliminar');
            Route::post('{activo}/estado', [ActActivoController::class, 'cambiarEstado'])->name('activos.estado');
            Route::get('{activo}/movimientos', [ActActivoController::class, 'movimientos'])->name('activos.movimientos');
        });
        Route::apiResource('activos', ActActivoController::class)
            ->parameters(['activos' => 'activo']);

        // ──────────────────────────────────────────────────────────────────────
        // Traslados: se envían desde el activo y se reciben (o cancelan) aquí
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('traslados')->group(function () {
            Route::get('/', [ActTrasladoController::class, 'index'])->name('traslados.index');
            Route::post('{traslado}/recibir', [ActTrasladoController::class, 'recibir'])->name('traslados.recibir');
            Route::post('{traslado}/cancelar', [ActTrasladoController::class, 'cancelar'])->name('traslados.cancelar');
        });
    });
