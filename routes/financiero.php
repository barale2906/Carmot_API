<?php

use App\Http\Controllers\Api\Financiero\ConceptoPago\ConceptoPagoController;
use App\Http\Controllers\Api\Financiero\Descuento\DescuentoController;
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

    // Grupo de rutas para Conceptos de Pago
    // Rutas principales de conceptos de pago (CRUD estándar)
    Route::apiResource('conceptos-pago', ConceptoPagoController::class);

    // Rutas adicionales para funcionalidades específicas de conceptos de pago
    Route::prefix('conceptos-pago')->group(function () {
        // Ruta para obtener los tipos disponibles
        Route::get('tipos', [ConceptoPagoController::class, 'obtenerTipos'])
            ->name('conceptos-pago.tipos');

        // Ruta para agregar un nuevo tipo al sistema
        Route::post('tipos/agregar', [ConceptoPagoController::class, 'agregarTipo'])
            ->name('conceptos-pago.agregar-tipo');
    });

    // Grupo de rutas para Descuentos
    // Rutas principales de descuentos (CRUD estándar)
    Route::apiResource('descuentos', DescuentoController::class);

    // Rutas adicionales para funcionalidades específicas de descuentos
    Route::prefix('descuentos')->group(function () {
        // Ruta para aprobar un descuento (cambiar de "en proceso" a "aprobado")
        Route::post('{id}/aprobar', [DescuentoController::class, 'aprobar'])
            ->name('descuentos.aprobar');

        // Ruta para aplicar descuentos a un precio
        Route::post('aplicar', [DescuentoController::class, 'aplicarDescuento'])
            ->name('descuentos.aplicar');

        // Ruta para obtener el historial de descuentos aplicados
        Route::get('historial', [DescuentoController::class, 'historial'])
            ->name('descuentos.historial');
    });
});

