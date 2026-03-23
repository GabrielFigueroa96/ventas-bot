@extends('tienda.layout')
@section('title', 'Mis pedidos — ' . ($empresaNombre ?? $empresa->nombre_ia ?? 'Tienda'))

@section('content')
<div class="max-w-2xl mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('tienda.index', ['slug' => $slug]) }}"
            class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-lg font-bold text-gray-800">Mis pedidos</h1>
    </div>

    @if($pedidos->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <p class="text-gray-500 font-medium mb-1">Todavía no realizaste ningún pedido</p>
            <p class="text-sm text-gray-400 mb-5">Explorá el catálogo y hacé tu primer pedido.</p>
            <a href="{{ route('tienda.index', ['slug' => $slug]) }}"
                class="inline-flex items-center gap-2 bg-red-700 hover:bg-red-800 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                Ver productos
            </a>
        </div>
    @else
        <div class="space-y-3">
            @foreach($pedidos as $pedido)
                @php $items = $itemsPorNro[$pedido->nro] ?? collect(); @endphp
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">

                    {{-- Header --}}
                    <div class="px-4 py-3 flex items-center justify-between gap-3 border-b border-gray-50">
                        <div>
                            <p class="text-sm font-bold text-gray-800">Pedido #{{ $pedido->nro }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $pedido->pedido_at
                                    ? $pedido->pedido_at->format('d/m/Y H:i')
                                    : ($pedido->fecha ? \Carbon\Carbon::parse($pedido->fecha)->format('d/m/Y') : '—') }}
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $pedido->estadoCss() }}">
                            {{ $pedido->estadoLabel() }}
                        </span>
                    </div>

                    {{-- Items --}}
                    @if($items->isNotEmpty())
                    <div class="px-4 py-3 space-y-2">
                        @foreach($items as $item)
                            <div class="flex justify-between items-center text-sm">
                                <div class="flex-1 pr-3">
                                    <span class="text-gray-700 font-medium">{{ $item->descrip }}</span>
                                    <span class="text-gray-400 text-xs ml-1.5">
                                        × {{ $item->cant }} {{ ($item->kilos ?? 0) > 0 ? 'kg' : 'u.' }}
                                    </span>
                                </div>
                                <span class="text-gray-700 font-semibold whitespace-nowrap">
                                    ${{ number_format($item->neto, 2, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Footer --}}
                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-3">
                        <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                @if($pedido->tipo_entrega === 'retiro')
                                    <span>🏪</span> Retiro en local
                                @else
                                    <span>🚚</span> Envío
                                @endif
                            </span>
                            @if($pedido->forma_pago)
                                <span class="flex items-center gap-1">
                                    <span>💳</span>
                                    {{ \App\Models\IaEmpresa::MEDIOS_PAGO[$pedido->forma_pago] ?? $pedido->forma_pago }}
                                </span>
                            @endif
                        </div>
                        <p class="font-bold text-red-700 whitespace-nowrap text-sm">
                            ${{ number_format($pedido->total, 2, ',', '.') }}
                        </p>
                    </div>

                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
