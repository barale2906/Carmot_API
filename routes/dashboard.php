<?php

use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Dashboard\KpiMetadataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard API Routes
|--------------------------------------------------------------------------
|
| Estas rutas son cargadas por RouteServiceProvider dentro de un grupo que
| Se le asigna el grupo de middleware "api". ¡Disfruta creando tu API de dashboard!
|
*/

// Todas las rutas de dashboard requieren autenticación con Sanctum.
// Los permisos específicos para cada acción se manejan en los controladores.
Route::middleware('auth:sanctum')->group(function () {
    // Rutas de metadata de KPIs (sin middleware de seguridad - solo lectura)
    Route::prefix('kpi-metadata')->group(function () {
        Route::get('models', [KpiMetadataController::class, 'getModels']);
        Route::get('models/{modelId}/fields', [KpiMetadataController::class, 'getFields']);
    });

    // Rutas de dashboards (sin middleware de seguridad - no manejan KPIs directamente)
    Route::apiResource('dashboards', DashboardController::class);
    Route::post('dashboards/{dashboard}/export-pdf', [DashboardController::class, 'exportPdf']);

    // Rutas para KPIs (CON middleware de seguridad)
    Route::middleware('kpi.security')->group(function () {
        Route::apiResource('kpis', \App\Http\Controllers\Api\Dashboard\KpiController::class);
        Route::apiResource('kpi-fields', \App\Http\Controllers\Api\Dashboard\KpiFieldController::class);
        Route::apiResource('dashboard-cards', \App\Http\Controllers\Api\Dashboard\DashboardCardController::class);

        // Rutas para relaciones entre campos de KPIs
        Route::prefix('kpis/{kpi}')->group(function () {
            Route::get('field-relations', [\App\Http\Controllers\Api\Dashboard\KpiFieldRelationController::class, 'index']);
            Route::post('field-relations', [\App\Http\Controllers\Api\Dashboard\KpiFieldRelationController::class, 'store']);
            Route::get('field-relations/{relation}', [\App\Http\Controllers\Api\Dashboard\KpiFieldRelationController::class, 'show']);
            Route::put('field-relations/{relation}', [\App\Http\Controllers\Api\Dashboard\KpiFieldRelationController::class, 'update']);
            Route::delete('field-relations/{relation}', [\App\Http\Controllers\Api\Dashboard\KpiFieldRelationController::class, 'destroy']);
        });

        // Rutas para datos de gráficos
        Route::prefix('kpis/{kpi}')->group(function () {
            Route::get('chart-data', [\App\Http\Controllers\Api\Dashboard\ChartDataController::class, 'getChartData']);
            Route::get('chart-statistics', [\App\Http\Controllers\Api\Dashboard\ChartDataController::class, 'getChartStatistics']);
        });

        // Rutas para tarjetas de dashboard con gráficos
        Route::prefix('dashboard-cards/{card}')->group(function () {
            Route::get('chart-data', [\App\Http\Controllers\Api\Dashboard\ChartDataController::class, 'getChartDataForCard']);
        });

        // Rutas para configuración de gráficos
        Route::prefix('chart-types')->group(function () {
            Route::get('{chartType}/parameters', [\App\Http\Controllers\Api\Dashboard\ChartDataController::class, 'getChartParameters']);
        });

        // Rutas para filtros y agrupación
        Route::prefix('models/{modelId}')->group(function () {
            Route::get('group-by-fields', [\App\Http\Controllers\Api\Dashboard\ChartDataController::class, 'getGroupByFields']);
        });

        Route::get('filter-types', [\App\Http\Controllers\Api\Dashboard\ChartDataController::class, 'getAvailableFilterTypes']);

        // Ruta para obtener operaciones disponibles
        Route::get('field-relations/operations', [\App\Http\Controllers\Api\Dashboard\KpiFieldRelationController::class, 'availableOperations']);
    });

    // Ruta de compatibilidad para KPIs disponibles (sin middleware - solo lectura)
    Route::get('kpis/available', [KpiMetadataController::class, 'getAvailableKpis']);
});
