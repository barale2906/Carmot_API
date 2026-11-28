<?php

use App\Http\Controllers\Api\Traslados\TrasladoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Traslados API Routes
|--------------------------------------------------------------------------
|
| Rutas del módulo de traslados de estudiantes (cambio de programación, de curso o de sede).
| Se cargan automáticamente por RouteServiceProvider con el prefijo api/traslados.
| Todas las rutas quedan bajo el nombre base tra- (ej. tra-traslados.index).
|
*/

Route::middleware('auth:sanctum')
    ->name('tra-')
    ->group(function () {
        // Las rutas con segmento fijo van antes de {matriculaTraslado} para que este no las capture.
        // El parámetro no se llama {traslado} porque ese nombre ya lo usa el módulo de activos con
        // otro modelo (Route::bind).
        Route::get('opciones', [TrasladoController::class, 'opciones'])->name('traslados.opciones');
        Route::post('simular', [TrasladoController::class, 'simular'])->name('traslados.simular');
        Route::get('/', [TrasladoController::class, 'index'])->name('traslados.index');
        Route::post('/', [TrasladoController::class, 'store'])->name('traslados.store');
        Route::get('{matriculaTraslado}', [TrasladoController::class, 'show'])->name('traslados.show');
        Route::post('{matriculaTraslado}/nivelacion-resuelta', [TrasladoController::class, 'resolverNivelacion'])->name('traslados.nivelacion-resuelta');
    });
