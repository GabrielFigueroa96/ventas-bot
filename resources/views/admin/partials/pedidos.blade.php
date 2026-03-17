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
