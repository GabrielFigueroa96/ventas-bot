<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

// Auth
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('guest');
Route::post('/logout',[AuthController::class, 'logout'])->name('logout');

// Admin (protegido)
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/',                   [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/clientes',           [AdminController::class, 'clientes'])->name('clientes');
    Route::get('/clientes/{cliente}', [AdminController::class, 'cliente'])->name('cliente');
    Route::get('/pedidos',            [AdminController::class, 'pedidos'])->name('pedidos');
});
