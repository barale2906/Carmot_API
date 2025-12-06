<?php

use App\Http\Controllers\Api\Financiero\Lp\LpListaPrecioController;
use App\Http\Controllers\Api\Financiero\Lp\LpPrecioProductoController;
use App\Http\Controllers\Api\Financiero\Lp\LpProductoController;
use App\Http\Controllers\Api\Financiero\Lp\LpTipoProductoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Financiero API Routes
|--------------------------------------------------------------------------
|
| Estas rutas son cargadas por RouteServiceProvider dentro de un grupo que
| se le asigna el grupo de middleware "api". ¡Disfruta creando tu API de financiero!
|
*/

// Todas las rutas de financiero requieren autenticación con Sanctum.
// Los permisos específicos para cada acción se manejan en los controladores.
Route::middleware('auth:sanctum')->group(function () {
    // Grupo de rutas para el submódulo de Listas de Precios (LP)
    Route::prefix('lp')->group(function () {
        // Rutas principales de tipos de producto (CRUD estándar)
        Route::apiResource('tipos-producto', LpTipoProductoController::class);

        // Rutas principales de productos (CRUD estándar)
        Route::apiResource('productos', LpProductoController::class);

        // Rutas principales de listas de precios (CRUD estándar)
        Route::apiResource('listas-precios', LpListaPrecioController::class);

        // Rutas adicionales para funcionalidades específicas de listas de precios
        Route::prefix('listas-precios')->group(function () {
            // Ruta para aprobar una lista de precios (cambiar de "en proceso" a "aprobada")
            Route::post('{id}/aprobar', [LpListaPrecioController::class, 'aprobar'])
                ->name('listas-precios.aprobar');

            // Ruta para activar una lista de precios (cambiar a estado "activa")
            Route::post('{id}/activar', [LpListaPrecioController::class, 'activar'])
                ->name('listas-precios.activar');

            // Ruta para inactivar una lista de precios (cambiar a estado "inactiva")
            Route::post('{id}/inactivar', [LpListaPrecioController::class, 'inactivar'])
                ->name('listas-precios.inactivar');
        });

        // Rutas principales de precios de productos (CRUD estándar)
        Route::apiResource('precios-producto', LpPrecioProductoController::class);

        // Rutas adicionales para funcionalidades específicas de precios de productos
        Route::prefix('precios-producto')->group(function () {
            // Ruta para obtener el precio de un producto según población y fecha
            Route::get('obtener-precio', [LpPrecioProductoController::class, 'obtenerPrecio'])
                ->name('precios-producto.obtener-precio');
        });
    });
});

