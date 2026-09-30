<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Importación oculta del demo
|--------------------------------------------------------------------------
|
| Sin autenticación: protegida por el token secreto DEMO_IMPORT_TOKEN en la URL
| (VerificarTokenDemo responde 404 si no coincide o no está configurado) y
| demo:importar --solo-vacia, que se niega a tocar una base con registros.
| Excluida de CSRF para permitir también curl/Postman.
|
*/
Route::middleware([\App\Http\Middleware\VerificarTokenDemo::class, 'throttle:5,1'])
    ->prefix('_demo/{token}')
    ->controller(\App\Http\Controllers\Demo\ImportarDemoController::class)
    ->group(function () {
        /** Formulario para cargar el demo.sql. */
        Route::get('/', 'create')->name('demo.importar.create');

        /** Importa el demo.sql sobre la base vacía y ejecuta migraciones pendientes. */
        Route::post('/', 'store')->name('demo.importar.store');
    });
