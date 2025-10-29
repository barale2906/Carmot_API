<?php

use App\Http\Controllers\Api\Dashboard\DashboardController;
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
});
