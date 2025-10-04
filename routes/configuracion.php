<?php

use App\Http\Controllers\Api\Configuracion\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/usercrea', [UserController::class, 'store'])->middleware('permission:co_userCrear');

Route::get('/users', [UserController::class, 'index'])->middleware('role:superusuario');
