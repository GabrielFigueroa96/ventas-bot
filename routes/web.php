<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminChatController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\LocalidadController;
use App\Http\Controllers\RecordatorioController;
use App\Http\Controllers\TiendaController;

Route::get('/', fn() => view('welcome'));

// Auth
Route::get ('/login',           [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login',           [AuthController::class, 'login'])->name('login.post')->middleware('guest');
Route::get ('/login/verificar', [AuthController::class, 'showVerificar'])->name('login.verificar')->middleware('guest');
Route::post('/login/verificar', [AuthController::class, 'verificar'])->name('login.verificar.post')->middleware('guest');
Route::post('/logout',          [AuthController::class, 'logout'])->name('logout');

// Admin (protegido)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'set.tenant'])->group(function () {
    Route::get('/',                   [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get ('/clientes',           [AdminController::class, 'clientes'])->name('clientes');
    Route::post('/clientes',           [AdminController::class, 'storeCliente'])->name('clientes.store');
    Route::get ('/clientes/{cliente}', [AdminController::class, 'cliente'])->name('cliente');
    Route::put ('/clientes/{cliente}', [AdminController::class, 'updateCliente'])->name('cliente.update');
    Route::get  ('/pedidos',                             [AdminController::class, 'pedidos'])->name('pedidos');
    Route::get  ('/pedidos/hoja-de-ruta',                [AdminController::class, 'hojaDeRuta'])->name('pedidos.hoja_de_ruta');
    Route::post ('/pedidos/hoja-de-ruta/marcar-reparto', [AdminController::class, 'hojaDeRutaMarcarReparto'])->name('pedidos.hoja_de_ruta.marcar_reparto');
    Route::patch('/pedidos/ia/{id}/estado',              [AdminController::class, 'avanzarEstadoPedido'])->name('pedidos.ia.estado');
    Route::patch('/pedidos/ia/{id}/cancelar',            [AdminController::class, 'cancelarPedido'])->name('pedidos.ia.cancelar');
    Route::get  ('/pedidos/ia/{id}/vmayo-opciones',      [AdminController::class, 'vmayoOpciones'])->name('pedidos.ia.vmayo');

    // Conversaciones (vista estilo WhatsApp)
    Route::get ('/conversaciones',               [AdminChatController::class, 'conversaciones'])->name('conversaciones');
    Route::get ('/conversaciones/{cliente}/panel',[AdminChatController::class, 'conversacionPanel'])->name('conversaciones.panel');

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
    Route::patch ('/localidades/{localidad}/toggle',         [LocalidadController::class, 'toggle'])->name('localidades.toggle');
    Route::post  ('/localidades/{localidad}/probar',         [LocalidadController::class, 'probar'])->name('localidades.probar');
    Route::get   ('/localidades/{localidad}/precios',        [LocalidadController::class, 'precios'])->name('localidades.precios');
    Route::patch ('/localidades/{localidad}/precios/{cod}',  [LocalidadController::class, 'precioUpsert'])->name('localidades.precios.upsert');
    Route::delete('/localidades/{localidad}/precios/{cod}',  [LocalidadController::class, 'precioRemove'])->name('localidades.precios.remove');

    // Recordatorios
    Route::get   ('/recordatorios',          [RecordatorioController::class, 'index'])->name('recordatorios');
    Route::post  ('/recordatorios',          [RecordatorioController::class, 'store'])->name('recordatorios.store');
    Route::get   ('/recordatorios/{rec}/edit',[RecordatorioController::class, 'edit'])->name('recordatorios.edit');
    Route::put   ('/recordatorios/{rec}',    [RecordatorioController::class, 'update'])->name('recordatorios.update');
    Route::delete('/recordatorios/{rec}',    [RecordatorioController::class, 'destroy'])->name('recordatorios.destroy');
    Route::patch ('/recordatorios/{rec}/toggle',[RecordatorioController::class, 'toggle'])->name('recordatorios.toggle');
    Route::post  ('/recordatorios/{rec}/probar',[RecordatorioController::class, 'probar'])->name('recordatorios.probar');

    // Test bot
    Route::get ('/test-bot',         [AdminChatController::class, 'testBot'])->name('test_bot');
    Route::post('/test-bot/mensaje', [AdminChatController::class, 'testBotMensaje'])->name('test_bot.mensaje');
    Route::post('/test-bot/reset',   [AdminChatController::class, 'testBotReset'])->name('test_bot.reset');

    // Uso IA
    Route::get('/uso-ia', [AdminController::class, 'usoIa'])->name('uso_ia');

    // Configuración del bot
    Route::get ('/configuracion',  [AdminController::class, 'configuracion'])->name('configuracion');
    Route::post('/configuracion',  [AdminController::class, 'guardarConfiguracion'])->name('configuracion.save');

    // Cuenta / contraseña
    Route::get ('/cuenta',          [AdminController::class, 'cuenta'])->name('cuenta');
    Route::post('/cuenta/password', [AdminController::class, 'cambiarPassword'])->name('cuenta.password');

    // Tienda online
    Route::get ('/tienda-config',  [AdminController::class, 'tienda'])->name('tienda');
    Route::post('/tienda-config',  [AdminController::class, 'guardarTienda'])->name('tienda.save');

    // Productos
    Route::get   ('/productos',                            [ProductoController::class, 'index'])->name('productos');
    Route::post  ('/productos/{cod}/imagen',          [ProductoController::class, 'uploadImagen'])->name('productos.imagen');
    Route::delete('/productos/{cod}/imagen',          [ProductoController::class, 'deleteImagen'])->name('productos.imagen.delete');
    Route::patch ('/productos/{cod}/descripcion',     [ProductoController::class, 'updateDescripcion'])->name('productos.descripcion');
    Route::patch ('/productos/{cod}/notas-ia',        [ProductoController::class, 'updateNotasIa'])->name('productos.notas_ia');
    Route::patch ('/productos/{cod}/precio',          [ProductoController::class, 'updatePrecio'])->name('productos.precio');
    Route::post  ('/productos/{cod}/catalogo',        [ProductoController::class, 'agregarCatalogo'])->name('productos.catalogo.agregar');
    Route::delete('/productos/{cod}/catalogo',        [ProductoController::class, 'quitarCatalogo'])->name('productos.catalogo.quitar');
    Route::patch ('/productos/{cod}/disponible',      [ProductoController::class, 'toggleDisponible'])->name('productos.disponible');
    Route::post  ('/productos/{cod}/sugerir-descripcion', [ProductoController::class, 'sugerirDescripcion'])->name('productos.sugerir_descripcion');
    Route::post  ('/productos/{cod}/localidades',                [ProductoController::class, 'storeLocalidad'])->name('productos.localidades.store');
    Route::patch ('/productos/{cod}/localidades/{localidad_id}', [ProductoController::class, 'patchLocalidad'])->name('productos.localidades.patch');
    Route::delete('/productos/{cod}/localidades/{localidad_id}', [ProductoController::class, 'destroyLocalidad'])->name('productos.localidades.destroy');
});

// Tienda online pública (multi-tenant por slug)
Route::prefix('tienda/{slug}')->name('tienda.')->middleware(['web', 'tienda.tenant'])->group(function () {
    Route::get ('/',          [TiendaController::class, 'index'])->name('index');
    Route::get ('/login',     [TiendaController::class, 'showLogin'])->name('login');
    Route::post('/login',     [TiendaController::class, 'postLogin'])->name('login.post');
    Route::get ('/verificar', [TiendaController::class, 'showVerificar'])->name('verificar');
    Route::post('/verificar', [TiendaController::class, 'postVerificar'])->name('verificar.post');
    Route::post('/logout',    [TiendaController::class, 'logout'])->name('logout');
    Route::get ('/mis-pedidos', [TiendaController::class, 'misPedidos'])->name('mis_pedidos');
});
