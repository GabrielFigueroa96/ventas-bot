@extends('admin.layout')
@section('title', 'Test Bot')

@push('styles')
<style>
    #chat-messages {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        width: 100%;
    }
    .msg-row { width: 100%; }
    .msg-row-out { display: flex; justify-content: flex-end; }
    .msg-row-in  { display: flex; justify-content: flex-start; }
    .bubble {
        max-width: 75%;
        padding: 0.5rem 0.75rem;
        border-radius: 0.75rem;
        font-size: 0.85rem;
        line-height: 1.5;
        word-break: break-word;
    }
    .bubble-in {
        background: #f0f0f0;
        color: #111;
        border-bottom-left-radius: 2px;
    }
    .bubble-out {
        background: #dcf8c6;
        color: #111;
        border-bottom-right-radius: 2px;
    }
    .bubble-time {
        font-size: 0.65rem;
        color: #999;
        margin-top: 2px;
    }
    .msg-row-out .bubble-time { text-align: right; }
    .msg-row-in  .bubble-time { text-align: left; }
    .bubble em   { font-style: italic; }
    .bubble code { background: rgba(0,0,0,.07); padding: 1px 4px; border-radius: 3px; font-size: .8em; }
</style>
@endpush

@section('content')
<div class="max-w-2xl mx-auto space-y-4">

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

    {{-- Ventana de chat --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden flex flex-col" style="height:520px">
        {{-- Mensajes --}}
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

        {{-- Input --}}
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

    <p class="text-xs text-gray-400 text-center">Esta conversación no se envía por WhatsApp. Cambiá la localidad para probar distintas configuraciones.</p>
</div>
@endsection

@section('scripts')
<script>
const mensajeUrl   = '{{ route('admin.test_bot.mensaje') }}';
const resetUrl     = '{{ route('admin.test_bot.reset') }}';
const csrfToken    = '{{ csrf_token() }}';

const chatMessages = document.getElementById('chat-messages');
const chatScroll   = document.getElementById('chat-scroll');
const msgInput     = document.getElementById('msg-input');

function scrollBottom() {
    chatScroll.scrollTop = chatScroll.scrollHeight;
}
scrollBottom();

function addBubble(text, direction, time) {
    const wrap = document.createElement('div');
    wrap.className = 'msg-row ' + (direction === 'incoming' ? 'msg-row-in' : 'msg-row-out');
    wrap.innerHTML = `
        <div>
            <div class="bubble ${direction === 'incoming' ? 'bubble-in' : 'bubble-out'}">${escHtml(text)}</div>
            <div class="bubble-time">${time}</div>
        </div>`;
    chatMessages.appendChild(wrap);
    scrollBottom();
}

function escHtml(s) {
    // Escape HTML
    s = s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    // WhatsApp markdown
    s = s.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>');
    s = s.replace(/_([^_\n]+)_/g, '<em>$1</em>');
    s = s.replace(/~([^~\n]+)~/g, '<s>$1</s>');
    s = s.replace(/`([^`\n]+)`/g, '<code>$1</code>');
    // Newlines
    s = s.replace(/\n/g, '<br>');
    return s;
}

function getLocalidadId() {
    return document.getElementById('sel-localidad').value || '';
}

// Enviar mensaje
async function enviar() {
    const texto = msgInput.value.trim();
    if (!texto) return;
    msgInput.value = '';
    msgInput.style.height = 'auto';

    addBubble(texto, 'incoming', new Date().toLocaleTimeString('es-AR', {hour:'2-digit', minute:'2-digit'}));

    // Indicador de escritura
    const typing = document.createElement('div');
    typing.id = 'typing';
    typing.className = 'flex justify-start';
    typing.innerHTML = '<div class="bubble bubble-in text-gray-400 italic">escribiendo...</div>';
    chatMessages.appendChild(typing);
    scrollBottom();

    try {
        const res = await fetch(mensajeUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
            body: JSON.stringify({ mensaje: texto, localidad_id: getLocalidadId() || null }),
        });
        const data = await res.json();
        document.getElementById('typing')?.remove();
        if (data.respuesta) {
            addBubble(data.respuesta, 'outgoing', new Date().toLocaleTimeString('es-AR', {hour:'2-digit', minute:'2-digit'}));
        }
    } catch(e) {
        document.getElementById('typing')?.remove();
        addBubble('❌ Error al contactar el servidor', 'outgoing', '');
    }
}

document.getElementById('btn-send').addEventListener('click', enviar);
msgInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar(); }
});
msgInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
});

// Aplicar localidad
document.getElementById('btn-set-localidad').addEventListener('click', async () => {
    const locId = getLocalidadId();
    await fetch(mensajeUrl.replace('/mensaje', '/reset'), {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
        body: JSON.stringify({ localidad_id: locId || null }),
    });
    // Cambiar localidad y reiniciar visualmente
    chatMessages.innerHTML = '';
    document.getElementById('loc-ok').classList.remove('hidden');
    setTimeout(() => document.getElementById('loc-ok').classList.add('hidden'), 2000);
});

// Reiniciar
document.getElementById('btn-reset').addEventListener('click', async () => {
    if (!confirm('¿Reiniciar la conversación de prueba?')) return;
    await fetch(mensajeUrl.replace('/mensaje', '/reset'), {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
        body: JSON.stringify({ localidad_id: getLocalidadId() || null }),
    });
    chatMessages.innerHTML = '';
});
</script>
@endsection
