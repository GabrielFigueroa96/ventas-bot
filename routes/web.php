<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminChatController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;

Route::get('/', fn() => view('welcome'));

// Auth
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('guest');
Route::post('/logout',[AuthController::class, 'logout'])->name('logout');

// Admin (protegido)
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/',                   [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/clientes',           [AdminController::class, 'clientes'])->name('clientes');
    Route::get('/clientes/{cliente}',          [AdminController::class,     'cliente'])->name('cliente');
    Route::get('/pedidos',                     [AdminController::class,     'pedidos'])->name('pedidos');

    // Control manual del chat
    Route::get ('/clientes/{cliente}/mensajes',      [AdminChatController::class, 'mensajesNuevos'])->name('chat.mensajes');
    Route::get ('/clientes/{cliente}/pedidos-panel', [AdminChatController::class, 'pedidosPanel'])->name('chat.pedidos');
    Route::post('/clientes/{cliente}/tomar',         [AdminChatController::class, 'tomarControl'])->name('chat.tomar');
    Route::post('/clientes/{cliente}/liberar',       [AdminChatController::class, 'liberarControl'])->name('chat.liberar');
    Route::post('/clientes/{cliente}/enviar',        [AdminChatController::class, 'enviar'])->name('chat.enviar');
});
