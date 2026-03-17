@extends('admin.layout')
@section('title', $cliente->name ?? $cliente->phone)

@section('content')
<a href="{{ route('admin.clientes') }}" class="text-sm text-red-600 hover:underline mb-4 inline-block">← Volver</a>

{{-- Cabecera cliente --}}
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-4">
        <div class="bg-red-100 text-red-700 rounded-full w-14 h-14 flex items-center justify-center text-2xl font-bold">
            {{ strtoupper(substr($cliente->name ?? '?', 0, 1)) }}
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $cliente->name ?? 'Sin nombre' }}</h1>
            <p class="text-gray-500 text-sm">{{ $cliente->phone }} · Cliente desde {{ $cliente->created_at->format('d/m/Y') }}</p>
        </div>
    </div>

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
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 rounded-lg mb-4">
        {{ session('success') }}
    </div>
@endif

<div class="grid md:grid-cols-2 gap-6">

    {{-- Conversación + envío --}}
    <div class="bg-white rounded-xl shadow flex flex-col">
        <div class="px-5 py-4 border-b font-semibold text-gray-700 flex items-center justify-between">
            <span>Conversación</span>
            @if($cliente->modo === 'humano')
                <span class="text-xs text-orange-500 font-medium">Respondiendo manualmente</span>
            @else
                <span class="text-xs text-green-500 font-medium">Bot activo</span>
            @endif
        </div>

        {{-- Mensajes --}}
        <div class="relative flex-1">
            <div id="chat-box" class="p-4 space-y-3 overflow-y-auto" style="max-height: 420px;">
                @forelse($mensajes as $msg)
                <div class="flex {{ $msg->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}" data-id="{{ $msg->id }}">
                    <div class="max-w-xs px-4 py-2 rounded-2xl text-sm
                        {{ $msg->direction === 'outgoing'
                            ? 'bg-red-600 text-white rounded-br-none'
                            : 'bg-gray-100 text-gray-800 rounded-bl-none' }}">
                        <p>{{ $msg->message }}</p>
                        <p class="text-xs mt-1 opacity-60">{{ $msg->created_at }}</p>
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

    {{-- Pedidos --}}
    <div class="space-y-4">
        @forelse($pedidos as $nro => $items)
        @php
            $first = $items->first();
            $key   = "{$first->venta}-{$first->pv}";
            $fact  = $factventas->get($key);
        @endphp
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <div>
                    <span class="font-bold text-gray-800">#{{ $nro }}</span>
                    <span class="text-xs text-gray-400 ml-2">{{ $first->fecha }}</span>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium
                    {{ $first->estado == 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' }}">
                    {{ $first->estado_texto }}
                </span>
            </div>
            <div class="px-5 py-3">
                <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Pedido</p>
                <ul class="text-sm text-gray-600 space-y-0.5">
                    @foreach($items as $item)
                        <li>• {{ $item->descrip }} — {{ $item->kilos }} kg/u</li>
                    @endforeach
                </ul>
            </div>
            @if($fact?->isNotEmpty())
            <div class="px-5 py-3 bg-gray-50 border-t">
                <p class="text-xs text-gray-400 uppercase font-semibold mb-2">
                    Como salió
                    @if($fact->first()->fact)
                        — Factura <span class="text-gray-700 font-bold">{{ $fact->first()->fact }}</span>
                    @endif
                </p>
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500">
                        <tr>
                            <th class="text-left pb-1">Artículo</th>
                            <th class="text-right pb-1">Cant</th>
                            <th class="text-right pb-1">Kilos</th>
                            <th class="text-right pb-1">Neto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($fact as $f)
                        <tr>
                            <td class="py-1 text-gray-700">{{ $f->descrip }}</td>
                            <td class="py-1 text-right text-gray-500">{{ $f->cant }}</td>
                            <td class="py-1 text-right text-gray-500">{{ number_format($f->kilos, 3) }}</td>
                            <td class="py-1 text-right font-medium text-gray-800">${{ number_format($f->neto, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-300 font-semibold text-sm">
                        <tr>
                            <td colspan="2" class="pt-1 text-gray-500">Total</td>
                            <td class="pt-1 text-right text-gray-600">{{ number_format($fact->sum('kilos'), 3) }} kg</td>
                            <td class="pt-1 text-right text-red-700">${{ number_format($fact->sum('neto'), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
        </div>
        @empty
        <div class="bg-white rounded-xl shadow px-5 py-6 text-center text-gray-400 text-sm">Sin pedidos.</div>
        @endforelse
    </div>

</div>

<script>
const chatBox   = document.getElementById('chat-box');
const mensajes  = chatBox?.querySelectorAll('[data-id]');
let lastId      = mensajes?.length ? parseInt([...mensajes].at(-1).dataset.id) : 0;
let atBottom    = true;
const pollUrl   = "{{ route('admin.chat.mensajes', $cliente) }}";

// Detectar si el admin scrolleó hacia arriba (no forzar scroll en ese caso)
chatBox?.addEventListener('scroll', () => {
    atBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 40;
});

function scrollBottom() {
    if (atBottom) chatBox.scrollTop = chatBox.scrollHeight;
}

function bubbleHtml(msg) {
    const isOut  = msg.direction === 'outgoing';
    const align  = isOut ? 'justify-end' : 'justify-start';
    const bubble = isOut
        ? 'bg-red-600 text-white rounded-br-none'
        : 'bg-gray-100 text-gray-800 rounded-bl-none';
    return `
        <div class="flex ${align}" data-id="${msg.id}">
            <div class="max-w-xs px-4 py-2 rounded-2xl text-sm ${bubble}">
                <p>${escHtml(msg.message)}</p>
                <p class="text-xs mt-1 opacity-60">${msg.created_at}</p>
            </div>
        </div>`;
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function pollMensajes() {
    try {
        const res  = await fetch(`${pollUrl}?since=${lastId}`);
        const data = await res.json();
        if (data.length > 0) {
            data.forEach(msg => {
                chatBox.insertAdjacentHTML('beforeend', bubbleHtml(msg));
                lastId = msg.id;
            });
            scrollBottom();

            // Indicador de nuevo mensaje si el admin scrolleó arriba
            if (!atBottom) {
                document.getElementById('nuevo-msg')?.classList.remove('hidden');
            }
        }
    } catch (_) {}
}

// Scroll inicial al fondo
scrollBottom();

// Polling cada 3 segundos
setInterval(pollMensajes, 3000);

// Botón "nuevo mensaje"
document.getElementById('nuevo-msg')?.addEventListener('click', () => {
    atBottom = true;
    scrollBottom();
    document.getElementById('nuevo-msg').classList.add('hidden');
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

document.getElementById('mensaje')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('form-enviar').submit();
    }
});
</script>
@endsection
