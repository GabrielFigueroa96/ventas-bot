@extends('admin.layout')
@section('title', $cliente->name ?? $cliente->phone)

@section('content')
<a href="{{ route('admin.clientes') }}" class="text-sm text-red-600 hover:underline mb-4 inline-block">← Volver</a>

<div class="flex items-center gap-4 mb-6">
    <div class="bg-red-100 text-red-700 rounded-full w-14 h-14 flex items-center justify-center text-2xl font-bold">
        {{ strtoupper(substr($cliente->name ?? '?', 0, 1)) }}
    </div>
    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $cliente->name ?? 'Sin nombre' }}</h1>
        <p class="text-gray-500 text-sm">{{ $cliente->phone }} · Cliente desde {{ $cliente->created_at->format('d/m/Y') }}</p>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6">

    {{-- Conversación --}}
    <div class="bg-white rounded-xl shadow flex flex-col">
        <div class="px-5 py-4 border-b font-semibold text-gray-700">Conversación</div>
        <div class="p-4 space-y-3 overflow-y-auto max-h-[500px]">
            @forelse($mensajes as $msg)
            <div class="flex {{ $msg->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-xs px-4 py-2 rounded-2xl text-sm
                    {{ $msg->direction === 'outgoing'
                        ? 'bg-red-600 text-white rounded-br-none'
                        : 'bg-gray-100 text-gray-800 rounded-bl-none' }}">
                    <p>{{ $msg->message }}</p>
                    <p class="text-xs mt-1 opacity-60">{{ $msg->created_at->format('d/m H:i') }}</p>
                </div>
            </div>
            @empty
            <p class="text-gray-400 text-sm text-center py-6">Sin mensajes.</p>
            @endforelse
        </div>
    </div>

    {{-- Pedidos --}}
    <div class="space-y-4">
        @forelse($pedidos as $nro => $items)
        @php
            $first = $items->first();
            $key   = $first->venta . '-' . $first->pv;
            $fact  = $factventas->get($key);
        @endphp
        <div class="bg-white rounded-xl shadow overflow-hidden">

            {{-- Cabecera --}}
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

            {{-- Artículos pedidos --}}
            <div class="px-5 py-3">
                <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Pedido</p>
                <ul class="text-sm text-gray-600 space-y-0.5">
                    @foreach($items as $item)
                        <li>• {{ $item->descrip }} — {{ $item->kilos }} kg/u</li>
                    @endforeach
                </ul>
            </div>

            {{-- Como salió (factventas) --}}
            @if($fact && $fact->isNotEmpty())
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
@endsection
