<?php

use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Dashboard\KpiConfigController;
use App\Http\Controllers\Api\Dashboard\KpiController;
use App\Http\Controllers\Api\Dashboard\KpiCrudController;
use App\Http\Controllers\Api\Dashboard\KpiModelController;
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
    // Rutas de dashboards
    Route::apiResource('dashboards', DashboardController::class);
    Route::post('dashboards/{dashboard}/export-pdf', [DashboardController::class, 'exportPdf']);

    // Rutas para tarjetas de dashboard
    Route::apiResource('dashboard-cards', \App\Http\Controllers\Api\Dashboard\DashboardCardController::class);
    Route::get('dashboard-cards/{card}/compute', [\App\Http\Controllers\Api\Dashboard\DashboardCardController::class, 'compute'])
        ->middleware('kpi.security');

    // Configuración de KPIs para el frontend
    Route::get('kpis/config', [KpiConfigController::class, 'index']);

    // Endpoints meta separados para facilitar consumo en frontend
    Route::get('kpis/models', [KpiConfigController::class, 'models']);
    Route::get('kpis/models/{modelId}/fields', [KpiConfigController::class, 'modelFields']);
    Route::get('kpis/operations/{fieldType}', [KpiConfigController::class, 'operationsByType']);
    Route::get('kpis/periods', [KpiConfigController::class, 'periods']);

    // Cálculo de KPIs (con validación de seguridad de parámetros)
    Route::get('kpis/{kpi}/compute', [KpiController::class, 'compute'])
        ->middleware('kpi.security');

    // CRUD de KPIs (sin middleware de seguridad general para evitar errores en DELETE)
    // La validación de seguridad se aplica en endpoints que la requieren explícitamente (compute, group-by)
    Route::apiResource('kpis', KpiCrudController::class)
        ->except(['create','edit']);

    // Opciones de agrupación para modelos de KPIs
    Route::get('kpis/models/{modelId}/group-by/{field}', [KpiModelController::class, 'groupBy'])
        ->middleware('kpi.security');
});
