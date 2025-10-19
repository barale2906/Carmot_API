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
        Route::get('models/{modelClass}/fields', [KpiMetadataController::class, 'getFields']);
    });

    // Rutas de dashboards (sin middleware de seguridad - no manejan KPIs directamente)
    Route::apiResource('dashboards', DashboardController::class);
    Route::post('dashboards/{dashboard}/export-pdf', [DashboardController::class, 'exportPdf']);

    // Rutas para KPIs (CON middleware de seguridad)
    Route::middleware('kpi.security')->group(function () {
        Route::apiResource('kpis', \App\Http\Controllers\Api\Dashboard\KpiController::class);
        Route::apiResource('kpi-fields', \App\Http\Controllers\Api\Dashboard\KpiFieldController::class);
        Route::apiResource('dashboard-cards', \App\Http\Controllers\Api\Dashboard\DashboardCardController::class);
    });

    // Ruta de compatibilidad para KPIs disponibles (sin middleware - solo lectura)
    Route::get('kpis/available', [KpiMetadataController::class, 'getAvailableKpis']);
});
