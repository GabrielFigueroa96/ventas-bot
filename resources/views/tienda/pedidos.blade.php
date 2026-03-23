@extends('tienda.layout')
@section('title', 'Mis pedidos — ' . ($empresaNombre ?? $empresa->nombre_ia ?? 'Tienda'))

@section('content')
<div class="max-w-2xl mx-auto">

    <div class="flex items-center justify-between mb-5">
        <h1 class="text-xl font-bold text-gray-800">Mis pedidos</h1>
        <a href="{{ route('tienda.index', ['slug' => $slug]) }}"
            class="text-sm text-red-600 hover:text-red-700 font-medium transition">
            ← Volver al catálogo
        </a>
    </div>

    @if($pedidos->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <svg class="w-14 h-14 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-gray-500 font-medium">Todavía no realizaste ningún pedido.</p>
            <a href="{{ route('tienda.index', ['slug' => $slug]) }}"
                class="inline-block mt-4 bg-red-700 hover:bg-red-800 text-white text-sm font-semibold rounded-xl px-6 py-2.5 transition">
                Ver productos
            </a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($pedidos as $pedido)
                @php
                    $items = $itemsPorNro[$pedido->nro] ?? collect();
                @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                    {{-- Header del pedido --}}
                    <div class="px-5 py-4 flex items-center justify-between gap-3 border-b border-gray-50">
                        <div class="flex items-center gap-3 min-w-0">
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">Pedido #{{ $pedido->nro }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $pedido->pedido_at ? $pedido->pedido_at->format('d/m/Y H:i') : ($pedido->fecha ? $pedido->fecha->format('d/m/Y') : '—') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $pedido->estadoCss() }}">
                                {{ $pedido->estadoLabel() }}
                            </span>
                        </div>
                    </div>

                    {{-- Items --}}
                    <div class="px-5 py-3 space-y-2">
                        @foreach($items as $item)
                            @php $esPorKilo = strtolower($item->kilos ?? 0) > 0 || strtolower($item->descrip ?? '') !== $item->descrip; @endphp
                            <div class="flex justify-between items-center text-sm">
                                <div class="flex-1 pr-3">
                                    <span class="text-gray-700">{{ $item->descrip }}</span>
                                    <span class="text-gray-400 text-xs ml-1">
                                        × {{ $item->cant }}
                                        {{ ($item->kilos ?? 0) > 0 ? 'kg' : 'u.' }}
                                    </span>
                                </div>
                                <span class="font-medium text-gray-700 whitespace-nowrap">
                                    ${{ number_format($item->neto, 2, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Footer --}}
                    <div class="px-5 py-3 bg-gray-50 flex items-center justify-between gap-3 text-sm">
                        <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                            <span>
                                {{ $pedido->tipo_entrega === 'retiro' ? '🏪 Retiro' : '🚚 Envío' }}
                            </span>
                            @if($pedido->forma_pago)
                                <span>💳 {{ \App\Models\IaEmpresa::MEDIOS_PAGO[$pedido->forma_pago] ?? $pedido->forma_pago }}</span>
                            @endif
                            @if($pedido->direccion)
                                <span class="truncate max-w-[160px]" title="{{ $pedido->direccion }}">
                                    📍 {{ $pedido->direccion }}
                                </span>
                            @endif
                        </div>
                        <p class="font-bold text-red-700 whitespace-nowrap">
                            ${{ number_format($pedido->total, 2, ',', '.') }}
                        </p>
                    </div>

                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
