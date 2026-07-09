<?php

use App\Http\Controllers\Api\Financiero\Cartera\CarteraController;
use App\Http\Controllers\Api\Financiero\ConceptoPago\ConceptoPagoController;
use App\Http\Controllers\Api\Financiero\Descuento\DescuentoController;
use App\Http\Controllers\Api\Financiero\Lp\LpListaPrecioController;
use App\Http\Controllers\Api\Financiero\Lp\LpPrecioProductoController;
use App\Http\Controllers\Api\Financiero\Lp\LpProductoController;
use App\Http\Controllers\Api\Financiero\Lp\LpProductoReferenciaController;
use App\Http\Controllers\Api\Financiero\Lp\LpTipoProductoController;
use App\Http\Controllers\Api\Financiero\ReciboPago\ReciboPagoController;
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
        Route::apiResource('tipos-producto', LpTipoProductoController::class)
            ->parameters(['tipos-producto' => 'lpTipoProducto']);

        // Rutas principales de productos (CRUD estándar)
        Route::apiResource('productos', LpProductoController::class)
            ->parameters(['productos' => 'lpProducto']);

        // Rutas principales de listas de precios (CRUD estándar)
        // Forzar el nombre del parámetro de ruta a {lpListaPrecio} para que coincida con el
        // type-hint LpListaPrecio $lpListaPrecio del controlador (evita modelo vacío e id null).
        Route::apiResource('listas-precios', LpListaPrecioController::class)
            ->parameters(['listas-precios' => 'lpListaPrecio']);

        // Rutas adicionales para funcionalidades específicas de listas de precios
        Route::prefix('listas-precios')->group(function () {
            // Ruta para aprobar una lista de precios (cambiar de "en proceso" a "aprobada")
            // El nombre del segmento debe coincidir con el type-hint del controlador para el route model binding.
            Route::post('{lpListaPrecio}/aprobar', [LpListaPrecioController::class, 'aprobar'])
                ->name('listas-precios.aprobar');

            // Ruta para activar una lista de precios (cambiar a estado "activa")
            Route::post('{lpListaPrecio}/activar', [LpListaPrecioController::class, 'activar'])
                ->name('listas-precios.activar');

            // Ruta para inactivar una lista de precios (cambiar a estado "inactiva")
            Route::post('{lpListaPrecio}/inactivar', [LpListaPrecioController::class, 'inactivar'])
                ->name('listas-precios.inactivar');

            // Ruta para clonar una lista de precios en un nuevo período
            // Copia todos los precios de la lista origen a una nueva lista en estado "En Proceso"
            Route::post('{lpListaPrecio}/clonar', [LpListaPrecioController::class, 'clonar'])
                ->name('listas-precios.clonar');
        });

        // ─── Precios de productos ─────────────────────────────────────────────
        // IMPORTANTE: las rutas GET con segmentos estáticos deben declararse ANTES
        // del apiResource para que no sean capturadas por GET /{id} (show).
        Route::prefix('precios-producto')->group(function () {
            // Precio vigente de un producto para una población y fecha específica
            Route::get('obtener-precio', [LpPrecioProductoController::class, 'obtenerPrecio'])
                ->name('precios-producto.obtener-precio');

            // Productos activos sin precio en una lista específica
            Route::get('sin-precio', [LpPrecioProductoController::class, 'sinPrecioEnLista'])
                ->name('precios-producto.sin-precio');
        });

        // Rutas CRUD estándar de precios de productos
        Route::apiResource('precios-producto', LpPrecioProductoController::class)
            ->parameters(['precios-producto' => 'lpPrecioProducto']);

        // ─── Producto → Referencias académicas ───────────────────────────────
        // IMPORTANTE: las rutas GET con segmentos estáticos van ANTES del apiResource.
        Route::prefix('producto-referencias')->group(function () {
            // Lista cursos/módulos sin ningún producto LP vinculado
            Route::get('sin-vincular', [LpProductoReferenciaController::class, 'sinVincular'])
                ->name('producto-referencias.sin-vincular');

            // Reemplaza masivamente todas las referencias de un producto (PUT no colisiona con show)
            Route::put('sync', [LpProductoReferenciaController::class, 'sync'])
                ->name('producto-referencias.sync');
        });

        // Rutas CRUD estándar de referencias de productos
        Route::apiResource('producto-referencias', LpProductoReferenciaController::class)
            ->parameters(['producto-referencias' => 'lpProductoReferencia'])
            ->only(['index', 'show', 'store', 'destroy']);
    });

    // Grupo de rutas para Conceptos de Pago
    // IMPORTANTE: rutas GET con segmentos estáticos ANTES del apiResource para
    // evitar que sean capturadas por GET /{conceptoPago} (show).
    Route::prefix('conceptos-pago')->group(function () {
        // Ruta para obtener los tipos disponibles
        Route::get('tipos', [ConceptoPagoController::class, 'obtenerTipos'])
            ->name('conceptos-pago.tipos');

        // Ruta para agregar un nuevo tipo al sistema
        Route::post('tipos/agregar', [ConceptoPagoController::class, 'agregarTipo'])
            ->name('conceptos-pago.agregar-tipo');
    });

    // Rutas principales de conceptos de pago (CRUD estándar)
    Route::apiResource('conceptos-pago', ConceptoPagoController::class)
        ->parameters(['conceptos-pago' => 'conceptoPago']);

    // Grupo de rutas para Descuentos
    // IMPORTANTE: rutas GET con segmentos estáticos ANTES del apiResource para
    // evitar colisión con GET /descuentos/{id} (show).
    Route::prefix('descuentos')->group(function () {
        // Resuelve los sobrecargos activos para un medio de pago y marca de tarjeta dados
        Route::get('sobrecargos/por-medio-pago', [DescuentoController::class, 'sobrecargoPorMedioPago'])
            ->name('descuentos.sobrecargos.por-medio-pago');

        // Ruta para aplicar descuentos a un precio
        Route::post('aplicar', [DescuentoController::class, 'aplicarDescuento'])
            ->name('descuentos.aplicar');

        // Ruta para obtener el historial de descuentos aplicados
        Route::get('historial', [DescuentoController::class, 'historial'])
            ->name('descuentos.historial');

        // Ruta para aprobar un descuento (cambiar de "en proceso" a "aprobado")
        Route::post('{descuento}/aprobar', [DescuentoController::class, 'aprobar'])
            ->name('descuentos.aprobar');

        // Ruta para activar un descuento aprobado (cambiar de "aprobado" a "activo")
        Route::post('{descuento}/activar', [DescuentoController::class, 'activar'])
            ->name('descuentos.activar');
    });

    // Rutas principales de descuentos (CRUD estándar)
    Route::apiResource('descuentos', DescuentoController::class);

    // ─── Cartera (cuentas por cobrar) ────────────────────────────────────────
    // IMPORTANTE: rutas GET estáticas ANTES del apiResource.
    Route::prefix('carteras')->group(function () {
        Route::get('deudas-estudiante',  [CarteraController::class, 'deudasEstudiante'])->name('carteras.deudas-estudiante');
        Route::get('detalle-matricula',  [CarteraController::class, 'detalleMatricula'])->name('carteras.detalle-matricula');
        Route::get('reportes',           [CarteraController::class, 'reportes'])->name('carteras.reportes');
        Route::post('{cartera}/anular',  [CarteraController::class, 'anular'])->name('carteras.anular');
        Route::post('acuerdo-pago',      [CarteraController::class, 'acuerdoPago'])->name('carteras.acuerdo-pago');
    });
    Route::apiResource('carteras', CarteraController::class)->only(['index', 'show']);

    // Grupo de rutas para Recibos de Pago
    // IMPORTANTE: rutas GET/POST con segmentos estáticos ANTES del apiResource para
    // evitar colisión con GET /recibos-pago/{id} (show).
    Route::prefix('recibos-pago')->group(function () {
        // Ruta para generar reportes
        Route::get('reportes', [ReciboPagoController::class, 'reportes'])
            ->name('recibos-pago.reportes');

        // Pre-calcula sobrecargos para una lista de medios de pago (antes de crear el recibo)
        Route::post('precalcular-sobrecargos', [ReciboPagoController::class, 'precalcularSobrecargos'])
            ->name('recibos-pago.precalcular-sobrecargos');

        // Pre-calcula si aplica descuento por pronto pago para una matrícula y monto (antes de crear el recibo)
        Route::post('precalcular-descuento', [ReciboPagoController::class, 'precalcularDescuento'])
            ->name('recibos-pago.precalcular-descuento');

        // Ruta para anular un recibo de pago
        Route::post('{reciboPago}/anular', [ReciboPagoController::class, 'anular'])
            ->name('recibos-pago.anular');

        // Ruta para cerrar un recibo de pago
        Route::post('{reciboPago}/cerrar', [ReciboPagoController::class, 'cerrar'])
            ->name('recibos-pago.cerrar');

        // Ruta para generar PDF del recibo
        Route::get('{reciboPago}/pdf', [ReciboPagoController::class, 'generarPDF'])
            ->name('recibos-pago.pdf');

        // Ruta para enviar recibo por correo electrónico
        Route::post('{reciboPago}/enviar-email', [ReciboPagoController::class, 'enviarEmail'])
            ->name('recibos-pago.enviar-email');

        // Agrega un nuevo medio de pago a un recibo existente (sin reemplazar los ya registrados)
        Route::post('{reciboPago}/agregar-medio-pago', [ReciboPagoController::class, 'agregarMedioPago'])
            ->name('recibos-pago.agregar-medio-pago');
    });

    // Rutas principales de recibos de pago (CRUD estándar)
    Route::apiResource('recibos-pago', ReciboPagoController::class)
        ->parameters(['recibos-pago' => 'reciboPago']);
});

