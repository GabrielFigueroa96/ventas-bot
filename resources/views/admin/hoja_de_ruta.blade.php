@extends('admin.layout')
@section('title', 'Hoja de ruta — ' . \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-bold text-gray-800">Hoja de ruta</h1>

    <div class="flex items-center gap-2 flex-wrap">
        <form method="GET" class="flex items-center gap-2" data-no-loading>
            <input type="date" name="fecha" value="{{ $fecha }}"
                onchange="this.form.submit()"
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
    $totalPedidos = $pedidos->count();
    $totalGeneral = $pedidos->sum('total');
    $preparados   = $pedidos->where('estado', \App\Models\Pedidosia::ESTADO_EN_CAMINO)->count();
    $enReparto    = $pedidos->where('estado', \App\Models\Pedidosia::ESTADO_EN_REPARTO)->count();
    $porLocalidad = $pedidos->groupBy('localidad');
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
        <p class="text-xs text-gray-400 uppercase font-medium">Total</p>
        <p class="text-sm font-semibold text-gray-800">${{ number_format($totalGeneral, 2, ',', '.') }}</p>
    </div>
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
                <p class="text-sm font-semibold text-gray-800 truncate">{{ $pedido->nomcli }}</p>
                @if($pedido->direccion)
                    <p class="text-xs text-gray-500 truncate">{{ $pedido->direccion }}</p>
                @endif
                <div class="flex items-center gap-2 mt-1">
                    @if($esEnReparto)
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-indigo-100 text-indigo-700">En reparto</span>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-orange-100 text-orange-700">Preparado</span>
                    @endif
                    <span class="text-xs text-gray-400">#{{ $pedido->nro }}</span>
                </div>
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

// ── Imprimir: abre ventana limpia ─────────────────────────────────────────────
function imprimirHoja() {
    const contenido = document.getElementById('contenido-ruta');
    if (!contenido) return;

    @php
        $js_fechaLabel = \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
        $js_total      = number_format($totalGeneral, 2, ',', '.');
    @endphp
    const fechaLabel = @json($js_fechaLabel);
    const total      = @json($js_total);
    const cant       = @json($totalPedidos);

    const win = window.open('', '_blank', 'width=950,height=750');
    win.document.write(`<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Hoja de ruta — ${fechaLabel}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 12px; color: #111; padding: 20px; }
  h1 { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
  .resumen { font-size: 11px; color: #555; margin-bottom: 16px; }
  .grupo { margin-bottom: 16px; }
  .localidad-badge { background: #b91c1c; color: #fff; font-size: 11px; font-weight: bold;
    padding: 2px 10px; border-radius: 20px; display: inline-block; margin-bottom: 6px; }
  .pedido { border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 8px; overflow: hidden; page-break-inside: avoid; }
  .pedido.en-reparto { border-color: #c7d2fe; }
  .pedido-header { padding: 5px 10px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: flex-start; background: #fff; }
  .pedido.en-reparto .pedido-header { background: #eef2ff; }
  .pedido-nombre { font-weight: bold; font-size: 12px; }
  .pedido-dir { font-size: 10px; color: #777; }
  .estado-badge { font-size: 10px; font-weight: bold; padding: 1px 7px; border-radius: 20px; white-space: nowrap; }
  .estado-preparado { background: #ffedd5; color: #c2410c; }
  .estado-reparto { background: #e0e7ff; color: #4338ca; }
  .seccion-label { font-size: 10px; font-weight: bold; color: #555; padding: 4px 10px 2px;
    display: flex; align-items: center; gap: 4px; }
  .seccion-label.real { color: #16a34a; border-top: 1px dashed #e5e7eb; }
  .seccion-label.original { color: #9ca3af; border-top: 1px dashed #e5e7eb; }
  table { width: 100%; border-collapse: collapse; }
  table th, table td { padding: 2px 10px; font-size: 11px; }
  table th { text-align: left; color: #9ca3af; font-weight: normal; border-bottom: 1px solid #f3f4f6; }
  td:nth-child(2), th:nth-child(2),
  td:nth-child(3), th:nth-child(3),
  td:nth-child(4), th:nth-child(4) { text-align: right; }
  tr.dim td { color: #aaa; }
  .pedido-footer { padding: 3px 10px; background: #f9fafb; border-top: 1px solid #f3f4f6;
    display: flex; justify-content: space-between; font-size: 10px; color: #666; }
  .pedido-footer strong { color: #111; font-size: 12px; }
  .total-general { background: #1f2937; color: #fff; padding: 7px 14px; border-radius: 6px;
    display: flex; justify-content: space-between; margin-top: 12px; font-weight: bold; font-size: 13px; }
</style>
</head>
<body>
<h1>Hoja de ruta — ${fechaLabel}</h1>
<p class="resumen">${cant} pedidos · Total: $${total}</p>
${contenido.innerHTML}
</body>
</html>`);
    win.document.close();
    win.focus();
    setTimeout(() => { win.print(); win.close(); }, 400);
}
</script>
@endsection
