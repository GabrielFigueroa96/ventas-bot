@forelse($pedidos as $nro => $items)
@php
    $first = $items->first();
    $key   = "{$first->venta}-{$first->pv}";
    $fact  = $factventas->get($key);
    $sia   = $pedidosia->get($nro) ?? null;

    // Fecha de entrega
    $fechaEntrega = $first->fecha
        ? \Carbon\Carbon::parse($first->fecha)
        : null;
    $diasParaEntrega = $fechaEntrega ? now()->startOfDay()->diffInDays($fechaEntrega->startOfDay(), false) : null;
    if ($fechaEntrega) {
        $fechaEntregaTexto = $fechaEntrega->format('d/m/Y');
        if ($diasParaEntrega !== null && $diasParaEntrega >= 0 && $diasParaEntrega <= 7) {
            $fechaEntregaTexto = $fechaEntrega->locale('es')->isoFormat('dddd D/MM/YYYY');
        }
    }

    // Fecha de creación con hora
    $pedidoAt = $first->pedido_at
        ? \Carbon\Carbon::parse($first->pedido_at)->format('d/m/Y H:i')
        : null;

    // Etiquetas tipo_entrega y forma_pago
    $entregaLabel = match($sia?->tipo_entrega) {
        'envio'  => ['🚚 Envío', 'bg-blue-100 text-blue-700'],
        'retiro' => ['🏪 Retiro', 'bg-gray-100 text-gray-600'],
        default  => null,
    };
    $pagoLabel = match($sia?->forma_pago) {
        'efectivo'         => ['💵 Efectivo', 'bg-green-100 text-green-700'],
        'transferencia'    => ['📲 Transferencia', 'bg-purple-100 text-purple-700'],
        'cuenta_corriente' => ['📋 Cta. cte.', 'bg-orange-100 text-orange-700'],
        'otro'             => ['💳 Otro', 'bg-gray-100 text-gray-600'],
        default            => null,
    };
@endphp
<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="flex items-center justify-between px-5 py-3 border-b">
        <div class="flex items-center gap-3 flex-wrap">
            <span class="font-bold text-gray-800">#{{ $nro }}</span>
            @if($pedidoAt)
                <span class="text-xs text-gray-400">Pedido el {{ $pedidoAt }}</span>
            @endif
            @if($entregaLabel)
                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $entregaLabel[1] }}">{{ $entregaLabel[0] }}</span>
            @endif
            @if($pagoLabel)
                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $pagoLabel[1] }}">{{ $pagoLabel[0] }}</span>
            @endif
        </div>
        <span class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0
            {{ $first->estado == 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' }}">
            {{ $first->estado_texto }}
        </span>
    </div>
    <div class="px-5 py-3 space-y-2">
        {{-- Fecha + Obs --}}
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
            @if($fechaEntrega)
                <span class="flex items-center gap-1 {{ ($diasParaEntrega !== null && $diasParaEntrega <= 2 && $diasParaEntrega >= 0) ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                    📅 Entrega: {{ $fechaEntregaTexto }}
                </span>
            @endif
            @php $obs = $sia?->obs ?: $first->obs; @endphp
            @if($obs)
                <span class="text-gray-500 italic">📝 {{ $obs }}</span>
            @endif
        </div>
        {{-- Items --}}
        <div>
            <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Productos</p>
            <ul class="text-sm text-gray-600 space-y-0.5">
                @foreach($items as $item)
                    <li class="flex justify-between">
                        <span>• {{ $item->descrip }}</span>
                        <span class="text-gray-400">
                            @if($item->kilos > 0)
                                {{ number_format($item->kilos, 3, ',', '.') }} kg
                            @else
                                {{ $item->cant }} u
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Comprobante --}}
    @if($fact?->isNotEmpty())
    <div class="px-5 py-3 bg-gray-50 border-t">
        <p class="text-xs text-gray-400 uppercase font-semibold mb-2">
            Como salió
            @if($fact->first()->fact ?? null)
                — Comprobante <span class="text-gray-700 font-bold">{{ $fact->first()->fact }}</span>
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
                    <td class="py-1 text-right text-gray-500">{{ number_format($f->kilos, 3, ',', '.') }}</td>
                    <td class="py-1 text-right font-medium text-gray-800">$ {{ number_format($f->neto ?? $f->NETO, 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-gray-300 font-semibold text-sm">
                <tr>
                    <td colspan="2" class="pt-1 text-gray-500">Total</td>
                    <td class="pt-1 text-right text-gray-600">{{ number_format($fact->sum('kilos'), 3, ',', '.') }} kg</td>
                    <td class="pt-1 text-right text-red-700">$ {{ number_format($fact->sum('neto') ?: $fact->sum('NETO'), 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>
@empty
<div class="bg-white rounded-xl shadow px-5 py-6 text-center text-gray-400 text-sm">Sin pedidos.</div>
@endforelse
