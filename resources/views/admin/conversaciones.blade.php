@extends('admin.layout')
@section('title', 'Conversaciones')

@push('styles')
<style>
    main {
        max-width: none !important;
        padding: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }
    #conv-wrapper { flex: 1; min-height: 0; }

    /* Mobile: fijar la cadena de altura para que el chat ocupe la pantalla completa */
    @media (max-width: 1023px) {
        html { height: 100%; }
        body {
            height: 100vh;
            height: 100dvh;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
        }
        body > .flex-1 {
            min-height: 0;
            overflow: hidden;
        }
    }
</style>
@endpush

@section('content')
<div id="conv-wrapper" class="flex overflow-hidden border-t border-gray-200">

    {{-- ── Panel izquierdo: lista de clientes ─────────────────────────── --}}
    <div id="panel-lista" class="w-full md:w-80 shrink-0 flex flex-col border-r border-gray-100 bg-white">
        {{-- Header --}}
        <div class="px-4 py-3 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Conversaciones</h2>
            <input type="text" id="buscar-cliente" placeholder="Buscar..." autocomplete="off"
                class="mt-2 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
        </div>

        {{-- Lista --}}
        <div id="lista-clientes" class="flex-1 overflow-y-auto divide-y divide-gray-50">
            @foreach($clientes as $cli)
            <button type="button"
                data-id="{{ $cli->id }}"
                onclick="seleccionarCliente({{ $cli->id }}, this)"
                class="conv-item w-full text-left px-4 py-3 hover:bg-red-50 transition flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-red-100 text-red-700 flex items-center justify-center text-sm font-bold shrink-0">
                    {{ strtoupper(substr($cli->name ?? '?', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-baseline gap-1">
                        <span class="text-sm font-medium text-gray-800 truncate">{{ $cli->name ?? $cli->phone }}</span>
                        <span class="text-xs text-gray-400 shrink-0">
                            {{ $cli->last_message_at ? \Carbon\Carbon::parse($cli->last_message_at)->format('d/m H:i') : '' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1 text-xs text-gray-400 truncate">
                        <span class="truncate">{{ $cli->phone }}</span>
                        @if($cli->localidad)
                            <span class="shrink-0">·</span>
                            <span class="truncate">{{ $cli->localidad }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1 mt-0.5">
                        @if($cli->modo === 'humano')
                            <span class="w-1.5 h-1.5 bg-orange-400 rounded-full shrink-0"></span>
                        @endif
                        <p class="text-xs text-gray-400 truncate">
                            @if($cli->last_direction === 'outgoing')
                                <span class="text-gray-300">↩ </span>
                            @endif
                            {{ Str::limit($cli->last_message ?? '', 45) }}
                        </p>
                    </div>
                </div>
            </button>
            @endforeach
        </div>
    </div>

    {{-- ── Panel derecho: chat ──────────────────────────────────────────── --}}
    <div id="chat-panel" class="hidden md:flex flex-1 flex-col bg-gray-50 min-w-0">
        {{-- Estado vacío --}}
        <div id="chat-empty" class="flex-1 flex items-center justify-center text-gray-300">
            <div class="text-center space-y-2">
                <svg class="w-12 h-12 mx-auto opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="text-sm">Seleccioná una conversación</p>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
const csrfToken  = document.querySelector('meta[name=csrf-token]').content;
let chatInterval = null;
let lastMsgId    = 0;
let atBottom     = true;
let currentId    = null;
let enviarUrl    = null;
let tomarUrl     = null;
let liberarUrl   = null;
let pollUrl      = null;
let pollingMsg   = false;

// ── Buscar en lista ───────────────────────────────────────────────────────────
document.getElementById('buscar-cliente').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.conv-item').forEach(btn => {
        const texto = btn.textContent.toLowerCase();
        btn.style.display = texto.includes(q) ? '' : 'none';
    });
});

// ── Responsive: volver a la lista en mobile ───────────────────────────────────
function volverALista() {
    document.getElementById('panel-lista').classList.remove('hidden');
    document.getElementById('chat-panel').classList.add('hidden');
    document.getElementById('chat-panel').classList.remove('flex');
    clearInterval(chatInterval);
    currentId = null;
    document.querySelectorAll('.conv-item').forEach(b => b.classList.remove('bg-red-50', 'border-l-2', 'border-red-500'));
}

// ── Seleccionar cliente ───────────────────────────────────────────────────────
async function seleccionarCliente(id, btn) {
    if (currentId === id) return;
    currentId = id;

    // Marcar activo
    document.querySelectorAll('.conv-item').forEach(b => b.classList.remove('bg-red-50', 'border-l-2', 'border-red-500'));
    btn.classList.add('bg-red-50', 'border-l-2', 'border-red-500');

    // En mobile: ocultar lista y mostrar chat
    const esMobile = window.innerWidth < 768;
    if (esMobile) {
        document.getElementById('panel-lista').classList.add('hidden');
        document.getElementById('chat-panel').classList.remove('hidden');
        document.getElementById('chat-panel').classList.add('flex');
    }

    // Detener polling anterior
    clearInterval(chatInterval);

    // Mostrar loader
    const panel = document.getElementById('chat-panel');
    panel.innerHTML = `
        <div class="flex-1 flex items-center justify-center text-gray-400">
            <span class="animate-pulse text-sm">Cargando...</span>
        </div>`;

    try {
        const res  = await fetch(`/admin/conversaciones/${id}/panel`);
        if (!res.ok) throw new Error(`Error ${res.status}`);
        const data = await res.json();

        // Inyectar HTML
        panel.innerHTML = data.html;

        // Inicializar estado
        lastMsgId  = data.lastId;
        pollUrl    = data.pollUrl;
        enviarUrl  = data.enviarUrl;
        tomarUrl   = data.tomarUrl;
        liberarUrl = data.liberarUrl;
        atBottom   = true;

        scrollBottom();
        iniciarPolling();
        bindFormEnvio();

        // Scroll chat box listener
        const box = document.getElementById('chat-box');
        box?.addEventListener('scroll', () => {
            atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 40;
        });

        document.getElementById('nuevo-msg')?.addEventListener('click', () => {
            atBottom = true;
            scrollBottom(true);
            document.getElementById('nuevo-msg').classList.add('hidden');
        });
    } catch (err) {
        panel.innerHTML = `
            <div class="flex-1 flex flex-col items-center justify-center text-gray-400 gap-3">
                <p class="text-sm">Error al cargar la conversación.</p>
                <button onclick="seleccionarCliente(${id}, document.querySelector('[data-id=\\'${id}\\']'))"
                    class="text-xs bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 transition">
                    Reintentar
                </button>
            </div>`;
    }
}

// ── Scroll ────────────────────────────────────────────────────────────────────
function scrollBottom(smooth = false) {
    const box = document.getElementById('chat-box');
    if (!box || !atBottom) return;
    box.scrollTo({ top: box.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
}

// ── Polling mensajes ──────────────────────────────────────────────────────────
function iniciarPolling() {
    chatInterval = setInterval(async () => {
        if (pollingMsg || !pollUrl) return;
        pollingMsg = true;
        try {
            const res  = await fetch(`${pollUrl}?since=${lastMsgId}`);
            const data = await res.json();
            const box  = document.getElementById('chat-box');
            if (!box) return;
            let hayNuevos = false;
            data.forEach(msg => {
                if (box.querySelector(`[data-id="${msg.id}"]`)) { lastMsgId = msg.id; return; }
                box.insertAdjacentHTML('beforeend', bubbleHtml(msg));
                lastMsgId = msg.id;
                hayNuevos = true;
            });
            if (hayNuevos) {
                scrollBottom();
                if (!atBottom) document.getElementById('nuevo-msg')?.classList.remove('hidden');
            }
        } catch (_) {}
        finally { pollingMsg = false; }
    }, 1500);
}

// ── Burbuja ───────────────────────────────────────────────────────────────────
function bubbleHtml(msg) {
    const isOut  = msg.direction === 'outgoing';
    const align  = isOut ? 'justify-end' : 'justify-start';
    const bubble = isOut ? 'bg-red-600 text-white rounded-br-none' : 'bg-gray-100 text-gray-800 rounded-bl-none';
    let content  = '';
    if (msg.media_path) content += `<img src="${msg.media_path}" class="rounded-lg max-w-full mb-1 cursor-pointer" onclick="window.open(this.src)" alt="Imagen">`;
    if (msg.message)    content += `<p class="leading-snug">${formatWpp(msg.message)}</p>`;
    return `<div class="flex ${align}" data-id="${msg.id}">
        <div class="max-w-sm px-4 py-2 rounded-2xl text-sm ${bubble}">
            ${content}
            <p class="text-xs mt-1 opacity-60">${msg.created_at}</p>
        </div>
    </div>`;
}

function escHtml(str) { return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function formatWpp(str) {
    let s = escHtml(str);
    s = s.replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
    s = s.replace(/_([^_]+)_/g, '<em>$1</em>');
    s = s.replace(/\n/g, '<br>');
    return s;
}

// ── Envío ─────────────────────────────────────────────────────────────────────
function bindFormEnvio() {
    const form = document.getElementById('form-enviar');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn    = form.querySelector('button[type=submit]');
        const input  = document.getElementById('mensaje');
        const archivo = document.getElementById('archivo');
        if (!input.value.trim() && !archivo?.files.length) return;

        btn.disabled = true; btn.textContent = '...';

        const textoLocal = input.value.trim();
        let tmpId = null;
        if (textoLocal) {
            tmpId = 'tmp-' + Date.now();
            const box = document.getElementById('chat-box');
            box.insertAdjacentHTML('beforeend', `
                <div class="flex justify-end opacity-60" id="${tmpId}">
                    <div class="max-w-sm px-4 py-2 rounded-2xl text-sm bg-red-600 text-white rounded-br-none">
                        <p class="leading-snug">${formatWpp(textoLocal)}</p>
                        <p class="text-xs mt-1 opacity-60">enviando...</p>
                    </div>
                </div>`);
            atBottom = true; scrollBottom();
        }

        try {
            const fd = new FormData(form);
            const res = await fetch(enviarUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: fd,
            });
            const data = await res.json();
            if (tmpId) document.getElementById(tmpId)?.remove();
            if (res.ok) {
                const box = document.getElementById('chat-box');
                if (!box.querySelector(`[data-id="${data.id}"]`)) {
                    box.insertAdjacentHTML('beforeend', bubbleHtml(data));
                }
                lastMsgId = data.id; atBottom = true; scrollBottom();
            } else {
                alert(data.message ?? 'Error al enviar.');
            }
        } catch (_) {
            if (tmpId) document.getElementById(tmpId)?.remove();
            alert('Error de red.');
        } finally {
            btn.disabled = false; btn.textContent = 'Enviar';
            input.value = ''; clearFile();
        }
    });

    document.getElementById('mensaje')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }
    });
}

// ── Tomar / liberar control ───────────────────────────────────────────────────
async function tomarControl() {
    await fetch(tomarUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } });
    recargarPanel();
}
async function liberarControl() {
    await fetch(liberarUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } });
    recargarPanel();
}
async function recargarPanel() {
    if (!currentId) return;
    clearInterval(chatInterval);
    try {
        const res  = await fetch(`/admin/conversaciones/${currentId}/panel`);
        const data = await res.json();
        document.getElementById('chat-panel').innerHTML = data.html;
        enviarUrl  = data.enviarUrl;
        tomarUrl   = data.tomarUrl;
        liberarUrl = data.liberarUrl;
        atBottom   = true;
        scrollBottom();
        iniciarPolling();
        bindFormEnvio();
        const box = document.getElementById('chat-box');
        box?.addEventListener('scroll', () => {
            atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 40;
        });
    } catch (_) {}
}

// ── Adjunto ───────────────────────────────────────────────────────────────────
function previewFile(input) {
    const preview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    if (input.files.length > 0) { fileName.textContent = input.files[0].name; preview.classList.remove('hidden'); }
}
function clearFile() {
    const archivo = document.getElementById('archivo');
    if (archivo) archivo.value = '';
    document.getElementById('file-preview')?.classList.add('hidden');
}
</script>
@endsection
