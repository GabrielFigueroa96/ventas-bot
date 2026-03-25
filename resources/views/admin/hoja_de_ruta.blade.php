@extends('admin.layout')
@section('title', 'Hoja de ruta — ' . \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-bold text-gray-800">Hoja de ruta</h1>

    <div class="flex items-center gap-2 flex-wrap">
        {{-- Selector de fecha --}}
        <form method="GET" class="flex items-center gap-2" data-no-loading>
            <input type="date" name="fecha" value="{{ $fecha }}"
                onchange="this.form.submit()"
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        </form>

        @if($pedidos->isNotEmpty())
        {{-- Marcar todos como En reparto --}}
        <button id="btn-marcar-reparto" onclick="marcarTodosReparto()"
            class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            Marcar todos como En reparto
        </button>

        {{-- Imprimir --}}
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

@if($pedidos->isEmpty())
    <div class="bg-white rounded-xl shadow p-10 text-center text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
        </svg>
        <p class="text-sm font-medium text-gray-500">No hay pedidos preparados para esta fecha.</p>
        <p class="text-xs text-gray-400 mt-1">Solo se muestran los pedidos de envío en estado <strong>Preparado</strong>.</p>
    </div>
@else

@php
    $porLocalidad = $pedidos->groupBy('localidad');
    $totalPedidos = $pedidos->count();
    $totalGeneral = $pedidos->sum('total');
@endphp

{{-- Resumen --}}
<div class="bg-white rounded-xl shadow p-4 mb-5 flex flex-wrap gap-6">
    <div>
        <p class="text-xs text-gray-400 uppercase font-medium">Fecha de entrega</p>
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
        <p class="text-xs text-gray-400 uppercase font-medium">Total</p>
        <p class="text-sm font-semibold text-gray-800">${{ number_format($totalGeneral, 2, ',', '.') }}</p>
    </div>
</div>

{{-- Pedidos agrupados por localidad --}}
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
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-2 px-4 py-3 border-b border-gray-50">
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-gray-300 w-5 text-center">{{ $idx + 1 }}</span>
                <div>
                    <p class="text-sm font-semibold text-gray-800">{{ $pedido->nomcli }}</p>
                    @if($pedido->direccion)
                        <p class="text-xs text-gray-500">{{ $pedido->direccion }}</p>
                    @endif
                </div>
            </div>
            <span class="text-xs font-semibold text-gray-400">#{{ $pedido->nro }}</span>
        </div>

        <div class="px-4 py-3">
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-medium pb-1">Producto</th>
                        <th class="text-right font-medium pb-1 w-24">Cant / kg</th>
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
                        <td class="py-1 text-right font-medium text-gray-800">${{ number_format($item->neto ?? 0, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

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

<div class="bg-gray-800 text-white rounded-xl px-5 py-3 flex justify-between items-center mt-4">
    <span class="text-sm font-medium">Total ({{ $totalPedidos }} pedidos)</span>
    <span class="text-lg font-bold">${{ number_format($totalGeneral, 2, ',', '.') }}</span>
</div>
</div>{{-- #contenido-ruta --}}

@endif

@endsection

@section('scripts')
<script>
const CSRF   = document.querySelector('meta[name=csrf-token]').content;
const FECHA  = @json($fecha);

// ── Marcar todos como En reparto ──────────────────────────────────────────────
async function marcarTodosReparto() {
    const btn = document.getElementById('btn-marcar-reparto');
    if (!confirm('¿Marcar todos los pedidos como "En reparto" y notificar a los clientes?')) return;

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
            btn.disabled  = false;
            btn.innerHTML = 'Marcar todos como En reparto';
        }
    } catch {
        showToast('Error de red.', 'error');
        btn.disabled  = false;
        btn.innerHTML = 'Marcar todos como En reparto';
    }
}

// ── Imprimir: abre ventana limpia solo con el contenido ───────────────────────
function imprimirHoja() {
    const contenido = document.getElementById('contenido-ruta');
    if (!contenido) return;

    const fecha = @json(\Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY'));
    const total = @json(number_format($totalGeneral, 2, ',', '.'));
    const cant  = @json($totalPedidos);

    const win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(`<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Hoja de ruta — ${fecha}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 12px; color: #111; padding: 20px; }
  h1 { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
  .resumen { font-size: 11px; color: #555; margin-bottom: 16px; }
  .localidad { background: #b91c1c; color: #fff; font-size: 11px; font-weight: bold;
               padding: 3px 10px; border-radius: 20px; display: inline-block; margin-bottom: 6px; }
  .grupo { margin-bottom: 16px; page-break-inside: avoid; }
  .pedido { border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px; overflow: hidden; page-break-inside: avoid; }
  .pedido-header { padding: 6px 10px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: flex-start; }
  .pedido-nombre { font-weight: bold; font-size: 12px; }
  .pedido-dir { font-size: 10px; color: #666; }
  .pedido-nro { font-size: 10px; color: #aaa; }
  table { width: 100%; border-collapse: collapse; }
  table th, table td { padding: 3px 10px; text-align: left; font-size: 11px; }
  table th { color: #888; font-weight: normal; border-bottom: 1px solid #f3f4f6; }
  td:nth-child(2), th:nth-child(2) { text-align: right; }
  td:nth-child(3), th:nth-child(3) { text-align: right; }
  .pedido-footer { padding: 4px 10px; background: #f9fafb; border-top: 1px solid #f3f4f6;
                   display: flex; justify-content: space-between; font-size: 10px; color: #555; }
  .pedido-footer .total { font-weight: bold; color: #111; font-size: 12px; }
  .total-general { background: #1f2937; color: #fff; padding: 8px 14px; border-radius: 8px;
                   display: flex; justify-content: space-between; margin-top: 12px; font-weight: bold; }
</style>
</head>
<body>
<h1>Hoja de ruta — ${fecha}</h1>
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
