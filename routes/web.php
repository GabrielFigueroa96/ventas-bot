<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminChatController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlujoController;
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
    Route::patch ('/localidades/{localidad}/toggle', [LocalidadController::class, 'toggle'])->name('localidades.toggle');

    // Recordatorios
    Route::get   ('/recordatorios',          [RecordatorioController::class, 'index'])->name('recordatorios');
    Route::post  ('/recordatorios',          [RecordatorioController::class, 'store'])->name('recordatorios.store');
    Route::get   ('/recordatorios/{rec}/edit',[RecordatorioController::class, 'edit'])->name('recordatorios.edit');
    Route::put   ('/recordatorios/{rec}',    [RecordatorioController::class, 'update'])->name('recordatorios.update');
    Route::delete('/recordatorios/{rec}',    [RecordatorioController::class, 'destroy'])->name('recordatorios.destroy');
    Route::patch ('/recordatorios/{rec}/toggle',[RecordatorioController::class, 'toggle'])->name('recordatorios.toggle');
    Route::post  ('/recordatorios/{rec}/probar',[RecordatorioController::class, 'probar'])->name('recordatorios.probar');

    // Flujo bot (visualización)
    Route::get('/flujo-bot', [AdminChatController::class, 'flujoBot'])->name('flujo_bot');

    // Editor de flujos
    Route::get   ('/flujos',                   [FlujoController::class, 'index'])->name('flujos');
    Route::get   ('/flujos/crear',             [FlujoController::class, 'crear'])->name('flujos.crear');
    Route::post  ('/flujos',                   [FlujoController::class, 'store'])->name('flujos.store');
    Route::get   ('/flujos/{flujo}/editar',    [FlujoController::class, 'editar'])->name('flujos.editar');
    Route::put   ('/flujos/{flujo}',           [FlujoController::class, 'update'])->name('flujos.update');
    Route::delete('/flujos/{flujo}',           [FlujoController::class, 'destroy'])->name('flujos.destroy');
    Route::patch ('/flujos/{flujo}/activar',   [FlujoController::class, 'activar'])->name('flujos.activar');

    // ── SEED TEMPORAL: visitar una vez y eliminar ──────────────────────────
    Route::get('/seed-flujo-principal', function () {
        \App\Models\IaFlujo::query()->update(['activo' => false]);

        $h = fn(string $tipo, string $color, string $icon, string $body) =>
            "<div class='df-node'><div class='df-head' style='background:{$color}'><span>{$icon}</span><span>{$tipo}</span></div><div class='df-body'>{$body}</div></div>";

        $badge  = fn(string $t) => "<span class='df-badge'>{$t}</span>";
        $prev   = fn(string $t) => "<div class='df-preview'>{$t}</div>";
        $prevMb = fn(string $t) => "<div class='df-preview mb-1'>{$t}</div>";
        $label  = fn(string $t) => "<div class='df-output-label'>{$t}</div>";

        $def = ['drawflow' => ['Home' => ['data' => [

            '1' => ['id'=>1,'name'=>'inicio','class'=>'inicio',
                'data'=>['tipo'=>'inicio','trigger'=>'siempre','keywords'=>''],
                'html'=>$h('Inicio','#22c55e','🟢',$badge('Siempre')),
                'typenode'=>false,'inputs'=>(object)[],'pos_x'=>50,'pos_y'=>250,
                'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],

            '2' => ['id'=>2,'name'=>'condicion','class'=>'condicion',
                'data'=>['tipo'=>'condicion','campo'=>'es_cliente_nuevo','valor'=>'','etiq_si'=>'Nuevo','etiq_no'=>'Ya registrado'],
                'html'=>$h('Condición','#f59e0b','🔀',$badge('es_cliente_nuevo').$label('Nuevo / Ya registrado')),
                'typenode'=>false,'pos_x'=>280,'pos_y'=>250,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'1','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'3','output'=>'input_1']]],'output_2'=>['connections'=>[['node'=>'10','output'=>'input_1']]]]],

            '3' => ['id'=>3,'name'=>'mensaje','class'=>'mensaje',
                'data'=>['tipo'=>'mensaje','texto'=>'¡Hola! Soy el asistente del negocio. ¿Cuál es tu nombre?'],
                'html'=>$h('Mensaje','#3b82f6','💬',$prev('¡Hola! ¿Cuál es tu nombre?')),
                'typenode'=>false,'pos_x'=>510,'pos_y'=>80,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'2','input'=>'output_1'],['node'=>'4','input'=>'output_2']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'4','output'=>'input_1']]]]],

            '4' => ['id'=>4,'name'=>'pregunta','class'=>'pregunta',
                'data'=>['tipo'=>'pregunta','texto'=>'¿Tu nombre es [nombre]?','opciones'=>"Sí\nNo"],
                'html'=>$h('Pregunta','#8b5cf6','❓',$prevMb('¿Tu nombre es [nombre]?').$badge('Sí').$badge('No')),
                'typenode'=>false,'pos_x'=>740,'pos_y'=>80,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'3','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'5','output'=>'input_1']]],'output_2'=>['connections'=>[['node'=>'3','output'=>'input_1']]],'output_3'=>['connections'=>[]]]],

            '5' => ['id'=>5,'name'=>'mensaje','class'=>'mensaje',
                'data'=>['tipo'=>'mensaje','texto'=>'¿En qué localidad estás? (Repartimos en: [localidades])'],
                'html'=>$h('Mensaje','#3b82f6','💬',$prev('¿En qué localidad estás?')),
                'typenode'=>false,'pos_x'=>970,'pos_y'=>80,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'4','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'6','output'=>'input_1']]]]],

            '6' => ['id'=>6,'name'=>'condicion','class'=>'condicion',
                'data'=>['tipo'=>'condicion','campo'=>'tiene_localidad','valor'=>'','etiq_si'=>'Con reparto','etiq_no'=>'Sin reparto'],
                'html'=>$h('Condición','#f59e0b','🔀',$badge('tiene_localidad').$label('Con reparto / Sin reparto')),
                'typenode'=>false,'pos_x'=>1200,'pos_y'=>80,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'5','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'7','output'=>'input_1']]],'output_2'=>['connections'=>[['node'=>'12','output'=>'input_1']]]]],

            '7' => ['id'=>7,'name'=>'mensaje','class'=>'mensaje',
                'data'=>['tipo'=>'mensaje','texto'=>'¿Cuál es tu calle y número de entrega? (ej: Italia 1234)'],
                'html'=>$h('Mensaje','#3b82f6','💬',$prev('¿Cuál es tu calle y número?')),
                'typenode'=>false,'pos_x'=>1430,'pos_y'=>80,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'6','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'8','output'=>'input_1']]]]],

            '8' => ['id'=>8,'name'=>'pregunta','class'=>'pregunta',
                'data'=>['tipo'=>'pregunta','texto'=>'¿Tu dirección es [dirección]?','opciones'=>"Sí\nNo, corregir"],
                'html'=>$h('Pregunta','#8b5cf6','❓',$prevMb('¿Tu dirección es [dirección]?').$badge('Sí').$badge('No, corregir')),
                'typenode'=>false,'pos_x'=>1660,'pos_y'=>80,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'7','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'9','output'=>'input_1']]],'output_2'=>['connections'=>[['node'=>'7','output'=>'input_1']]],'output_3'=>['connections'=>[]]]],

            '9' => ['id'=>9,'name'=>'mensaje','class'=>'mensaje',
                'data'=>['tipo'=>'mensaje','texto'=>'¿Tenés algún dato extra? (piso, depto, referencia) — respondé no para omitir.'],
                'html'=>$h('Mensaje','#3b82f6','💬',$prev('¿Dato extra? (piso, depto...)')),
                'typenode'=>false,'pos_x'=>1890,'pos_y'=>80,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'8','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'12','output'=>'input_1']]]]],

            '10' => ['id'=>10,'name'=>'condicion','class'=>'condicion',
                'data'=>['tipo'=>'condicion','campo'=>'texto_contiene','valor'=>'humano','etiq_si'=>'Modo humano','etiq_no'=>'Modo bot'],
                'html'=>$h('Condición','#f59e0b','🔀',$badge('modo_humano').$label('Humano / Bot')),
                'typenode'=>false,'pos_x'=>510,'pos_y'=>420,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'2','input'=>'output_2']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'11','output'=>'input_1']]],'output_2'=>['connections'=>[['node'=>'12','output'=>'input_1']]]]],

            '11' => ['id'=>11,'name'=>'fin','class'=>'fin',
                'data'=>['tipo'=>'fin'],
                'html'=>$h('Fin','#ef4444','🔴',$badge('Operador toma el control')),
                'typenode'=>false,'pos_x'=>740,'pos_y'=>320,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'10','input'=>'output_1']]]],
                'outputs'=>(object)[]],

            '12' => ['id'=>12,'name'=>'ia','class'=>'ia',
                'data'=>['tipo'=>'ia','instruccion'=>''],
                'html'=>$h('IA','#7c3aed','🤖',$badge('Respuesta libre de IA')),
                'typenode'=>false,'pos_x'=>740,'pos_y'=>520,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'10','input'=>'output_2'],['node'=>'6','input'=>'output_2'],['node'=>'9','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'13','output'=>'input_1']]]]],

            '13' => ['id'=>13,'name'=>'condicion','class'=>'condicion',
                'data'=>['tipo'=>'condicion','campo'=>'tiene_carrito','valor'=>'','etiq_si'=>'Con carrito','etiq_no'=>'Sin carrito'],
                'html'=>$h('Condición','#f59e0b','🔀',$badge('tiene_carrito').$label('Con carrito / Sin carrito')),
                'typenode'=>false,'pos_x'=>970,'pos_y'=>520,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'12','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'16','output'=>'input_1']]],'output_2'=>['connections'=>[['node'=>'14','output'=>'input_1']]]]],

            '14' => ['id'=>14,'name'=>'herramienta','class'=>'herramienta',
                'data'=>['tipo'=>'herramienta','tool'=>'elegir_reparto'],
                'html'=>$h('Herramienta','#f97316','🛠️',$badge('elegir_reparto')),
                'typenode'=>false,'pos_x'=>1200,'pos_y'=>420,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'13','input'=>'output_2']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'15','output'=>'input_1']]]]],

            '15' => ['id'=>15,'name'=>'herramienta','class'=>'herramienta',
                'data'=>['tipo'=>'herramienta','tool'=>'ver_precios'],
                'html'=>$h('Herramienta','#f97316','🛠️',$badge('ver_precios')),
                'typenode'=>false,'pos_x'=>1200,'pos_y'=>620,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'14','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'16','output'=>'input_1']]]]],

            '16' => ['id'=>16,'name'=>'herramienta','class'=>'herramienta',
                'data'=>['tipo'=>'herramienta','tool'=>'agregar_al_carrito'],
                'html'=>$h('Herramienta','#f97316','🛠️',$badge('agregar_al_carrito')),
                'typenode'=>false,'pos_x'=>1430,'pos_y'=>520,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'13','input'=>'output_1'],['node'=>'15','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'17','output'=>'input_1']]]]],

            '17' => ['id'=>17,'name'=>'herramienta','class'=>'herramienta',
                'data'=>['tipo'=>'herramienta','tool'=>'ver_carrito'],
                'html'=>$h('Herramienta','#f97316','🛠️',$badge('ver_carrito')),
                'typenode'=>false,'pos_x'=>1660,'pos_y'=>520,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'16','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'18','output'=>'input_1']]]]],

            '18' => ['id'=>18,'name'=>'herramienta','class'=>'herramienta',
                'data'=>['tipo'=>'herramienta','tool'=>'crear_pedido'],
                'html'=>$h('Herramienta','#f97316','🛠️',$badge('crear_pedido')),
                'typenode'=>false,'pos_x'=>1890,'pos_y'=>520,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'17','input'=>'output_1']]]],
                'outputs'=>['output_1'=>['connections'=>[['node'=>'19','output'=>'input_1']]]]],

            '19' => ['id'=>19,'name'=>'fin','class'=>'fin',
                'data'=>['tipo'=>'fin'],
                'html'=>$h('Fin','#ef4444','🔴',$badge('Pedido confirmado')),
                'typenode'=>false,'pos_x'=>2120,'pos_y'=>520,
                'inputs'=>['input_1'=>['connections'=>[['node'=>'18','input'=>'output_1']]]],
                'outputs'=>(object)[]],
        ]]]];

        \App\Models\IaFlujo::create([
            'nombre'     => 'Flujo Principal',
            'definicion' => $def,
            'activo'     => true,
        ]);

        return response()->json(['ok' => true, 'msg' => 'Flujo creado. Eliminá esta ruta de web.php.']);
    });
    // ── FIN SEED TEMPORAL ────────────────────────────────────────────────────

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
