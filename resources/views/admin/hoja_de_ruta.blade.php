@extends('admin.layout')
@section('title', 'Hoja de ruta — ' . \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))

@section('content')

{{-- Print styles --}}
<style>
@media print {
    #sidebar, header.sticky, #overlay, #toast-wrap, .no-print { display: none !important; }
    body { background: white !important; }
    main { padding: 0 !important; max-width: none !important; }
    .shadow, .shadow-sm { box-shadow: none !important; }
    .rounded-xl { border-radius: 4px !important; }
    .bg-gray-100 { background: white !important; }
    .print-title { display: block !important; }
}
.print-title { display: none; }
</style>

{{-- Loading overlay al cambiar fecha --}}
<div id="fecha-loading" class="hidden fixed inset-0 z-50 bg-white/60 backdrop-blur-sm flex items-center justify-center no-print">
    <div class="bg-white rounded-xl shadow-lg px-6 py-5 flex items-center gap-3">
        <svg class="animate-spin w-5 h-5 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        <span class="text-sm font-medium text-gray-600">Cargando pedidos…</span>
    </div>
</div>

{{-- Título visible solo en impresión --}}
<div class="print-title mb-3">
    <h1 style="font-size:16px;font-weight:bold;">Hoja de ruta — {{ \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }}</h1>
</div>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5 no-print">
    <h1 class="text-2xl font-bold text-gray-800">Hoja de ruta</h1>

    <div class="flex items-center gap-2 flex-wrap">
        <form method="GET" class="flex items-center gap-2" id="form-fecha">
            <input type="date" name="fecha" value="{{ $fecha }}"
                onchange="cambiarFecha(this)"
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        </form>

        @if($pedidos->where('estado', \App\Models\Pedidosia::ESTADO_EN_CAMINO)->isNotEmpty())
        <button id="btn-marcar-reparto" onclick="marcarTodosReparto()"
            class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            Marcar todos como En reparto
        </button>
        @endif

        @if($pedidos->isNotEmpty())
        <button onclick="imprimirHoja()"
            class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Imprimir
        </button>
        @endif

        <a href="{{ route('admin.pedidos') }}"
            class="border border-gray-200 hover:bg-gray-50 text-gray-500 text-sm px-4 py-2 rounded-lg transition-colors">
            ← Pedidos
        </a>
    </div>
</div>

@php
    $totalPedidos    = $pedidos->count();
    $totalGeneral    = $pedidos->sum('total');
    $totalDespachado = $vmayo->flatten()->sum('NETO');
    $preparados      = $pedidos->where('estado', \App\Models\Pedidosia::ESTADO_EN_CAMINO)->count();
    $enReparto       = $pedidos->where('estado', \App\Models\Pedidosia::ESTADO_EN_REPARTO)->count();
    $porLocalidad    = $pedidos->groupBy('localidad');
@endphp

@if($pedidos->isEmpty())
    <div class="bg-white rounded-xl shadow p-10 text-center text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
        </svg>
        <p class="text-sm font-medium text-gray-500">No hay pedidos para esta fecha.</p>
        <p class="text-xs text-gray-400 mt-1">Solo se muestran envíos en estado <strong>Preparado</strong> o <strong>En reparto</strong>.</p>
    </div>
@else

{{-- Resumen --}}
<div class="bg-white rounded-xl shadow p-4 mb-5 flex flex-wrap gap-6">
    <div>
        <p class="text-xs text-gray-400 uppercase font-medium">Fecha de entrega</p>
        <p class="text-sm font-semibold text-gray-800">{{ \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM') }}</p>
    </div>
    <div>
        <p class="text-xs text-gray-400 uppercase font-medium">Total pedidos</p>
        <p class="text-sm font-semibold text-gray-800">{{ $totalPedidos }}</p>
    </div>
    @if($preparados > 0)
    <div>
        <p class="text-xs text-gray-400 uppercase font-medium">Preparados</p>
        <p class="text-sm font-semibold text-orange-600">{{ $preparados }}</p>
    </div>
    @endif
    @if($enReparto > 0)
    <div>
        <p class="text-xs text-gray-400 uppercase font-medium">En reparto</p>
        <p class="text-sm font-semibold text-indigo-600">{{ $enReparto }}</p>
    </div>
    @endif
    <div>
        <p class="text-xs text-gray-400 uppercase font-medium">Total pedido</p>
        <p class="text-sm font-semibold text-gray-800">${{ number_format($totalGeneral, 2, ',', '.') }}</p>
    </div>
    @if($totalDespachado > 0)
    <div>
        <p class="text-xs text-gray-400 uppercase font-medium">Total despachado</p>
        <p class="text-sm font-semibold text-green-700">${{ number_format($totalDespachado, 2, ',', '.') }}</p>
    </div>
    @endif
</div>

{{-- Pedidos --}}
<div id="contenido-ruta">
@foreach($porLocalidad as $localidad => $items)
<div class="mb-6">
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
        $esEnReparto   = (int) $pedido->estado === \App\Models\Pedidosia::ESTADO_EN_REPARTO;
        $vmayoItems    = $vmayo[$pedido->nro] ?? null;
        $tieneVmayo    = $vmayoItems?->isNotEmpty() ?? false;
    @endphp
    <div class="bg-white rounded-xl border overflow-hidden {{ $esEnReparto ? 'border-indigo-200' : 'border-gray-100' }} shadow-sm">

        {{-- Header --}}
        <div class="flex items-start gap-3 px-4 py-3 border-b {{ $esEnReparto ? 'border-indigo-50 bg-indigo-50/40' : 'border-gray-50' }}">
            <span class="text-xs font-bold text-gray-300 w-5 shrink-0 text-center pt-0.5">{{ $idx + 1 }}</span>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $pedido->nomcli }}</p>
                    @if($esEnReparto)
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-indigo-100 text-indigo-700 shrink-0">En reparto</span>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-orange-100 text-orange-700 shrink-0">Preparado</span>
                    @endif
                </div>
                @if($pedido->direccion)
                    <p class="text-xs text-gray-500 truncate mt-0.5">{{ $pedido->direccion }}</p>
                @endif
                <span class="text-xs text-gray-400">#{{ $pedido->nro }}</span>
            </div>
        </div>

        {{-- Detalle real (vmayo) si existe --}}
        @if($tieneVmayo)
        <div class="px-4 py-3 border-b border-dashed border-gray-200">
            <p class="text-xs font-semibold text-gray-500 mb-1.5 flex items-center gap-1">
                <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Despachado (real)
            </p>
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-medium pb-1">Producto</th>
                        <th class="text-right font-medium pb-1 w-24">Cant / kg</th>
                        <th class="text-right font-medium pb-1 w-20">Precio</th>
                        <th class="text-right font-medium pb-1 w-20">Neto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($vmayoItems as $vi)
                    <tr>
                        <td class="py-1 text-gray-700">{{ $vi->descrip }}</td>
                        <td class="py-1 text-right text-gray-600">
                            @if(($vi->cant ?? 0) > 0 && ($vi->kilos ?? 0) > 0)
                                {{ (int)$vi->cant }}u · {{ number_format($vi->kilos, 3, ',', '.') }}kg
                            @elseif(($vi->kilos ?? 0) > 0)
                                {{ number_format($vi->kilos, 3, ',', '.') }} kg
                            @else
                                {{ (int)($vi->cant ?? 0) }} u
                            @endif
                        </td>
                        <td class="py-1 text-right text-gray-500">${{ number_format($vi->precio ?? 0, 2, ',', '.') }}</td>
                        <td class="py-1 text-right font-semibold text-gray-800">${{ number_format($vi->NETO ?? 0, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Pedido original del cliente --}}
        <div class="px-4 py-3">
            @if($tieneVmayo)
            <p class="text-xs font-semibold text-gray-400 mb-1.5 flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                Pedido original (cliente)
            </p>
            @endif
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-medium pb-1">Producto</th>
                        <th class="text-right font-medium pb-1 w-24">Cant / kg</th>
                        <th class="text-right font-medium pb-1 w-20">Precio</th>
                        <th class="text-right font-medium pb-1 w-20">Neto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($pedido->items as $item)
                    <tr class="{{ $tieneVmayo ? 'text-gray-400' : '' }}">
                        <td class="py-1">{{ $item->descrip }}</td>
                        <td class="py-1 text-right">
                            @if(($item->kilos ?? 0) > 0 && ($item->cant ?? 0) > 0)
                                {{ (int)$item->cant }}u · {{ number_format($item->kilos, 3, ',', '.') }}kg
                            @elseif(($item->kilos ?? 0) > 0)
                                {{ number_format($item->kilos, 3, ',', '.') }} kg
                            @else
                                {{ (int)($item->cant ?? 0) }} u
                            @endif
                        </td>
                        <td class="py-1 text-right">${{ number_format($item->precio ?? 0, 2, ',', '.') }}</td>
                        <td class="py-1 text-right {{ $tieneVmayo ? '' : 'font-semibold text-gray-800' }}">${{ number_format($item->neto ?? 0, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 bg-gray-50 border-t border-gray-100 text-xs text-gray-500">
            <div class="flex items-center gap-3 flex-wrap">
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

<div class="bg-gray-800 text-white rounded-xl px-5 py-3 flex justify-between items-center mt-4">
    <span class="text-sm font-medium">Total ({{ $totalPedidos }} pedidos)</span>
    <span class="text-lg font-bold">${{ number_format($totalGeneral, 2, ',', '.') }}</span>
</div>
</div>{{-- #contenido-ruta --}}

@endif

@endsection

@section('scripts')
<script>
const CSRF  = document.querySelector('meta[name=csrf-token]').content;
const FECHA = @json($fecha);

// ── Cambio de fecha con loading ───────────────────────────────────────────────
function cambiarFecha(input) {
    document.getElementById('fecha-loading').classList.remove('hidden');
    document.getElementById('form-fecha').submit();
}

// ── Marcar todos como En reparto ──────────────────────────────────────────────
async function marcarTodosReparto() {
    const btn = document.getElementById('btn-marcar-reparto');
    if (!confirm('¿Marcar todos los pedidos preparados como "En reparto" y notificar a los clientes?')) return;

    btn.disabled  = true;
    btn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> Procesando…';

    try {
        const res  = await fetch('{{ route('admin.pedidos.hoja_de_ruta.marcar_reparto') }}', {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body   : JSON.stringify({ fecha: FECHA }),
        });
        const data = await res.json();
        if (res.ok) {
            showToast(`${data.avanzados} pedido${data.avanzados !== 1 ? 's' : ''} marcado${data.avanzados !== 1 ? 's' : ''} como En reparto.`, 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.error ?? 'Error al actualizar.', 'error');
            btn.disabled = false;
            btn.innerHTML = 'Marcar todos como En reparto';
        }
    } catch {
        showToast('Error de red.', 'error');
        btn.disabled = false;
        btn.innerHTML = 'Marcar todos como En reparto';
    }
}

// ── Imprimir ──────────────────────────────────────────────────────────────────
function imprimirHoja() {
    window.print();
}
</script>
@endsection
