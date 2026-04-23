@extends('admin.layout')
@section('title', 'Test Bot')

@push('styles')
<style>
    #chat-messages { display: flex; flex-direction: column; gap: 0.5rem; width: 100%; }
    .msg-row { width: 100%; display: flex; }
    .msg-row-out { justify-content: flex-end; }
    .msg-row-in  { justify-content: flex-start; }
    .msg-row > div { max-width: 75%; }
    .bubble { width: 100%; padding: 0.5rem 0.75rem; border-radius: 0.75rem; font-size: 0.85rem; line-height: 1.5; word-break: break-word; }
    .bubble-in  { background: #f0f0f0; color: #111; border-bottom-left-radius: 2px; }
    .bubble-out { background: #dcf8c6; color: #111; border-bottom-right-radius: 2px; }
    .bubble-time { font-size: 0.65rem; color: #999; margin-top: 2px; }
    .msg-row-out .bubble-time { text-align: right; }
    .msg-row-in  .bubble-time { text-align: left; }
    .bubble strong { font-weight: 700; }
    .bubble em   { font-style: italic; }
    .bubble code { background: rgba(0,0,0,.07); padding: 1px 4px; border-radius: 3px; font-size: .8em; }
    .esc-btn { transition: all .15s; }
    .esc-btn:disabled { opacity: .5; cursor: not-allowed; }
    .esc-btn.running { background: #f59e0b !important; }
</style>
@endpush

@section('content')
<div class="max-w-4xl mx-auto space-y-4">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-lg font-bold text-gray-800">🧪 Test Bot</h1>
        <button id="btn-reset"
            class="text-xs text-red-500 border border-red-200 px-3 py-1 rounded-full hover:bg-red-50 transition">
            Reiniciar conversación
        </button>
    </div>

    {{-- Selector de localidad --}}
    <div class="bg-white border border-gray-200 rounded-xl px-4 py-3 flex items-center gap-3">
        <label class="text-xs font-semibold text-gray-500 shrink-0">Localidad simulada:</label>
        <select id="sel-localidad"
            class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
            <option value="">— Sin localidad —</option>
            @foreach($localidades as $loc)
                <option value="{{ $loc->id }}" {{ $cliente->localidad_id == $loc->id ? 'selected' : '' }}>
                    {{ $loc->nombre }}
                </option>
            @endforeach
        </select>
        <button id="btn-set-localidad"
            class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition shrink-0">
            Aplicar
        </button>
        <span id="loc-ok" class="text-green-500 text-xs hidden">✓</span>
    </div>

    {{-- Escenarios predefinidos --}}
    <div class="bg-white border border-gray-200 rounded-xl px-4 py-3 space-y-2">
        <p class="text-xs font-semibold text-gray-500">Escenarios automáticos:</p>
        <div class="flex flex-wrap gap-2" id="escenarios-btns">
            <button class="esc-btn text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-100 font-medium"
                data-escenario="pedido_completo">🛒 Pedido completo</button>
            <button class="esc-btn text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-100 font-medium"
                data-escenario="elegir_fecha">📅 Elegir fecha</button>
            <button class="esc-btn text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-100 font-medium"
                data-escenario="lo_mismo">🔁 Lo mismo de siempre</button>
            <button class="esc-btn text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-100 font-medium"
                data-escenario="consulta_estado">📦 Consultar estado pedido</button>
            <button class="esc-btn text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-100 font-medium"
                data-escenario="cambiar_de_opinion">❌ Cambiar de opinión</button>
        </div>
        <p id="esc-status" class="text-xs text-amber-600 hidden">⏳ Ejecutando escenario...</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        {{-- Ventana de chat (2/3) --}}
        <div class="md:col-span-2 bg-white border border-gray-200 rounded-xl overflow-hidden flex flex-col" style="height:520px">
            <div id="chat-scroll" class="flex-1 overflow-y-auto p-4">
                <div id="chat-messages">
                    @foreach($mensajes as $m)
                    <div class="msg-row {{ $m->direction === 'incoming' ? 'msg-row-in' : 'msg-row-out' }}">
                        <div>
                            <div class="bubble {{ $m->direction === 'incoming' ? 'bubble-in' : 'bubble-out' }}">{!! nl2br(e($m->message)) !!}</div>
                            <div class="bubble-time">{{ $m->created_at->format('H:i') }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="border-t border-gray-100 px-3 py-2 flex gap-2 bg-gray-50">
                <textarea id="msg-input" rows="1"
                    placeholder="Escribí un mensaje..."
                    class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-300"
                    style="max-height:100px; overflow-y:auto"></textarea>
                <button id="btn-send"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 rounded-xl text-sm font-semibold transition shrink-0">
                    Enviar
                </button>
            </div>
        </div>

        {{-- Panel de estado (1/3) --}}
        <div class="space-y-3">
            <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-3">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado del cliente</p>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 text-xs">Estado</span>
                        <span id="st-estado" class="font-mono text-xs text-indigo-600">—</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 text-xs">Fecha elegida</span>
                        <span id="st-fecha" class="font-mono text-xs text-green-600">—</span>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-2">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Carrito</p>
                <div id="st-carrito" class="text-xs text-gray-400 italic">vacío</div>
                <div id="st-total" class="text-sm font-bold text-gray-800 hidden"></div>
            </div>

            <button id="btn-refresh-estado"
                class="w-full text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition">
                ↻ Actualizar estado
            </button>
        </div>
    </div>

    <p class="text-xs text-gray-400 text-center">Esta conversación no se envía por WhatsApp. Cambiá la localidad para probar distintas configuraciones.</p>
</div>
@endsection

@section('scripts')
<script>
const mensajeUrl  = '{{ route('admin.test_bot.mensaje') }}';
const resetUrl    = '{{ route('admin.test_bot.reset') }}';
const estadoUrl   = '{{ route('admin.test_bot.estado') }}';
const iaPasoUrl   = '{{ route('admin.test_bot.ia_paso') }}';
const csrfToken   = '{{ csrf_token() }}';

const chatMessages = document.getElementById('chat-messages');
const chatScroll   = document.getElementById('chat-scroll');
const msgInput     = document.getElementById('msg-input');

// ── Objetivos de escenarios IA ───────────────────────────
const OBJETIVOS = {
    pedido_completo:    'Hacer un pedido completo: elegir fecha de entrega, pedir al menos 2 unidades de algún producto disponible y confirmar el pedido.',
    elegir_fecha:       'Elegir una fecha de entrega disponible, consultar qué productos hay para ese día y agregar uno al carrito.',
    lo_mismo:          'Pedir lo mismo que el pedido anterior ("lo mismo de siempre") y confirmarlo.',
    consulta_estado:    'Consultar el estado de tu último pedido.',
    cambiar_de_opinion: 'Iniciar un pedido, agregar productos al carrito y luego cancelar o cambiar de opinión antes de confirmar.',
};

function scrollBottom() { chatScroll.scrollTop = chatScroll.scrollHeight; }
scrollBottom();

function now() { return new Date().toLocaleTimeString('es-AR', {hour:'2-digit', minute:'2-digit'}); }

function escHtml(s) {
    s = s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    s = s.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>');
    s = s.replace(/_([^_\n]+)_/g, '<em>$1</em>');
    s = s.replace(/~([^~\n]+)~/g, '<s>$1</s>');
    s = s.replace(/`([^`\n]+)`/g, '<code>$1</code>');
    s = s.replace(/\n/g, '<br>');
    return s;
}

function addBubble(text, direction, time) {
    const wrap = document.createElement('div');
    wrap.className = 'msg-row ' + (direction === 'incoming' ? 'msg-row-in' : 'msg-row-out');
    wrap.innerHTML = `<div><div class="bubble ${direction === 'incoming' ? 'bubble-in' : 'bubble-out'}">${escHtml(text)}</div><div class="bubble-time">${time}</div></div>`;
    chatMessages.appendChild(wrap);
    scrollBottom();
}

function getLocalidadId() { return document.getElementById('sel-localidad').value || ''; }

// ── Enviar un mensaje ────────────────────────────────────
async function enviarMensaje(texto) {
    if (!texto) return;
    msgInput.value = '';
    msgInput.style.height = 'auto';

    addBubble(texto, 'incoming', now());

    const typing = document.createElement('div');
    typing.id = 'typing';
    typing.className = 'flex justify-start';
    typing.innerHTML = '<div class="bubble bubble-in text-gray-400 italic">escribiendo...</div>';
    chatMessages.appendChild(typing);
    scrollBottom();

    try {
        const res  = await fetch(mensajeUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
            body: JSON.stringify({ mensaje: texto, localidad_id: getLocalidadId() || null }),
        });
        const data = await res.json();
        document.getElementById('typing')?.remove();
        if (data.respuesta) addBubble(data.respuesta, 'outgoing', now());
    } catch(e) {
        document.getElementById('typing')?.remove();
        addBubble('❌ Error al contactar el servidor', 'outgoing', '');
    }

    await refreshEstado();
}

// ── Envío manual ─────────────────────────────────────────
async function enviar() {
    const texto = msgInput.value.trim();
    if (texto) await enviarMensaje(texto);
}

document.getElementById('btn-send').addEventListener('click', enviar);
msgInput.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar(); } });
msgInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
});

// ── Escenarios automáticos con IA ────────────────────────
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

function getHistorial() {
    const rows = chatMessages.querySelectorAll('.msg-row');
    const hist = [];
    rows.forEach(row => {
        const bubble = row.querySelector('.bubble');
        if (!bubble) return;
        const direction = row.classList.contains('msg-row-in') ? 'incoming' : 'outgoing';
        hist.push({ direction, message: bubble.innerText });
    });
    return hist;
}

async function pedirSiguienteMensajeIA(objetivo) {
    const historial = getHistorial();
    try {
        const res = await fetch(iaPasoUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ objetivo, historial }),
        });
        return await res.json(); // { fin: bool, mensaje: string|null }
    } catch (e) {
        return { fin: true, mensaje: null };
    }
}

async function correrEscenario(nombre) {
    const objetivo = OBJETIVOS[nombre];
    if (!objetivo) return;

    const btns = document.querySelectorAll('.esc-btn');
    btns.forEach(b => { b.disabled = true; });
    const statusEl = document.getElementById('esc-status');
    statusEl.classList.remove('hidden');

    const MAX_PASOS = 12;
    for (let paso = 0; paso < MAX_PASOS; paso++) {
        statusEl.textContent = `🤖 IA pensando paso ${paso + 1}...`;
        const { fin, mensaje } = await pedirSiguienteMensajeIA(objetivo);

        if (fin || !mensaje) break;

        await sleep(400);
        await enviarMensaje(mensaje);
        await sleep(900);
    }

    btns.forEach(b => { b.disabled = false; });
    statusEl.textContent = '⏳ Ejecutando escenario...';
    statusEl.classList.add('hidden');
}

document.querySelectorAll('.esc-btn').forEach(btn => {
    btn.addEventListener('click', () => correrEscenario(btn.dataset.escenario));
});

// ── Panel de estado ──────────────────────────────────────
async function refreshEstado() {
    try {
        const locId = getLocalidadId();
        const url   = estadoUrl + (locId ? '?localidad_id=' + locId : '');
        const res   = await fetch(url);
        const data  = await res.json();

        document.getElementById('st-estado').textContent = data.estado || 'activo';
        document.getElementById('st-fecha').textContent  = data.fecha_elegida || '—';

        const carritoEl = document.getElementById('st-carrito');
        const totalEl   = document.getElementById('st-total');

        if (data.items && data.items.length > 0) {
            carritoEl.innerHTML = data.items.map(i =>
                `<div class="flex justify-between py-0.5 border-b border-gray-50">
                    <span class="text-gray-700 truncate mr-2">${escHtml(i.des)}</span>
                    <span class="text-gray-500 shrink-0">${i.cant}</span>
                </div>`
            ).join('');
            totalEl.textContent = 'Total: $' + data.total.toLocaleString('es-AR', {minimumFractionDigits: 2});
            totalEl.classList.remove('hidden');
        } else {
            carritoEl.innerHTML = '<span class="text-gray-400 italic">vacío</span>';
            totalEl.classList.add('hidden');
        }
    } catch(e) { /* silencioso */ }
}

document.getElementById('btn-refresh-estado').addEventListener('click', refreshEstado);
refreshEstado();

// ── Aplicar localidad ────────────────────────────────────
document.getElementById('btn-set-localidad').addEventListener('click', async () => {
    const locId = getLocalidadId();
    await fetch(resetUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
        body: JSON.stringify({ localidad_id: locId || null }),
    });
    chatMessages.innerHTML = '';
    document.getElementById('loc-ok').classList.remove('hidden');
    setTimeout(() => document.getElementById('loc-ok').classList.add('hidden'), 2000);
    await refreshEstado();
});

// ── Reiniciar ────────────────────────────────────────────
document.getElementById('btn-reset').addEventListener('click', async () => {
    if (!confirm('¿Reiniciar la conversación de prueba?')) return;
    await fetch(resetUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
        body: JSON.stringify({ localidad_id: getLocalidadId() || null }),
    });
    chatMessages.innerHTML = '';
    await refreshEstado();
});
</script>
@endsection
