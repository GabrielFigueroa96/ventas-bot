<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminChatController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\LocalidadController;
use App\Http\Controllers\RecordatorioController;

Route::get('/', fn() => view('welcome'));

// Auth
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('guest');
Route::post('/logout',[AuthController::class, 'logout'])->name('logout');

// Admin (protegido)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'set.tenant'])->group(function () {
    Route::get('/',                   [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/clientes',           [AdminController::class, 'clientes'])->name('clientes');
    Route::get('/clientes/{cliente}',          [AdminController::class,     'cliente'])->name('cliente');
    Route::get('/pedidos',                     [AdminController::class,     'pedidos'])->name('pedidos');

    // Control manual del chat
    Route::get ('/clientes/{cliente}/mensajes',      [AdminChatController::class, 'mensajesNuevos'])->name('chat.mensajes');
    Route::get ('/clientes/{cliente}/imprimir',      [AdminChatController::class, 'imprimir'])->name('chat.imprimir');
    Route::get ('/clientes/{cliente}/pedidos-panel', [AdminChatController::class, 'pedidosPanel'])->name('chat.pedidos');
    Route::post('/clientes/{cliente}/tomar',         [AdminChatController::class, 'tomarControl'])->name('chat.tomar');
    Route::post('/clientes/{cliente}/liberar',       [AdminChatController::class, 'liberarControl'])->name('chat.liberar');
    Route::post('/clientes/{cliente}/enviar',        [AdminChatController::class, 'enviar'])->name('chat.enviar');
    Route::post('/clientes/{cliente}/cuenta',        [AdminChatController::class, 'setCuenta'])->name('chat.setCuenta');
    Route::get ('/cuentas/buscar',                   [AdminChatController::class, 'cuentaBuscar'])->name('cuentas.buscar');

    // Localidades
    Route::get   ('/localidades',                    [LocalidadController::class, 'index'])->name('localidades');
    Route::post  ('/localidades',                    [LocalidadController::class, 'store'])->name('localidades.store');
    Route::put   ('/localidades/{localidad}',        [LocalidadController::class, 'update'])->name('localidades.update');
    Route::delete('/localidades/{localidad}',        [LocalidadController::class, 'destroy'])->name('localidades.destroy');
    Route::patch ('/localidades/{localidad}/toggle', [LocalidadController::class, 'toggle'])->name('localidades.toggle');

    // Recordatorios
    Route::get   ('/recordatorios',          [RecordatorioController::class, 'index'])->name('recordatorios');
    Route::post  ('/recordatorios',          [RecordatorioController::class, 'store'])->name('recordatorios.store');
    Route::get   ('/recordatorios/{rec}/edit',[RecordatorioController::class, 'edit'])->name('recordatorios.edit');
    Route::put   ('/recordatorios/{rec}',    [RecordatorioController::class, 'update'])->name('recordatorios.update');
    Route::delete('/recordatorios/{rec}',    [RecordatorioController::class, 'destroy'])->name('recordatorios.destroy');
    Route::patch ('/recordatorios/{rec}/toggle',[RecordatorioController::class, 'toggle'])->name('recordatorios.toggle');

    // Uso IA
    Route::get('/uso-ia', [AdminController::class, 'usoIa'])->name('uso_ia');

    // Configuración del bot
    Route::get ('/configuracion',  [AdminController::class, 'configuracion'])->name('configuracion');
    Route::post('/configuracion',  [AdminController::class, 'guardarConfiguracion'])->name('configuracion.save');

    // Productos
    Route::get   ('/productos',                            [ProductoController::class, 'index'])->name('productos');
    Route::post  ('/productos/{producto}/imagen',          [ProductoController::class, 'uploadImagen'])->name('productos.imagen');
    Route::delete('/productos/{producto}/imagen',          [ProductoController::class, 'deleteImagen'])->name('productos.imagen.delete');
    Route::patch ('/productos/{producto}/descripcion',     [ProductoController::class, 'updateDescripcion'])->name('productos.descripcion');
    Route::patch ('/productos/{producto}/notas-ia',        [ProductoController::class, 'updateNotasIa'])->name('productos.notas_ia');
    Route::patch ('/productos/{producto}/precio',          [ProductoController::class, 'updatePrecio'])->name('productos.precio');
    Route::post  ('/productos/{producto}/catalogo',        [ProductoController::class, 'agregarCatalogo'])->name('productos.catalogo.agregar');
    Route::delete('/productos/{producto}/catalogo',        [ProductoController::class, 'quitarCatalogo'])->name('productos.catalogo.quitar');
    Route::patch ('/productos/{producto}/disponible',      [ProductoController::class, 'toggleDisponible'])->name('productos.disponible');
});
