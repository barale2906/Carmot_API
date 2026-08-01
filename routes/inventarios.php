<?php

use App\Http\Controllers\Api\Inventarios\InvAlmacenController;
use App\Http\Controllers\Api\Inventarios\InvCategoriaController;
use App\Http\Controllers\Api\Inventarios\InvEntregaController;
use App\Http\Controllers\Api\Inventarios\InvKitComponenteController;
use App\Http\Controllers\Api\Inventarios\InvMovimientoController;
use App\Http\Controllers\Api\Inventarios\InvOrdenCompraController;
use App\Http\Controllers\Api\Inventarios\InvPedidoController;
use App\Http\Controllers\Api\Inventarios\InvPrecioProductoController;
use App\Http\Controllers\Api\Inventarios\InvProductoController;
use App\Http\Controllers\Api\Inventarios\InvProveedorController;
use App\Http\Controllers\Api\Inventarios\InvStockController;
use App\Http\Controllers\Api\Inventarios\InvUnidadMedidaController;
use App\Http\Controllers\Api\Inventarios\InvVentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventarios API Routes
|--------------------------------------------------------------------------
|
| Rutas del módulo Inventarios. Se cargan automáticamente por
| RouteServiceProvider con el prefijo api/inventarios.
| Todas las rutas quedan bajo el nombre base inv- (ej. inv-categorias.index).
|
*/

Route::middleware('auth:sanctum')
    ->name('inv-')
    ->group(function () {

        // ──────────────────────────────────────────────────────────────────────
        // Categorías de inventario
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('categorias')->group(function () {
            Route::get('activas', [InvCategoriaController::class, 'activas'])->name('categorias.activas');
            Route::get('trashed', [InvCategoriaController::class, 'trashed'])->name('categorias.trashed');
            Route::get('filters', [InvCategoriaController::class, 'filters'])->name('categorias.filters');
        });
        Route::post('categorias/{id}/restore', [InvCategoriaController::class, 'restore'])
            ->name('categorias.restore');
        Route::delete('categorias/{id}/force-delete', [InvCategoriaController::class, 'forceDelete'])
            ->name('categorias.force-delete');
        Route::apiResource('categorias', InvCategoriaController::class)
            ->parameters(['categorias' => 'categoria']);

        // ──────────────────────────────────────────────────────────────────────
        // Unidades de medida
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('unidades-medida')->group(function () {
            Route::get('activas', [InvUnidadMedidaController::class, 'activas'])->name('unidades.activas');
            Route::get('trashed', [InvUnidadMedidaController::class, 'trashed'])->name('unidades.trashed');
            Route::get('filters', [InvUnidadMedidaController::class, 'filters'])->name('unidades.filters');
        });
        Route::post('unidades-medida/{id}/restore', [InvUnidadMedidaController::class, 'restore'])
            ->name('unidades.restore');
        Route::delete('unidades-medida/{id}/force-delete', [InvUnidadMedidaController::class, 'forceDelete'])
            ->name('unidades.force-delete');
        Route::apiResource('unidades-medida', InvUnidadMedidaController::class)
            ->parameters(['unidades-medida' => 'unidad_medida'])
            ->names([
                'index'   => 'unidades.index',
                'store'   => 'unidades.store',
                'show'    => 'unidades.show',
                'update'  => 'unidades.update',
                'destroy' => 'unidades.destroy',
            ]);

        // ──────────────────────────────────────────────────────────────────────
        // Productos (catálogo)
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('productos')->group(function () {
            Route::get('activos',    [InvProductoController::class, 'activos'])->name('productos.activos');
            Route::get('trashed',    [InvProductoController::class, 'trashed'])->name('productos.trashed');
            Route::get('filters',    [InvProductoController::class, 'filters'])->name('productos.filters');
            Route::get('statistics', [InvProductoController::class, 'statistics'])->name('productos.statistics');
            Route::get('plantilla',  [InvProductoController::class, 'plantilla'])->name('productos.plantilla');
            Route::post('importar',  [InvProductoController::class, 'importar'])->name('productos.importar');
        });
        Route::post('productos/{id}/restore', [InvProductoController::class, 'restore'])
            ->name('productos.restore');
        Route::delete('productos/{id}/force-delete', [InvProductoController::class, 'forceDelete'])
            ->name('productos.force-delete');
        Route::apiResource('productos', InvProductoController::class)
            ->parameters(['productos' => 'producto']);

        // Kit componentes (recurso anidado bajo productos)
        Route::prefix('productos/{producto}/componentes')->group(function () {
            Route::get('/',                [InvKitComponenteController::class, 'index'])->name('kit-componentes.index');
            Route::post('/',               [InvKitComponenteController::class, 'store'])->name('kit-componentes.store');
            Route::put('/{componente}',    [InvKitComponenteController::class, 'update'])->name('kit-componentes.update');
            Route::delete('/{componente}', [InvKitComponenteController::class, 'destroy'])->name('kit-componentes.destroy');
        });

        // ──────────────────────────────────────────────────────────────────────
        // Almacenes
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('almacenes')->group(function () {
            Route::get('activos', [InvAlmacenController::class, 'activos'])->name('almacenes.activos');
            Route::get('trashed', [InvAlmacenController::class, 'trashed'])->name('almacenes.trashed');
            Route::get('filters', [InvAlmacenController::class, 'filters'])->name('almacenes.filters');
        });
        Route::post('almacenes/{id}/restore', [InvAlmacenController::class, 'restore'])
            ->name('almacenes.restore');
        Route::delete('almacenes/{id}/force-delete', [InvAlmacenController::class, 'forceDelete'])
            ->name('almacenes.force-delete');
        Route::post('almacenes/{almacen}/usuarios', [InvAlmacenController::class, 'syncUsuarios'])
            ->name('almacenes.sync-usuarios');
        Route::apiResource('almacenes', InvAlmacenController::class)
            ->parameters(['almacenes' => 'almacen']);

        // ──────────────────────────────────────────────────────────────────────
        // Proveedores
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('proveedores')->group(function () {
            Route::get('activos', [InvProveedorController::class, 'activos'])->name('proveedores.activos');
            Route::get('trashed', [InvProveedorController::class, 'trashed'])->name('proveedores.trashed');
        });
        Route::post('proveedores/{id}/restore', [InvProveedorController::class, 'restore'])
            ->name('proveedores.restore');
        Route::delete('proveedores/{id}/force-delete', [InvProveedorController::class, 'forceDelete'])
            ->name('proveedores.force-delete');
        Route::apiResource('proveedores', InvProveedorController::class)
            ->parameters(['proveedores' => 'proveedor']);

        // ──────────────────────────────────────────────────────────────────────
        // Stock
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('stock')->group(function () {
            Route::get('filters',    [InvStockController::class, 'filters'])->name('stock.filters');
            Route::get('statistics', [InvStockController::class, 'statistics'])->name('stock.statistics');
            Route::get('plantilla',  [InvStockController::class, 'plantilla'])->name('stock.plantilla');
            Route::post('importar',  [InvStockController::class, 'importar'])->name('stock.importar');
        });
        Route::get('stock',      [InvStockController::class, 'index'])->name('stock.index');
        Route::get('stock/{stock}', [InvStockController::class, 'show'])->name('stock.show');

        // ──────────────────────────────────────────────────────────────────────
        // Movimientos (documentos de movimiento de stock)
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('movimientos')->group(function () {
            Route::get('filters', [InvMovimientoController::class, 'filters'])->name('movimientos.filters');
        });
        Route::post('movimientos/{movimiento}/anular', [InvMovimientoController::class, 'anular'])
            ->name('movimientos.anular');
        Route::apiResource('movimientos', InvMovimientoController::class)
            ->parameters(['movimientos' => 'movimiento'])
            ->only(['index', 'show', 'store']);

        // ──────────────────────────────────────────────────────────────────────
        // Precios de productos de inventario
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('precios')->group(function () {
            Route::get('trashed', [InvPrecioProductoController::class, 'trashed'])->name('precios.trashed');
            Route::get('producto/{productoId}', [InvPrecioProductoController::class, 'porProducto'])
                ->name('precios.por-producto');
        });
        Route::post('precios/{id}/restore', [InvPrecioProductoController::class, 'restore'])
            ->name('precios.restore');
        Route::delete('precios/{id}/force-delete', [InvPrecioProductoController::class, 'forceDelete'])
            ->name('precios.force-delete');
        Route::apiResource('precios', InvPrecioProductoController::class)
            ->parameters(['precios' => 'precio']);

        // ──────────────────────────────────────────────────────────────────────
        // Ventas (crear pedido + abonar)
        // ──────────────────────────────────────────────────────────────────────
        Route::post('ventas', [InvVentaController::class, 'store'])->name('ventas.store');
        Route::post('ventas/{pedido}/abonar', [InvVentaController::class, 'abonar'])->name('ventas.abonar');

        // ──────────────────────────────────────────────────────────────────────
        // Pedidos
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('pedidos')->group(function () {
            Route::get('estudiante/{estudianteId}', [InvPedidoController::class, 'porEstudiante'])
                ->name('pedidos.por-estudiante');
        });
        Route::post('pedidos/{pedido}/cancelar', [InvPedidoController::class, 'cancelar'])
            ->name('pedidos.cancelar');
        Route::apiResource('pedidos', InvPedidoController::class)
            ->parameters(['pedidos' => 'pedido'])
            ->only(['index', 'show']);

        // ──────────────────────────────────────────────────────────────────────
        // Entregas
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('entregas')->group(function () {
            Route::get('pendientes',                  [InvEntregaController::class, 'pendientes'])->name('entregas.pendientes');
            Route::get('necesidades',                 [InvEntregaController::class, 'necesidades'])->name('entregas.necesidades');
            Route::post('simple/{entregaId}/completar', [InvEntregaController::class, 'completarSimple'])
                ->name('entregas.completar-simple');
            Route::post('kit/{entregaKitId}/completar', [InvEntregaController::class, 'completarKit'])
                ->name('entregas.completar-kit');
        });

        // ──────────────────────────────────────────────────────────────────────
        // Órdenes de compra
        // ──────────────────────────────────────────────────────────────────────
        Route::prefix('ordenes-compra')->group(function () {
            Route::get('pendientes-recepcion', [InvOrdenCompraController::class, 'pendientesRecepcion'])
                ->name('oc.pendientes-recepcion');
        });
        Route::post('ordenes-compra/{ordenCompra}/enviar',  [InvOrdenCompraController::class, 'enviar'])
            ->name('oc.enviar');
        Route::post('ordenes-compra/{ordenCompra}/recibir', [InvOrdenCompraController::class, 'recibir'])
            ->name('oc.recibir');
        Route::post('ordenes-compra/{ordenCompra}/cancelar', [InvOrdenCompraController::class, 'cancelar'])
            ->name('oc.cancelar');
        Route::apiResource('ordenes-compra', InvOrdenCompraController::class)
            ->parameters(['ordenes-compra' => 'ordenCompra'])
            ->names([
                'index'   => 'oc.index',
                'store'   => 'oc.store',
                'show'    => 'oc.show',
                'update'  => 'oc.update',
                'destroy' => 'oc.destroy',
            ])
            ->only(['index', 'store', 'show', 'update']);
    });
