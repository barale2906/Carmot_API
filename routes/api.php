<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TranslationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

//Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas de traducción (públicas)
Route::get('/translations/messages', [TranslationController::class, 'getMessages']);
Route::post('/translations/locale', [TranslationController::class, 'setLocale']);
Route::post('/translations/validate-example', [TranslationController::class, 'validateExample']);
Route::get('/translations/timezone', [TranslationController::class, 'getTimezoneInfo']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);


});
