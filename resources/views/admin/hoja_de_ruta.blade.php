@extends('admin.layout')
@section('title', 'Hoja de ruta — ' . \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-5 no-print">
    <h1 class="text-2xl font-bold text-gray-800">Hoja de ruta</h1>

    <div class="flex items-center gap-2">
        {{-- Selector de fecha --}}
        <form method="GET" class="flex items-center gap-2">
            <input type="date" name="fecha" value="{{ $fecha }}"
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
            <button type="submit"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                Ver
            </button>
        </form>

        <button onclick="window.print()"
            class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Imprimir
        </button>

        <a href="{{ route('pedidos') }}"
            class="border border-gray-200 hover:bg-gray-50 text-gray-500 text-sm px-4 py-2 rounded-lg transition-colors">
            ← Pedidos
        </a>
    </div>
</div>

{{-- Encabezado imprimible --}}
<div class="print-only mb-4">
    <h2 class="text-xl font-bold">Hoja de ruta — {{ \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }}</h2>
</div>

@if($pedidos->isEmpty())
    <div class="bg-white rounded-xl shadow p-10 text-center text-gray-400 no-print">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
        </svg>
        <p class="text-sm">No hay pedidos de envío para esta fecha.</p>
    </div>
    <div class="print-only text-center text-gray-400 text-sm mt-8">No hay pedidos de envío para esta fecha.</div>
@else

@php
    $porLocalidad = $pedidos->groupBy('localidad');
    $totalPedidos = $pedidos->count();
    $totalGeneral = $pedidos->sum('total');
@endphp

{{-- Resumen --}}
<div class="bg-white rounded-xl shadow p-4 mb-5 flex flex-wrap gap-6 no-print">
    <div>
        <p class="text-xs text-gray-400 uppercase font-medium">Fecha</p>
        <p class="text-sm font-semibold text-gray-800">{{ \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM') }}</p>
    </div>
    <div>
        <p class="text-xs text-gray-400 uppercase font-medium">Pedidos</p>
        <p class="text-sm font-semibold text-gray-800">{{ $totalPedidos }}</p>
    </div>
    <div>
        <p class="text-xs text-gray-400 uppercase font-medium">Localidades</p>
        <p class="text-sm font-semibold text-gray-800">{{ $porLocalidad->count() }}</p>
    </div>
    <div>
        <p class="text-xs text-gray-400 uppercase font-medium">Total estimado</p>
        <p class="text-sm font-semibold text-gray-800">${{ number_format($totalGeneral, 2, ',', '.') }}</p>
    </div>
</div>

{{-- Resumen imprimible --}}
<div class="print-only mb-4 text-sm text-gray-600">
    {{ $totalPedidos }} pedidos · {{ $porLocalidad->count() }} localidades · Total: ${{ number_format($totalGeneral, 2, ',', '.') }}
</div>

{{-- Pedidos agrupados por localidad --}}
@foreach($porLocalidad as $localidad => $items)
<div class="mb-6 page-break-inside-avoid">
    {{-- Encabezado localidad --}}
    <div class="flex items-center gap-2 mb-2">
        <div class="flex items-center gap-2 bg-red-700 text-white text-xs font-bold px-3 py-1 rounded-full">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            {{ $localidad ?: 'Sin localidad' }}
        </div>
        <span class="text-xs text-gray-400">{{ $items->count() }} {{ $items->count() === 1 ? 'pedido' : 'pedidos' }}</span>
    </div>

    <div class="space-y-3">
    @foreach($items as $idx => $pedido)
    @php
        $estadoInfo = \App\Models\Pedidosia::ESTADOS[$pedido->estado] ?? ['label' => '?', 'css' => ''];
        $esPeso = fn($item) => ($item->kilos ?? 0) > 0;
    @endphp
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        {{-- Header pedido --}}
        <div class="flex flex-wrap items-start justify-between gap-2 px-4 py-3 border-b border-gray-50">
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-gray-400 w-5 text-center">{{ $idx + 1 }}</span>
                <div>
                    <p class="text-sm font-semibold text-gray-800">{{ $pedido->nomcli }}</p>
                    @if($pedido->direccion)
                        <p class="text-xs text-gray-500">{{ $pedido->direccion }}</p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $estadoInfo['css'] }}">{{ $pedido->estadoLabel() }}</span>
                <span class="text-xs font-semibold text-gray-700">#{{ $pedido->nro }}</span>
            </div>
        </div>

        {{-- Items --}}
        <div class="px-4 py-3">
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-medium pb-1">Producto</th>
                        <th class="text-right font-medium pb-1 w-20">Cant / kg</th>
                        <th class="text-right font-medium pb-1 w-20">Precio</th>
                        <th class="text-right font-medium pb-1 w-20">Neto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($pedido->items as $item)
                    <tr>
                        <td class="py-1 text-gray-700">{{ $item->descrip }}</td>
                        <td class="py-1 text-right text-gray-600">
                            @if(($item->kilos ?? 0) > 0 && ($item->cant ?? 0) > 0)
                                {{ (int)$item->cant }}u · {{ number_format($item->kilos, 3, ',', '.') }}kg
                            @elseif(($item->kilos ?? 0) > 0)
                                {{ number_format($item->kilos, 3, ',', '.') }} kg
                            @else
                                {{ (int)($item->cant ?? 0) }} u
                            @endif
                        </td>
                        <td class="py-1 text-right text-gray-600">${{ number_format($item->precio ?? 0, 2, ',', '.') }}</td>
                        <td class="py-1 text-right font-medium text-gray-800">${{ number_format($item->neto ?? 0, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Footer pedido --}}
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 bg-gray-50 border-t border-gray-100 text-xs text-gray-500">
            <div class="flex items-center gap-3">
                @if($pedido->forma_pago)
                    <span>💳 {{ \App\Models\IaEmpresa::MEDIOS_PAGO[$pedido->forma_pago] ?? $pedido->forma_pago }}</span>
                @endif
                @if($pedido->obs)
                    <span class="italic">📝 {{ $pedido->obs }}</span>
                @endif
            </div>
            <span class="font-semibold text-gray-800 text-sm">${{ number_format($pedido->total, 2, ',', '.') }}</span>
        </div>
    </div>
    @endforeach
    </div>
</div>
@endforeach

{{-- Total general --}}
<div class="bg-gray-800 text-white rounded-xl px-5 py-3 flex justify-between items-center mt-4">
    <span class="text-sm font-medium">Total general ({{ $totalPedidos }} pedidos)</span>
    <span class="text-lg font-bold">${{ number_format($totalGeneral, 2, ',', '.') }}</span>
</div>

@endif

@endsection

@push('head')
<style>
@media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    body { background: white !important; }
    .page-break-inside-avoid { page-break-inside: avoid; }
    /* Ocultar sidebar y topbar del layout */
    nav, aside, header { display: none !important; }
    main { padding: 0 !important; margin: 0 !important; }
}
@media screen {
    .print-only { display: none; }
}
</style>
@endpush
