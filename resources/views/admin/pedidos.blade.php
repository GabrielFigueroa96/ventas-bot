@extends('admin.layout')
@section('title', 'Pedidos')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">Pedidos</h1>

{{-- Filtros --}}
<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <input type="text" name="search" value="{{ request('search') }}"
        placeholder="Buscar cliente..."
        class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">

    <select name="estado" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <option value="">Todos los estados</option>
        <option value="0" {{ request('estado') === '0' ? 'selected' : '' }}>Pendiente</option>
        <option value="1" {{ request('estado') === '1' ? 'selected' : '' }}>Finalizado</option>
    </select>

    <input type="date" name="fecha" value="{{ request('fecha') }}"
        class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">

    <button type="submit" class="bg-red-600 text-white rounded-lg px-5 py-2 text-sm hover:bg-red-700">Filtrar</button>
    <a href="{{ route('admin.pedidos') }}" class="text-sm text-gray-500 hover:underline self-center">Limpiar</a>
</form>

<div class="space-y-4">
    @forelse($pedidos as $nro => $items)
    @php
        $first  = $items->first();
        $key    = $first->venta . '-' . $first->pv;
        $fact   = $factventas->get($key);
    @endphp
    <div class="bg-white rounded-xl shadow overflow-hidden">

        {{-- Cabecera del pedido --}}
        <div class="flex items-start justify-between px-5 py-4 border-b">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <span class="font-bold text-gray-800 text-lg">#{{ $nro }}</span>
                    <span class="text-sm text-gray-600">{{ $first->nomcli }}</span>
                    <span class="text-xs text-gray-400">{{ $first->fecha }}</span>
                    @if($first->pv)
                        <span class="text-xs text-gray-400">PV: {{ $first->pv }}</span>
                    @endif
                </div>
                {{-- Artículos pedidos --}}
                <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Pedido</p>
                <ul class="text-sm text-gray-600 space-y-0.5">
                    @foreach($items as $item)
                        <li>• {{ $item->descrip }} — {{ $item->kilos }} kg/u</li>
                    @endforeach
                </ul>
            </div>
            <span class="text-xs px-3 py-1 rounded-full font-medium shrink-0
                {{ $first->estado == 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' }}">
                {{ $first->estado_texto }}
            </span>
        </div>

        {{-- Detalle de factura (solo si está finalizado y tiene renglones) --}}
        @if($fact && $fact->isNotEmpty())
        <div class="px-5 py-4 bg-gray-50">
            <p class="text-xs text-gray-400 uppercase font-semibold mb-2">
                Como salió
                @if($fact->first()->nro)
                    — COMPROBANTE NRO. <span class="text-gray-600 font-bold">{{ $fact->first()->nro }}</span>
                @endif
            </p>
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="text-left pb-1">Artículo</th>
                        <th class="text-right pb-1">Cant</th>
                        <th class="text-right pb-1">Kilos</th>
                        <th class="text-right pb-1">Precio</th>
                        <th class="text-right pb-1">Neto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($fact as $f)
                    <tr>
                        <td class="py-1 text-gray-700">{{ $f->descrip }}</td>
                        <td class="py-1 text-right text-gray-600">{{ $f->cant }}</td>
                        <td class="py-1 text-right text-gray-600">{{ number_format($f->kilos, 3) }}</td>
                        <td class="py-1 text-right text-gray-600">${{ number_format($f->precio, 2) }}</td>
                        <td class="py-1 text-right font-medium text-gray-800">${{ number_format($f->NETO, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-gray-300 text-sm font-semibold">
                    <tr>
                        <td colspan="2" class="pt-2 text-gray-500">Total</td>
                        <td class="pt-2 text-right text-gray-700">{{ number_format($fact->sum('kilos'), 3) }} kg</td>
                        <td></td>
                        <td class="pt-2 text-right text-red-700">${{ number_format($fact->sum('NETO'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif

    </div>
    @empty
    <div class="bg-white rounded-xl shadow px-6 py-10 text-center text-gray-400">
        No hay pedidos con esos filtros.
    </div>
    @endforelse
</div>
@endsection
