@extends('admin.layout')
@section('title', $cliente->name ?? $cliente->phone)

@section('content')
<a href="{{ route('admin.clientes') }}" class="text-sm text-red-600 hover:underline mb-4 inline-block">← Volver</a>

{{-- Cabecera cliente --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="flex items-center gap-4">
        <div class="bg-red-100 text-red-700 rounded-full w-14 h-14 flex items-center justify-center text-2xl font-bold">
            {{ strtoupper(substr($cliente->name ?? '?', 0, 1)) }}
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $cliente->name ?? 'Sin nombre' }}</h1>
            <p class="text-gray-500 text-sm">{{ $cliente->phone }} · Cliente desde {{ $cliente->created_at->format('d/m/Y') }}</p>
            {{-- Cuenta vinculada --}}
            <div class="mt-1.5 flex items-center gap-2" id="cuenta-display">
                @if($cliente->cuenta)
                    <span class="text-xs bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full font-medium">
                        {{ $cliente->cuenta->nom }}
                        <span class="opacity-60">#{{ $cliente->cuenta->cod }}</span>
                    </span>
                    <button onclick="abrirBuscarCuenta()" class="text-xs text-gray-400 hover:text-gray-600 underline">cambiar</button>
                @else
                    <span class="text-xs text-gray-400">Sin cuenta vinculada</span>
                    <button onclick="abrirBuscarCuenta()" class="text-xs text-red-600 hover:underline font-medium">+ Vincular cuenta</button>
                @endif
            </div>
            {{-- Buscador de cuentas (oculto por defecto) --}}
            <div id="cuenta-buscar" class="hidden mt-2 relative w-72">
                <input type="text" id="cuenta-input" placeholder="Buscar por nombre o código..."
                    class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                    oninput="buscarCuenta(this.value)" autocomplete="off">
                <ul id="cuenta-resultados"
                    class="hidden absolute z-10 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 text-sm max-h-48 overflow-y-auto"></ul>
            </div>
        </div>
    </div>

    {{-- Botón editar --}}
    <button onclick="abrirModalEditar()"
        class="shrink-0 flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 hover:border-red-400 hover:text-red-600 px-3 py-1.5 rounded-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
        Editar
    </button>

    {{-- Control bot/humano --}}
    @if($cliente->modo === 'humano')
        <div class="flex items-center gap-3">
            <span class="flex items-center gap-1.5 text-sm font-medium text-orange-600 bg-orange-50 border border-orange-200 px-3 py-1.5 rounded-full">
                <span class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></span>
                Control manual activo
            </span>
            <form method="POST" action="{{ route('admin.chat.liberar', $cliente) }}">
                @csrf
                <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white text-sm px-4 py-1.5 rounded-lg transition">
                    Dejar al bot
                </button>
            </form>
        </div>
    @else
        <form method="POST" action="{{ route('admin.chat.tomar', $cliente) }}">
            @csrf
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2 rounded-lg transition">
                Tomar control del chat
            </button>
        </form>
    @endif
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 rounded-lg mb-4">{{ session('success') }}</div>
@endif
@if(session('ok'))
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 rounded-lg mb-4">{{ session('ok') }}</div>
@endif
@if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2 rounded-lg mb-4">{{ $errors->first() }}</div>
@endif

{{-- Modal editar cliente --}}
<div id="modal-editar" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <h3 class="font-bold text-gray-800 text-base mb-5">Editar cliente</h3>

        <form method="POST" action="{{ route('admin.cliente.update', $cliente) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Nombre</label>
                    <input type="text" name="name" value="{{ old('name', $cliente->name) }}"
                        placeholder="Nombre del cliente"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Teléfono</label>
                    <input type="text" name="phone" value="{{ old('phone', $cliente->phone) }}" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-3 sm:col-span-1">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Calle</label>
                    <input type="text" name="calle" value="{{ old('calle', $cliente->calle) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Número</label>
                    <input type="text" name="numero" value="{{ old('numero', $cliente->numero) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Piso/Depto</label>
                    <input type="text" name="dato_extra" value="{{ old('dato_extra', $cliente->dato_extra) }}"
                        placeholder="Ej: 2° A"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Localidad</label>
                <select name="localidad_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    <option value="">— Sin localidad —</option>
                    @foreach($localidades as $loc)
                        <option value="{{ $loc->id }}" {{ old('localidad_id', $cliente->localidad_id) == $loc->id ? 'selected' : '' }}>
                            {{ $loc->nombre }}{{ $loc->provincia ? ' (' . $loc->provincia . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
                <select name="estado"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    <option value="activo"   {{ old('estado', $cliente->estado) === 'activo'   ? 'selected' : '' }}>Activo</option>
                    <option value="inactivo" {{ old('estado', $cliente->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                </select>
            </div>

            <div class="flex gap-2 pt-1">
                <button type="button" onclick="cerrarModalEditar()"
                    class="flex-1 border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium py-2 rounded-lg transition">
                    Cancelar
                </button>
                <button type="submit"
                    class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm font-medium py-2 rounded-lg transition">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6 items-start">

    {{-- Conversación + envío --}}
    <div class="bg-white rounded-xl shadow flex flex-col" style="height: calc(100vh - 220px);">
        <div class="px-5 py-4 border-b font-semibold text-gray-700 flex items-center justify-between shrink-0">
            <span>Conversación</span>
            <div class="flex items-center gap-3">
                @if($cliente->modo === 'humano')
                    <span class="text-xs text-orange-500 font-medium">Respondiendo manualmente</span>
                @else
                    <span class="text-xs text-green-500 font-medium">Bot activo</span>
                @endif
                <button onclick="abrirModalImprimir()"
                    class="flex items-center gap-1 text-xs text-gray-500 hover:text-red-600 border border-gray-200 hover:border-red-300 px-2.5 py-1 rounded-lg transition"
                    title="Imprimir conversación">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir
                </button>
            </div>
        </div>

        {{-- Modal imprimir --}}
        <div id="modal-imprimir" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
                <h3 class="font-bold text-gray-800 text-base mb-4">Imprimir conversación</h3>
                <form id="form-imprimir" target="_blank" method="GET" action="{{ route('admin.chat.imprimir', $cliente) }}">
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-1 block">Desde</label>
                            <input type="date" name="desde" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 mb-1 block">Hasta</label>
                            <input type="date" name="hasta" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                        </div>
                    </div>
                    <div class="flex gap-2 mt-5">
                        <button type="button" onclick="cerrarModalImprimir()"
                            class="flex-1 border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium py-2 rounded-lg transition">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm font-medium py-2 rounded-lg transition">
                            Ver / Imprimir
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Mensajes --}}
        <div class="relative flex-1 min-h-0">
            <div id="chat-box" class="p-4 space-y-3 overflow-y-auto h-full">
                @forelse($mensajes as $msg)
                <div class="flex {{ $msg->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}" data-id="{{ $msg->id }}">
                    <div class="max-w-xs px-4 py-2 rounded-2xl text-sm
                        {{ $msg->direction === 'outgoing'
                            ? 'bg-red-600 text-white rounded-br-none'
                            : 'bg-gray-100 text-gray-800 rounded-bl-none' }}">
                        @if($msg->media_path)
                            <img src="{{ asset($msg->media_path) }}"
                                 class="rounded-lg max-w-full mb-1 cursor-pointer"
                                 onclick="window.open(this.src)"
                                 alt="Imagen">
                        @endif
                        @if($msg->message)
                            @php
                                $txt = e($msg->message);
                                $txt = preg_replace('/\*([^*]+)\*/', '<strong class="font-bold">$1</strong>', $txt);
                                $txt = preg_replace('/_([^_]+)_/', '<em class="italic">$1</em>', $txt);
                                $txt = nl2br($txt);
                            @endphp
                            <p class="leading-snug">{!! $txt !!}</p>
                        @endif
                        <p class="text-xs mt-1 opacity-60 flex items-center gap-1">
                            {{ $msg->fecha }}
                            @if($msg->direction === 'outgoing')
                                @if($msg->status === 'read')
                                    <span title="Leído" class="text-blue-300">✓✓</span>
                                @elseif($msg->status === 'delivered')
                                    <span title="Entregado" class="opacity-80">✓✓</span>
                                @elseif($msg->status === 'sent')
                                    <span title="Enviado" class="opacity-80">✓</span>
                                @endif
                            @endif
                        </p>
                    </div>
                </div>
                @empty
                <p class="text-gray-400 text-sm text-center py-6">Sin mensajes.</p>
                @endforelse
            </div>
            {{-- Indicador de mensaje nuevo --}}
            <button id="nuevo-msg"
                class="hidden absolute bottom-2 left-1/2 -translate-x-1/2 bg-red-600 text-white text-xs px-4 py-1.5 rounded-full shadow-lg hover:bg-red-700 transition">
                ↓ Nuevo mensaje
            </button>
        </div>

        {{-- Panel de envío (solo en modo humano) --}}
        @if($cliente->modo === 'humano')
        <div class="border-t p-4 bg-gray-50">
            <form method="POST" action="{{ route('admin.chat.enviar', $cliente) }}" enctype="multipart/form-data" id="form-enviar">
                @csrf

                {{-- Preview archivo --}}
                <div id="file-preview" class="hidden mb-2 flex items-center gap-2 bg-white border rounded-lg px-3 py-2 text-sm text-gray-600">
                    <span id="file-name"></span>
                    <button type="button" onclick="clearFile()" class="ml-auto text-gray-400 hover:text-red-500">✕</button>
                </div>

                <div class="flex gap-2">
                    {{-- Adjuntar archivo --}}
                    <label class="cursor-pointer flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-gray-300 hover:bg-gray-100 transition shrink-0" title="Adjuntar imagen o PDF">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        <input type="file" name="archivo" id="archivo" class="hidden" accept="image/*,.pdf" onchange="previewFile(this)">
                    </label>

                    {{-- Texto --}}
                    <input type="text" name="mensaje" id="mensaje" placeholder="Escribí un mensaje..."
                        class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">

                    {{-- Enviar --}}
                    <button type="submit"
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm transition shrink-0">
                        Enviar
                    </button>
                </div>
            </form>
        </div>
        @endif
    </div>

    {{-- Info del cliente + Pedidos --}}
    <div class="space-y-4">

        {{-- Tarjeta info --}}
        <div class="bg-white rounded-xl shadow p-4 grid grid-cols-2 gap-3 text-sm">

            {{-- Domicilio --}}
            <div>
                <p class="text-xs text-gray-400 font-medium mb-0.5">Domicilio</p>
                @php
                    $partes = array_filter([$cliente->calle, $cliente->numero, $cliente->dato_extra]);
                @endphp
                <p class="text-gray-700">{{ $partes ? implode(' ', $partes) : '—' }}</p>
            </div>

            {{-- Localidad --}}
            <div>
                <p class="text-xs text-gray-400 font-medium mb-0.5">Localidad</p>
                <p class="text-gray-700">
                    {{ $cliente->localidad ?: '—' }}
                    @if($cliente->provincia)
                        <span class="text-gray-400">({{ $cliente->provincia }})</span>
                    @endif
                </p>
            </div>

            {{-- Total pedidos --}}
            <div>
                <p class="text-xs text-gray-400 font-medium mb-0.5">Pedidos realizados</p>
                <p class="text-gray-700 font-semibold">{{ $totalPedidos }}</p>
            </div>

            {{-- Último pedido --}}
            <div>
                <p class="text-xs text-gray-400 font-medium mb-0.5">Último pedido</p>
                @if($ultimoPedidoAt)
                    @php $dias = \Carbon\Carbon::parse($ultimoPedidoAt)->diffInDays(now()); @endphp
                    <p class="text-gray-700">
                        @if($dias === 0)
                            <span class="text-green-600 font-medium">Hoy</span>
                        @elseif($dias === 1)
                            <span class="text-green-600 font-medium">Ayer</span>
                        @else
                            <span class="{{ $dias > 30 ? 'text-red-500' : 'text-gray-700' }} font-medium">hace {{ $dias }} días</span>
                        @endif
                    </p>
                @else
                    <p class="text-gray-400">—</p>
                @endif
            </div>

        </div>

        {{-- Pedidos (id para refresh dinámico) --}}
        <div id="pedidos-panel" class="space-y-4">
            @include('admin.partials.pedidos', compact('pedidos', 'factventas'))
        </div>

    </div>

</div>

<script>
const chatBox      = document.getElementById('chat-box');
const mensajes     = chatBox?.querySelectorAll('[data-id]');
let lastId         = mensajes?.length ? parseInt([...mensajes].at(-1).dataset.id) : 0;
let atBottom       = true;
const pollUrl      = "{{ route('admin.chat.mensajes', $cliente) }}";
const pedidosUrl   = "{{ route('admin.chat.pedidos', $cliente) }}";
let lastPedidoReg  = {{ $lastPedidoReg }};
let pollingMsg     = false;   // lock: evita polls simultáneos

// Detectar si el admin scrolleó hacia arriba (no forzar scroll en ese caso)
chatBox?.addEventListener('scroll', () => {
    atBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 40;
});

function scrollBottom(smooth = false) {
    if (!atBottom) return;
    chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
}

function bubbleHtml(msg) {
    const isOut  = msg.direction === 'outgoing';
    const align  = isOut ? 'justify-end' : 'justify-start';
    const bubble = isOut
        ? 'bg-red-600 text-white rounded-br-none'
        : 'bg-gray-100 text-gray-800 rounded-bl-none';

    let content = '';
    if (msg.media_path) {
        content += `<img src="${msg.media_path}" class="rounded-lg max-w-full mb-1 cursor-pointer" onclick="window.open(this.src)" alt="Imagen">`;
    }
    if (msg.message) {
        content += `<p class="leading-snug">${formatWpp(msg.message)}</p>`;
    }

    let tick = '';
    if (isOut) {
        if (msg.status === 'read')      tick = '<span title="Leído" class="text-blue-300">✓✓</span>';
        else if (msg.status === 'delivered') tick = '<span title="Entregado" class="opacity-80">✓✓</span>';
        else if (msg.status === 'sent') tick = '<span title="Enviado" class="opacity-80">✓</span>';
    }

    return `
        <div class="flex ${align}" data-id="${msg.id}">
            <div class="max-w-xs px-4 py-2 rounded-2xl text-sm ${bubble}">
                ${content}
                <p class="text-xs mt-1 opacity-60 flex items-center gap-1">${msg.created_at} ${tick}</p>
            </div>
        </div>`;
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function formatWpp(str) {
    let s = escHtml(str);
    s = s.replace(/\*([^*]+)\*/g, '<strong class="font-bold">$1</strong>');
    s = s.replace(/_([^_]+)_/g, '<em class="italic">$1</em>');
    s = s.replace(/\n/g, '<br>');
    return s;
}

async function pollMensajes() {
    if (pollingMsg) return;     // ya hay un poll en curso, esperamos
    pollingMsg = true;
    try {
        const res  = await fetch(`${pollUrl}?since=${lastId}`);
        const data = await res.json();
        if (data.length > 0) {
            let hayNuevos = false;
            data.forEach(msg => {
                // Deduplicar: saltar si el mensaje ya está en el DOM
                if (chatBox.querySelector(`[data-id="${msg.id}"]`)) {
                    lastId = msg.id;
                    return;
                }
                chatBox.insertAdjacentHTML('beforeend', bubbleHtml(msg));
                lastId = msg.id;
                hayNuevos = true;
            });
            if (hayNuevos) {
                scrollBottom();
                if (!atBottom) {
                    document.getElementById('nuevo-msg')?.classList.remove('hidden');
                }
            }
        }
    } catch (_) {}
    finally {
        pollingMsg = false;
    }
}

async function pollPedidos() {
    try {
        const res  = await fetch(pedidosUrl);
        const data = await res.json();
        if (data.lastReg > lastPedidoReg) {
            lastPedidoReg = data.lastReg;
            document.getElementById('pedidos-panel').innerHTML = data.html;
        }
    } catch (_) {}
}

// Abrir modal editar si hay errores de validación
@if($errors->any())
abrirModalEditar();
@endif

// Scroll inicial al fondo
scrollBottom();

// Polling mensajes: 1.5s | pedidos: 3s
setInterval(pollMensajes, 1500);
setInterval(pollPedidos, 3000);

// Botón "nuevo mensaje"
document.getElementById('nuevo-msg')?.addEventListener('click', () => {
    atBottom = true;
    scrollBottom(true);
    document.getElementById('nuevo-msg').classList.add('hidden');
});

// ── Envío AJAX ──────────────────────────────────────────────────────────────
const formEnviar = document.getElementById('form-enviar');

formEnviar?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn    = formEnviar.querySelector('button[type=submit]');
    const input  = document.getElementById('mensaje');
    const archivo = document.getElementById('archivo');

    if (!input.value.trim() && !archivo?.files.length) return;

    // Deshabilitar mientras envía
    btn.disabled   = true;
    btn.textContent = '...';

    // Burbuja optimista (solo si hay texto, no para imágenes solas)
    const textoLocal = input.value.trim();
    let tmpId = null;
    if (textoLocal) {
        tmpId = 'tmp-' + Date.now();
        chatBox.insertAdjacentHTML('beforeend', `
            <div class="flex justify-end opacity-60" id="${tmpId}">
                <div class="max-w-xs px-4 py-2 rounded-2xl text-sm bg-red-600 text-white rounded-br-none">
                    <p class="leading-snug">${formatWpp(textoLocal)}</p>
                    <p class="text-xs mt-1 opacity-60">enviando...</p>
                </div>
            </div>`);
        atBottom = true;
        scrollBottom();
    }

    try {
        const formData = new FormData(formEnviar);
        const res = await fetch(formEnviar.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json',
                        'X-CSRF-TOKEN': formEnviar.querySelector('[name=_token]').value },
            body: formData,
        });

        const data = await res.json();

        if (res.ok) {
            // Eliminar burbuja temporal si existe
            if (tmpId) document.getElementById(tmpId)?.remove();

            // Insertar solo si el poll no lo agregó ya
            if (!chatBox.querySelector(`[data-id="${data.id}"]`)) {
                chatBox.insertAdjacentHTML('beforeend', bubbleHtml(data));
            }
            lastId = data.id;
            atBottom = true;
            scrollBottom();
        } else {
            if (tmpId) document.getElementById(tmpId)?.remove();
            alert(data.message ?? 'Error al enviar.');
        }
    } catch (err) {
        if (tmpId) document.getElementById(tmpId)?.remove();
        alert('Error de red al enviar el mensaje.');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Enviar';
        input.value     = '';
        clearFile();
    }
});

document.getElementById('mensaje')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        formEnviar?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    }
});

function previewFile(input) {
    const preview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    if (input.files.length > 0) {
        fileName.textContent = input.files[0].name;
        preview.classList.remove('hidden');
    }
}

function clearFile() {
    document.getElementById('archivo').value = '';
    document.getElementById('file-preview').classList.add('hidden');
}

// ── Vinculación de cuenta ────────────────────────────────────────────────────
const buscarUrl  = "{{ route('admin.cuentas.buscar') }}";
const cuentaUrl  = "{{ route('admin.chat.setCuenta', $cliente) }}";
let   buscarTimer = null;

function abrirBuscarCuenta() {
    document.getElementById('cuenta-buscar').classList.remove('hidden');
    document.getElementById('cuenta-input').focus();
}

function buscarCuenta(q) {
    clearTimeout(buscarTimer);
    const lista = document.getElementById('cuenta-resultados');
    if (q.length < 1) { lista.classList.add('hidden'); return; }
    buscarTimer = setTimeout(async () => {
        const res  = await fetch(`${buscarUrl}?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        lista.innerHTML = '';
        if (!data.length) {
            lista.innerHTML = '<li class="px-3 py-2 text-gray-400">Sin resultados</li>';
        } else {
            data.forEach(c => {
                const li = document.createElement('li');
                li.className = 'px-3 py-2 hover:bg-gray-50 cursor-pointer flex justify-between';
                li.innerHTML = `<span class="font-medium text-gray-800">${escHtml(c.nom)}</span>
                                <span class="text-gray-400 text-xs">#${escHtml(c.cod)}</span>`;
                li.onclick = () => asignarCuenta(c);
                lista.appendChild(li);
            });
        }
        lista.classList.remove('hidden');
    }, 300);
}

async function asignarCuenta(cuenta) {
    await fetch(cuentaUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                         ?? document.querySelector('[name=_token]')?.value ?? '',
        },
        body: JSON.stringify({ cuenta_cod: cuenta.cod }),
    });

    // Actualizar display sin recargar
    document.getElementById('cuenta-display').innerHTML = `
        <span class="text-xs bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full font-medium">
            ${escHtml(cuenta.nom)} <span class="opacity-60">#${escHtml(cuenta.cod)}</span>
        </span>
        <button onclick="abrirBuscarCuenta()" class="text-xs text-gray-400 hover:text-gray-600 underline">cambiar</button>`;

    document.getElementById('cuenta-buscar').classList.add('hidden');
    document.getElementById('cuenta-input').value = '';
    document.getElementById('cuenta-resultados').classList.add('hidden');
}

// Cerrar dropdown al hacer click fuera
document.addEventListener('click', e => {
    if (!e.target.closest('#cuenta-buscar') && !e.target.closest('#cuenta-display')) {
        document.getElementById('cuenta-buscar')?.classList.add('hidden');
    }
});

// ── Modal editar cliente ─────────────────────────────────────────────────────
function abrirModalEditar() {
    document.getElementById('modal-editar').classList.remove('hidden');
}
function cerrarModalEditar() {
    document.getElementById('modal-editar').classList.add('hidden');
}
document.getElementById('modal-editar')?.addEventListener('click', function(e) {
    if (e.target === this) cerrarModalEditar();
});

// ── Modal imprimir ────────────────────────────────────────────────────────────
function abrirModalImprimir() {
    // Prellenar fechas: hoy como "hasta", hace 7 días como "desde"
    const hoy = new Date();
    const hace7 = new Date(hoy); hace7.setDate(hoy.getDate() - 7);
    const fmt = d => d.toISOString().slice(0, 10);
    const form = document.getElementById('form-imprimir');
    form.querySelector('[name=desde]').value = fmt(hace7);
    form.querySelector('[name=hasta]').value = fmt(hoy);
    document.getElementById('modal-imprimir').classList.remove('hidden');
}

function cerrarModalImprimir() {
    document.getElementById('modal-imprimir').classList.add('hidden');
}

document.getElementById('modal-imprimir')?.addEventListener('click', function(e) {
    if (e.target === this) cerrarModalImprimir();
});
</script>
@endsection
