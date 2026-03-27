{{-- Header del cliente --}}
<div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-white shrink-0">
    <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-full bg-red-100 text-red-700 flex items-center justify-center text-sm font-bold shrink-0">
            {{ strtoupper(substr($cliente->name ?? '?', 0, 1)) }}
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-800 leading-tight">{{ $cliente->name ?? 'Sin nombre' }}</p>
            <p class="text-xs text-gray-400">{{ $cliente->phone }}</p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        @if($cliente->modo === 'humano')
            <span class="flex items-center gap-1 text-xs font-medium text-orange-600 bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-full">
                <span class="w-1.5 h-1.5 bg-orange-500 rounded-full animate-pulse"></span>
                Manual
            </span>
            <button onclick="liberarControl()"
                class="text-xs bg-gray-700 hover:bg-gray-800 text-white px-3 py-1.5 rounded-lg transition">
                Dejar al bot
            </button>
        @else
            <button onclick="tomarControl()"
                class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg transition">
                Tomar control
            </button>
        @endif
        <a href="{{ route('admin.cliente', $cliente) }}" target="_blank"
            class="text-xs text-gray-400 hover:text-gray-600 border border-gray-200 rounded-lg px-3 py-1.5 transition">
            Ver perfil
        </a>
    </div>
</div>

{{-- Mensajes --}}
<div class="relative flex-1 min-h-0">
    <div id="chat-box" class="p-4 space-y-3 overflow-y-auto h-full">
        @forelse($mensajes as $msg)
        <div class="flex {{ $msg->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}" data-id="{{ $msg->id }}">
            <div class="max-w-sm px-4 py-2 rounded-2xl text-sm
                {{ $msg->direction === 'outgoing' ? 'bg-red-600 text-white rounded-br-none' : 'bg-gray-100 text-gray-800 rounded-bl-none' }}">
                @if($msg->media_path)
                    <img src="{{ asset($msg->media_path) }}" class="rounded-lg max-w-full mb-1 cursor-pointer" onclick="window.open(this.src)" alt="Imagen">
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
                        @if($msg->status === 'read') <span class="text-blue-300">✓✓</span>
                        @elseif($msg->status === 'delivered') <span class="opacity-80">✓✓</span>
                        @elseif($msg->status === 'sent') <span class="opacity-80">✓</span>
                        @endif
                    @endif
                </p>
            </div>
        </div>
        @empty
        <p class="text-gray-400 text-sm text-center py-10">Sin mensajes.</p>
        @endforelse
    </div>
    <button id="nuevo-msg"
        class="hidden absolute bottom-2 left-1/2 -translate-x-1/2 bg-red-600 text-white text-xs px-4 py-1.5 rounded-full shadow-lg hover:bg-red-700 transition">
        ↓ Nuevo mensaje
    </button>
</div>

{{-- Panel envío (solo modo humano) --}}
@if($cliente->modo === 'humano')
<div id="panel-envio" class="border-t bg-gray-50 p-3 shrink-0">
    <form id="form-enviar" enctype="multipart/form-data">
        @csrf
        <div id="file-preview" class="hidden mb-2 flex items-center gap-2 bg-white border rounded-lg px-3 py-2 text-sm text-gray-600">
            <span id="file-name"></span>
            <button type="button" onclick="clearFile()" class="ml-auto text-gray-400 hover:text-red-500">✕</button>
        </div>
        <div class="flex gap-2">
            <label class="cursor-pointer text-gray-400 hover:text-red-500 flex items-center px-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                <input type="file" name="archivo" id="archivo" class="hidden" accept="image/*,.pdf" onchange="previewFile(this)">
            </label>
            <input type="text" name="mensaje" id="mensaje" placeholder="Escribí un mensaje..."
                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm transition shrink-0">
                Enviar
            </button>
        </div>
    </form>
</div>
@else
<div id="panel-envio" class="hidden"></div>
@endif
