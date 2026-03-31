@extends('admin.layout')
@section('title', 'Configuración')

@section('content')
<div class="max-w-2xl mx-auto space-y-4">
    <h1 class="text-xl font-bold text-gray-800">Configuración</h1>

    @if(session('ok'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">{{ session('ok') }}</div>
    @endif

    {{-- Tabs --}}
    <div class="flex border-b border-gray-200 overflow-x-auto" style="scrollbar-width:none">
        @foreach([
            ['negocio',  'Negocio'],
            ['entrega',  'Entrega y pagos'],
            ['horarios', 'Horarios'],
            ['bot',      'Bot'],
            ['notif',    'Notificaciones'],
        ] as [$tid, $tlabel])
        <button type="button" onclick="cambiarTab('{{ $tid }}')" id="tab-{{ $tid }}"
            class="tab-btn shrink-0 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                   {{ $tid === 'negocio' ? 'border-red-600 text-red-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            {{ $tlabel }}
        </button>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.configuracion.save') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        {{-- ══════════════ TAB: NEGOCIO ══════════════ --}}
        <div id="panel-negocio" class="tab-panel space-y-4">

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-700 mb-0.5">Información del negocio</h2>
                    <p class="text-xs text-gray-400 mb-2">Dirección, horarios de atención. El bot usa esto para responder consultas generales.</p>
                    <textarea name="bot_info" rows="4"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
                        placeholder="Ej: Estamos en Av. Italia 1234, Rosario. Atendemos lunes a sábado de 8 a 13hs.">{{ old('bot_info', $config->bot_info) }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-4 pt-3 border-t border-gray-100">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Sucursal</label>
                        <input type="text" name="suc" value="{{ old('suc', $config->suc) }}"
                            placeholder="Ej: 001" maxlength="10"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                        <p class="text-xs text-gray-400 mt-1">Código de sucursal para los pedidos del bot.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Punto de venta</label>
                        <input type="text" name="pv" value="{{ old('pv', $config->pv) }}"
                            placeholder="Ej: 0001" maxlength="10"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                        <p class="text-xs text-gray-400 mt-1">Punto de venta para los pedidos del bot.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-700 mb-0.5">Logo</h2>
                    <p class="text-xs text-gray-400">Aparece en el encabezado de la web y en el menú del panel.</p>
                </div>
                @if($config->imagen_tienda)
                    <div class="flex items-center gap-4">
                        <img src="{{ asset($config->imagen_tienda) }}?v={{ $config->updated_at?->timestamp }}"
                            alt="Logo actual" class="w-20 h-20 object-cover rounded-xl border border-gray-200">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Logo actual</p>
                            <label class="flex items-center gap-1.5 mt-2 text-red-600 cursor-pointer text-xs">
                                <input type="checkbox" name="eliminar_imagen_tienda" value="1" class="accent-red-600">
                                Eliminar logo
                            </label>
                        </div>
                    </div>
                @endif
                <input type="file" name="imagen_tienda" accept="image/*"
                    class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                <p class="text-xs text-gray-400">JPG, PNG o WebP. Recomendado: cuadrado, hasta 2 MB.</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-700 mb-0.5">Imagen de bienvenida</h2>
                    <p class="text-xs text-gray-400">Se envía automáticamente al cliente la primera vez que escribe.</p>
                </div>
                @if($config->imagen_bienvenida)
                    <div class="flex items-center gap-4">
                        <img src="{{ asset($config->imagen_bienvenida) }}?v={{ $config->updated_at?->timestamp }}" alt="Imagen actual"
                            class="w-24 h-24 object-cover rounded-lg border border-gray-200">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Imagen actual</p>
                            <label class="flex items-center gap-1.5 mt-2 text-red-600 cursor-pointer text-xs">
                                <input type="checkbox" name="eliminar_imagen" value="1" class="accent-red-600">
                                Eliminar imagen
                            </label>
                        </div>
                    </div>
                @endif
                <input type="file" name="imagen_bienvenida" accept="image/*"
                    class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                <p class="text-xs text-gray-400">JPG, PNG o WebP. Hasta 5 MB.</p>
            </div>

        </div>{{-- /panel-negocio --}}

        {{-- ══════════════ TAB: ENTREGA Y PAGOS ══════════════ --}}
        <div id="panel-entrega" class="tab-panel hidden space-y-4">

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700">Límites de pedido</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Monto mínimo</label>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-400">$</span>
                            <input type="number" name="pedido_minimo"
                                value="{{ old('pedido_minimo', $config->pedido_minimo ?? 0) }}"
                                min="0" step="0.01" placeholder="0"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Poner 0 para no exigir mínimo.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Máx. pedidos pendientes por cliente</label>
                        <input type="number" name="max_pedidos_pendientes"
                            value="{{ old('max_pedidos_pendientes', $config->max_pedidos_pendientes ?? 0) }}"
                            min="0" step="1" placeholder="0"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                        <p class="text-xs text-gray-400 mt-1">Si el cliente ya tiene esta cantidad en <strong>Pendiente</strong>, el bot no acepta uno nuevo. Poner 0 para no limitar.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700">Medios de pago</h2>
                <div class="flex flex-col gap-2">
                    @foreach(\App\Models\IaEmpresa::MEDIOS_PAGO as $key => $label)
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="bot_medios_pago[]" value="{{ $key }}"
                                {{ in_array($key, old('bot_medios_pago', $config->bot_medios_pago ?? array_keys(\App\Models\IaEmpresa::MEDIOS_PAGO))) ? 'checked' : '' }}
                                class="accent-red-600">
                            <span class="text-gray-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

        </div>{{-- /panel-entrega --}}

        {{-- ══════════════ TAB: HORARIOS ══════════════ --}}
        <div id="panel-horarios" class="tab-panel hidden space-y-4">

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-700 mb-0.5">Horario de atención</h2>
                    <p class="text-xs text-gray-400">Configurá los días y horarios. Podés agregar múltiples turnos por día (ej: mañana y tarde).</p>
                </div>

                <div id="horarios-builder" class="space-y-2">
                    @foreach(\App\Models\IaEmpresa::DIAS_LABEL as $num => $nombre)
                    <div class="border border-gray-200 rounded-lg p-3" id="dia-row-{{ $num }}">
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" class="dia-toggle accent-red-600" data-dia="{{ $num }}" id="dia-toggle-{{ $num }}">
                                <span class="text-sm font-medium text-gray-700">{{ $nombre }}</span>
                            </label>
                            <button type="button" onclick="agregarTurno({{ $num }})"
                                id="btn-agregar-{{ $num }}"
                                class="hidden text-xs text-red-600 hover:text-red-800 font-medium border border-red-200 rounded px-2 py-0.5">
                                + Turno
                            </button>
                        </div>
                        <div class="turnos-container mt-2 space-y-1.5 hidden" id="turnos-{{ $num }}"></div>
                    </div>
                    @endforeach
                </div>
                <input type="hidden" name="bot_horarios" id="bot-horarios-input">
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-700 mb-0.5">Fechas cerradas</h2>
                    <p class="text-xs text-gray-400">Feriados, vacaciones u otros días sin atención.</p>
                </div>
                <div class="flex gap-2">
                    <input type="date" id="fecha-picker" min="{{ now()->format('Y-m-d') }}"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    <button type="button" onclick="agregarFecha()"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg">
                        + Agregar
                    </button>
                </div>
                <div id="fechas-tags" class="flex flex-wrap gap-2 min-h-6"></div>
                <input type="hidden" name="bot_fechas_cerrado" id="fechas-input"
                    value="{{ implode(',', $config->bot_fechas_cerrado ?? []) }}">
            </div>

        </div>{{-- /panel-horarios --}}

        {{-- ══════════════ TAB: BOT ══════════════ --}}
        <div id="panel-bot" class="tab-panel hidden space-y-4">

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700">Identidad</h2>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Nombre del asistente</label>
                    <input type="text" name="nombre_ia" value="{{ old('nombre_ia', $config->nombre_ia) }}"
                        placeholder="Ej: Martina, Asistente La Carnicería, etc."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    <p class="text-xs text-gray-400 mt-1">El bot se presentará con este nombre al nuevo cliente.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700">Comportamiento</h2>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2">Atención de clientes nuevos</label>
                    <div class="flex flex-col gap-2">
                        <label class="flex items-start gap-2 text-sm cursor-pointer">
                            <input type="radio" name="bot_atiende_nuevos" value="bot"
                                {{ old('bot_atiende_nuevos', $config->bot_atiende_nuevos ?? 'bot') === 'bot' ? 'checked' : '' }}
                                class="mt-0.5 accent-red-600">
                            <span>
                                <span class="font-medium text-gray-700">Bot</span>
                                <span class="block text-xs text-gray-400">El bot registra al cliente y toma pedidos normalmente.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm cursor-pointer">
                            <input type="radio" name="bot_atiende_nuevos" value="humano"
                                {{ old('bot_atiende_nuevos', $config->bot_atiende_nuevos ?? 'bot') === 'humano' ? 'checked' : '' }}
                                class="mt-0.5 accent-red-600">
                            <span>
                                <span class="font-medium text-gray-700">Humano</span>
                                <span class="block text-xs text-gray-400">El bot solo envía el mensaje de bienvenida. La atención queda a cargo de una persona.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-100 flex flex-col gap-3">
                    <label class="flex items-start gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="bot_puede_pedir" value="1" id="bot_puede_pedir"
                            {{ old('bot_puede_pedir', $config->bot_puede_pedir ?? true) ? 'checked' : '' }}
                            class="mt-0.5 accent-red-600">
                        <span>
                            <span class="font-medium text-gray-700">Puede tomar pedidos</span>
                            <span class="block text-xs text-gray-400">Si está desactivado, el bot solo informa precios. El cliente debe llamar o escribir para pedir.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="bot_puede_sugerir" value="1" id="bot_puede_sugerir"
                            {{ old('bot_puede_sugerir', $config->bot_puede_sugerir ?? true) ? 'checked' : '' }}
                            class="mt-0.5 accent-red-600">
                        <span>
                            <span class="font-medium text-gray-700">Puede sugerir productos</span>
                            <span class="block text-xs text-gray-400">El bot puede proponer productos al cliente de forma proactiva durante la conversación.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="bot_puede_mas_vendidos" value="1" id="bot_puede_mas_vendidos"
                            {{ old('bot_puede_mas_vendidos', $config->bot_puede_mas_vendidos ?? false) ? 'checked' : '' }}
                            class="mt-0.5 accent-red-600">
                        <span>
                            <span class="font-medium text-gray-700">Puede informar los más vendidos</span>
                            <span class="block text-xs text-gray-400">El bot puede mencionar cuáles son los productos más pedidos del negocio.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-2">
                <label class="block text-sm font-semibold text-gray-700">Instrucciones especiales</label>
                <p class="text-xs text-gray-400">Cómo tratar ciertos productos, restricciones de corte, promociones vigentes, etc.</p>
                <textarea name="bot_instrucciones" rows="6"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
                    placeholder="Ej: El asado de tira se vende en piezas de aprox. 2kg. No hacemos cortes especiales salvo pedido previo.">{{ old('bot_instrucciones', $config->bot_instrucciones) }}</textarea>
            </div>

        </div>{{-- /panel-bot --}}

        {{-- ══════════════ TAB: NOTIFICACIONES ══════════════ --}}
        <div id="panel-notif" class="tab-panel hidden space-y-4">

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700">Teléfono del negocio</h2>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Número de WhatsApp</label>
                    <input type="text" name="telefono_pedidos" value="{{ old('telefono_pedidos', $config->telefono_pedidos) }}"
                        placeholder="Ej: 5493415550000 (con código de país, sin +)"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    <p class="text-xs text-gray-400 mt-1">Se usa para recibir copia de pedidos y para enviar el código de verificación al iniciar sesión.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700">Contactos de asesores</h2>
                <p class="text-xs text-gray-400">Cuando el bot no sepa responder algo o el cliente pida hablar con una persona, compartirá estos números de WhatsApp.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Asesor 1</label>
                        <input type="text" name="contacto_asesor_1" value="{{ old('contacto_asesor_1', $config->contacto_asesor_1) }}"
                            placeholder="Ej: 5493415550001 (con código de país, sin +)"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Asesor 2 <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <input type="text" name="contacto_asesor_2" value="{{ old('contacto_asesor_2', $config->contacto_asesor_2) }}"
                            placeholder="Ej: 5493415550002 (con código de país, sin +)"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700">Notificaciones al negocio</h2>

                @if($config->telefono_pedidos)
                <div class="flex items-start gap-3">
                    <input type="checkbox" name="notif_negocio_enabled" value="1" id="notif_negocio_enabled"
                        {{ old('notif_negocio_enabled', $config->notif_negocio_enabled ?? true) ? 'checked' : '' }}
                        class="mt-0.5 accent-red-600">
                    <label for="notif_negocio_enabled" class="text-sm cursor-pointer">
                        <span class="font-medium text-gray-700">Recibir copia del pedido por WhatsApp</span>
                        <p class="text-xs text-gray-400 mt-0.5">Cuando un cliente confirme un pedido, se enviará un resumen al número {{ $config->telefono_pedidos }}.</p>
                    </label>
                </div>

                <div id="div_template" {{ old('notif_negocio_enabled', $config->notif_negocio_enabled ?? true) ? '' : 'style=display:none' }}>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Nombre del template de WhatsApp <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="text" name="notif_template_nombre" value="{{ old('notif_template_nombre', $config->notif_template_nombre) }}"
                        placeholder="Ej: notif_pedido_nuevo  o  notif_pedido_nuevo|es"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    <p class="text-xs text-gray-400 mt-1">Podés agregar el código de idioma separado por <code>|</code>, ej: <code>notif_pedido_nuevo|es_AR</code>. Por defecto usa <code>es_AR</code>.</p>
                    <div class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 space-y-1">
                        <p class="font-semibold">¿Por qué usar un template?</p>
                        <p>WhatsApp solo permite enviar mensajes de texto libre si el destinatario escribió en las últimas 24hs. Los templates funcionan siempre.</p>
                        <p class="font-semibold mt-1">Cómo crear el template en Meta Business Manager:</p>
                        <ol class="list-decimal list-inside space-y-0.5 ml-1">
                            <li>Ingresá a <strong>business.facebook.com → Configuración → Plantillas de mensajes</strong></li>
                            <li>Creá una plantilla con categoría <strong>Utilidad</strong>, idioma <strong>Español (ARG)</strong></li>
                            <li>Usá este cuerpo exacto:<br>
                                <code class="block mt-1 bg-white border border-amber-200 rounded px-2 py-1 font-mono text-xs">Pedido #&#123;&#123;1&#125;&#125; de &#123;&#123;2&#125;&#125;&#10;&#123;&#123;3&#125;&#125;&#10;Total: $&#123;&#123;4&#125;&#125;</code>
                            </li>
                            <li>Una vez aprobada, ingresá el nombre de la plantilla arriba</li>
                        </ol>
                    </div>
                </div>
                @else
                <p class="text-sm text-gray-400">Configurá primero el teléfono del negocio para habilitar estas opciones.</p>
                @endif
            </div>

            {{-- Notificaciones de estado de pedidos --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700">Notificaciones de estado de pedidos</h2>
                <p class="text-xs text-gray-400">Se envía al cliente cuando su pedido cambia a un estado relevante (Confirmado, En camino, Entregado, Cancelado).</p>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Nombre del template de WhatsApp <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="text" name="notif_estado_template" value="{{ old('notif_estado_template', $config->notif_estado_template) }}"
                        placeholder="Ej: estado_pedido  o  estado_pedido|es_AR"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    <p class="text-xs text-gray-400 mt-1">Podés agregar el código de idioma separado por <code>|</code>. Por defecto usa <code>es_AR</code>.</p>
                    <div class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 space-y-1">
                        <p class="font-semibold">Cómo crear el template en Meta Business Manager:</p>
                        <ol class="list-decimal list-inside space-y-0.5 ml-1">
                            <li>Categoría <strong>Utilidad</strong>, idioma <strong>Español (ARG)</strong></li>
                            <li>Cuerpo con dos parámetros:<br>
                                <code class="block mt-1 bg-white border border-amber-200 rounded px-2 py-1 font-mono text-xs">Hola &#123;&#123;1&#125;&#125;, &#123;&#123;2&#125;&#125;</code>
                            </li>
                            <li><code>&#123;&#123;1&#125;&#125;</code> = nombre del cliente, <code>&#123;&#123;2&#125;&#125;</code> = mensaje del estado</li>
                            <li>Una vez aprobada, ingresá el nombre arriba</li>
                        </ol>
                    </div>
                </div>
            </div>

            {{-- Seguimientos automáticos --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-5">
                <div>
                    <h2 class="text-sm font-semibold text-gray-700">Seguimientos automáticos</h2>
                    <p class="text-xs text-gray-400 mt-0.5">El bot envía mensajes automáticos a clientes según su actividad. Podés activar cada tipo y configurar el tiempo de espera.</p>
                </div>

                {{-- Carrito abandonado --}}
                <div class="border border-gray-100 rounded-xl p-4 space-y-3">
                    <label class="flex items-start gap-3 text-sm cursor-pointer">
                        <input type="checkbox" name="seguimiento_carrito_activo" value="1" id="seg_carrito"
                            {{ old('seguimiento_carrito_activo', $config->seguimiento_carrito_activo ?? true) ? 'checked' : '' }}
                            class="mt-0.5 accent-red-600" onchange="toggleSeg('carrito', this.checked)">
                        <span>
                            <span class="font-medium text-gray-700">🛒 Carrito abandonado</span>
                            <span class="block text-xs text-gray-400 mt-0.5">Avisa al cliente que dejó productos en el carrito sin confirmar el pedido.</span>
                        </span>
                    </label>
                    <div id="seg-carrito-config" class="{{ old('seguimiento_carrito_activo', $config->seguimiento_carrito_activo ?? true) ? '' : 'hidden' }} flex items-center gap-2 pl-7">
                        <span class="text-xs text-gray-500">Enviar después de</span>
                        <input type="number" name="seguimiento_carrito_horas" min="1" max="48"
                            value="{{ old('seguimiento_carrito_horas', $config->seguimiento_carrito_horas ?? 2) }}"
                            class="w-16 border border-gray-300 rounded-lg px-2 py-1 text-sm text-center focus:outline-none focus:ring-2 focus:ring-red-300">
                        <span class="text-xs text-gray-500">horas sin confirmar.</span>
                    </div>
                </div>

                {{-- Consultó sin pedir --}}
                <div class="border border-gray-100 rounded-xl p-4 space-y-3">
                    <label class="flex items-start gap-3 text-sm cursor-pointer">
                        <input type="checkbox" name="seguimiento_sin_pedido_activo" value="1" id="seg_sinpedido"
                            {{ old('seguimiento_sin_pedido_activo', $config->seguimiento_sin_pedido_activo ?? true) ? 'checked' : '' }}
                            class="mt-0.5 accent-red-600" onchange="toggleSeg('sinpedido', this.checked)">
                        <span>
                            <span class="font-medium text-gray-700">💬 Consultó pero no pidió</span>
                            <span class="block text-xs text-gray-400 mt-0.5">Avisa al cliente que tuvo conversación reciente pero no realizó ningún pedido.</span>
                        </span>
                    </label>
                    <div id="seg-sinpedido-config" class="{{ old('seguimiento_sin_pedido_activo', $config->seguimiento_sin_pedido_activo ?? true) ? '' : 'hidden' }} flex items-center gap-2 pl-7">
                        <span class="text-xs text-gray-500">Enviar si no pidió en los últimos</span>
                        <input type="number" name="seguimiento_sin_pedido_dias" min="1" max="30"
                            value="{{ old('seguimiento_sin_pedido_dias', $config->seguimiento_sin_pedido_dias ?? 3) }}"
                            class="w-16 border border-gray-300 rounded-lg px-2 py-1 text-sm text-center focus:outline-none focus:ring-2 focus:ring-red-300">
                        <span class="text-xs text-gray-500">días.</span>
                    </div>
                </div>

                {{-- Inactivos --}}
                <div class="border border-gray-100 rounded-xl p-4 space-y-3">
                    <label class="flex items-start gap-3 text-sm cursor-pointer">
                        <input type="checkbox" name="seguimiento_inactivo_activo" value="1" id="seg_inactivo"
                            {{ old('seguimiento_inactivo_activo', $config->seguimiento_inactivo_activo ?? true) ? 'checked' : '' }}
                            class="mt-0.5 accent-red-600" onchange="toggleSeg('inactivo', this.checked)">
                        <span>
                            <span class="font-medium text-gray-700">😴 Cliente inactivo</span>
                            <span class="block text-xs text-gray-400 mt-0.5">Avisa al cliente que no escribe ni pide desde hace un tiempo.</span>
                        </span>
                    </label>
                    <div id="seg-inactivo-config" class="{{ old('seguimiento_inactivo_activo', $config->seguimiento_inactivo_activo ?? true) ? '' : 'hidden' }} flex items-center gap-2 pl-7">
                        <span class="text-xs text-gray-500">Enviar si no escribe hace más de</span>
                        <input type="number" name="seguimiento_inactivo_dias" min="1" max="90"
                            value="{{ old('seguimiento_inactivo_dias', $config->seguimiento_inactivo_dias ?? 7) }}"
                            class="w-16 border border-gray-300 rounded-lg px-2 py-1 text-sm text-center focus:outline-none focus:ring-2 focus:ring-red-300">
                        <span class="text-xs text-gray-500">días.</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700">Notificaciones al cliente</h2>
                <label class="flex items-start gap-3 text-sm cursor-pointer">
                    <input type="checkbox" name="bot_notifica_estados" value="1" id="bot_notifica_estados"
                        {{ old('bot_notifica_estados', $config->bot_notifica_estados ?? true) ? 'checked' : '' }}
                        class="mt-0.5 accent-red-600">
                    <span>
                        <span class="font-medium text-gray-700">Notificar estados al cliente por WhatsApp</span>
                        <span class="block text-xs text-gray-400 mt-0.5">Cuando se avanza el estado de un pedido (confirmado, preparado, en camino, entregado), se envía un mensaje automático al cliente.</span>
                    </span>
                </label>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700">Seguridad</h2>
                @if($config->telefono_pedidos)
                <label class="flex items-start gap-3 text-sm cursor-pointer">
                    <input type="checkbox" name="two_factor_enabled" value="1" id="two_factor_enabled"
                        {{ old('two_factor_enabled', $config->two_factor_enabled ?? false) ? 'checked' : '' }}
                        class="mt-0.5 accent-red-600">
                    <span>
                        <span class="font-medium text-gray-700">Verificación en dos pasos (WhatsApp)</span>
                        <span class="block text-xs text-gray-400 mt-0.5">Al iniciar sesión se enviará un código al número {{ $config->telefono_pedidos }}.</span>
                    </span>
                </label>
                @else
                <p class="text-sm text-gray-400">Configurá primero el teléfono del negocio para habilitar la verificación en dos pasos.</p>
                @endif
            </div>

        </div>{{-- /panel-notif --}}

        <div class="flex justify-end pt-2">
            <button type="submit" class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors">
                Guardar
            </button>
        </div>

    </form>
</div>
@endsection

@section('scripts')
<script>
// ─── Seguimientos toggle ──────────────────────────────────────────────────────
function toggleSeg(tipo, activo) {
    const el = document.getElementById('seg-' + tipo + '-config');
    if (el) el.classList.toggle('hidden', !activo);
}

// ─── Tabs ─────────────────────────────────────────────────────────────────────
function cambiarTab(id) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-red-600', 'text-red-700');
        b.classList.add('border-transparent', 'text-gray-500');
    });
    document.getElementById('panel-' + id).classList.remove('hidden');
    const btn = document.getElementById('tab-' + id);
    btn.classList.remove('border-transparent', 'text-gray-500');
    btn.classList.add('border-red-600', 'text-red-700');
    history.replaceState(null, '', '#' + id);
}

// Restaurar tab desde hash o primer error
(function () {
    const hash = location.hash.replace('#', '');
    const valid = ['negocio', 'entrega', 'horarios', 'bot', 'notif'];
    cambiarTab(valid.includes(hash) ? hash : 'negocio');
})();

// ─── Notificaciones ───────────────────────────────────────────────────────────
document.getElementById('notif_negocio_enabled')?.addEventListener('change', function () {
    document.getElementById('div_template').style.display = this.checked ? '' : 'none';
});

// ─── Horarios por día ─────────────────────────────────────────────────────────
let horarios = @json($config->bot_horarios ?? null) || {};

function syncHorariosInput() {
    document.getElementById('bot-horarios-input').value = JSON.stringify(horarios);
}

function renderDia(dia) {
    const turnos     = horarios[dia] || [];
    const toggle     = document.getElementById('dia-toggle-' + dia);
    const container  = document.getElementById('turnos-' + dia);
    const btnAgregar = document.getElementById('btn-agregar-' + dia);

    toggle.checked = turnos.length > 0;

    if (turnos.length > 0) {
        container.classList.remove('hidden');
        btnAgregar.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
        btnAgregar.classList.add('hidden');
    }

    container.innerHTML = turnos.map((t, i) => `
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400 w-5">${i + 1}.</span>
            <input type="time" value="${t.de}" onchange="actualizarTurno(${dia},${i},'de',this.value)"
                class="border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-red-300">
            <span class="text-xs text-gray-400">a</span>
            <input type="time" value="${t.a}" onchange="actualizarTurno(${dia},${i},'a',this.value)"
                class="border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-red-300">
            <button type="button" onclick="quitarTurno(${dia},${i})"
                class="text-gray-400 hover:text-red-600 text-base font-bold leading-none px-1">&times;</button>
        </div>
    `).join('');

    syncHorariosInput();
}

function agregarTurno(dia) {
    if (!horarios[dia]) horarios[dia] = [];
    horarios[dia].push({ de: '08:00', a: '13:00' });
    renderDia(dia);
}

function quitarTurno(dia, idx) {
    horarios[dia].splice(idx, 1);
    if (horarios[dia].length === 0) delete horarios[dia];
    renderDia(dia);
}

function actualizarTurno(dia, idx, campo, valor) {
    if (horarios[dia] && horarios[dia][idx]) {
        horarios[dia][idx][campo] = valor;
        syncHorariosInput();
    }
}

document.querySelectorAll('.dia-toggle').forEach(cb => {
    cb.addEventListener('change', function () {
        const dia = this.dataset.dia;
        if (this.checked) {
            if (!horarios[dia] || horarios[dia].length === 0) {
                horarios[dia] = [{ de: '08:00', a: '13:00' }];
            }
        } else {
            delete horarios[dia];
        }
        renderDia(dia);
    });
});

[0,1,2,3,4,5,6].forEach(dia => renderDia(dia));

// ─── Fechas cerradas ──────────────────────────────────────────────────────────
const MESES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

function fechasActuales() {
    const val = document.getElementById('fechas-input').value.trim();
    return val ? val.split(',').filter(f => f) : [];
}

function renderFechas() {
    const fechas    = fechasActuales();
    const container = document.getElementById('fechas-tags');
    container.innerHTML = fechas.map(f => {
        const d     = new Date(f + 'T00:00:00');
        const label = `${d.getDate()} ${MESES[d.getMonth()]} ${d.getFullYear()}`;
        return `<span class="inline-flex items-center gap-1 bg-red-50 border border-red-200 text-red-700 text-xs rounded-full px-3 py-1">
            ${label}
            <button type="button" onclick="quitarFecha('${f}')" class="ml-1 hover:text-red-900 font-bold leading-none">&times;</button>
        </span>`;
    }).join('');
}

function agregarFecha() {
    const picker = document.getElementById('fecha-picker');
    const fecha  = picker.value;
    if (!fecha) return;
    const fechas = fechasActuales();
    if (!fechas.includes(fecha)) {
        fechas.push(fecha);
        fechas.sort();
        document.getElementById('fechas-input').value = fechas.join(',');
        renderFechas();
    }
    picker.value = '';
}

function quitarFecha(fecha) {
    const fechas = fechasActuales().filter(f => f !== fecha);
    document.getElementById('fechas-input').value = fechas.join(',');
    renderFechas();
}

renderFechas();
</script>
@endsection
