@forelse($pedidos as $nro => $items)
@php
    $first     = $items->first();
    $key       = "{$first->venta}-{$first->pv}";
    $fact      = $factventas->get($key);
    $sia       = $pedidosia->get($nro) ?? null;
    $vmayoRows = ($vmayo ?? collect())->get($nro);

    // Comprobante: número de venta del sistema
    $nroComprobante = ($first->venta && $first->venta > 0)
        ? str_pad($first->pv, 4, '0', STR_PAD_LEFT) . '-' . str_pad($first->venta, 8, '0', STR_PAD_LEFT)
        : null;

    // Fecha de entrega
    $fechaEntrega = $first->fecha ? \Carbon\Carbon::parse($first->fecha) : null;
    $diasParaEntrega = $fechaEntrega ? now()->startOfDay()->diffInDays($fechaEntrega->startOfDay(), false) : null;
    $fechaEntregaTexto = null;
    if ($fechaEntrega) {
        $fechaEntregaTexto = ($diasParaEntrega !== null && $diasParaEntrega >= 0 && $diasParaEntrega <= 7)
            ? ucfirst($fechaEntrega->locale('es')->isoFormat('dddd D/MM/YYYY'))
            : $fechaEntrega->format('d/m/Y');
    }

    // Fecha de creación
    $pedidoAt = $first->pedido_at
        ? \Carbon\Carbon::parse($first->pedido_at)->format('d/m/Y H:i')
        : null;

    // Estado sia
    $siaEstado = $sia ? (int) $sia->estado : null;
    $siaLabel  = $sia ? $sia->estadoLabel() : null;
    $siaCss    = $sia ? $sia->estadoCss()   : null;
    $siaMax    = \App\Models\Pedidosia::ESTADO_ENTREGADO;

    $totalAcordado = $items->sum('neto');
    $obs = $sia?->obs ?: $first->obs;
@endphp

<div class="bg-white rounded-xl shadow overflow-hidden">

    {{-- Header --}}
    <div class="px-4 py-3 border-b">
        {{-- Fila 1: nro + nombre + estado --}}
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-bold text-gray-800">#{{ $nro }}</span>
                    @if($sia?->nomcli ?: $first->nomcli)
                        <span class="text-sm font-semibold text-gray-700 truncate">{{ $sia?->nomcli ?: $first->nomcli }}</span>
                    @endif
                </div>
                @if($pedidoAt)
                    <p class="text-xs text-gray-400 mt-0.5">{{ $pedidoAt }}</p>
                @endif
            </div>

            {{-- Estado + botones --}}
            @if($sia)
                <div class="flex items-center gap-1.5 shrink-0">
                    <span id="badge-sia-{{ $sia->id }}"
                          class="text-xs px-2 py-0.5 rounded-full font-medium {{ $siaCss }}">
                        {{ $siaLabel }}
                    </span>
                    @if($siaEstado < $sia->estadoMax() && $siaEstado !== \App\Models\Pedidosia::ESTADO_CANCELADO)
                        <button onclick="avanzarEstado({{ $sia->id }}, this)"
                            data-max="{{ $sia->estadoMax() }}"
                            data-estado="{{ $siaEstado }}"
                            class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full transition shrink-0">
                            ›
                        </button>
                    @endif
                    @if($siaEstado === \App\Models\Pedidosia::ESTADO_PENDIENTE)
                        <button onclick="cancelarPedido({{ $sia->id }}, this)"
                            class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-2 py-0.5 rounded-full transition shrink-0">
                            ✕
                        </button>
                    @endif
                </div>
            @else
                <span class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0
                    {{ $first->estado == \App\Models\Pedido::ESTADO_CANCELADO ? 'bg-red-100 text-red-600' : ($first->estado == 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                    {{ $first->estado_texto }}
                </span>
            @endif
        </div>

        {{-- Fila 2: badges entrega/pago/fecha --}}
        @php
            $entregaLabel = match($sia?->tipo_entrega) {
                'envio'  => ['🚚 Envío', 'bg-blue-50 text-blue-700'],
                'retiro' => ['🏪 Retiro', 'bg-gray-100 text-gray-600'],
                default  => null,
            };
            $pagoLabel = match($sia?->forma_pago) {
                'efectivo'         => ['💵 Efectivo', 'bg-green-50 text-green-700'],
                'transferencia'    => ['📲 Transf.', 'bg-purple-50 text-purple-700'],
                'cuenta_corriente' => ['📋 Cta. cte.', 'bg-orange-50 text-orange-700'],
                'otro'             => ['💳 Otro', 'bg-gray-100 text-gray-600'],
                default            => null,
            };
        @endphp
        <div class="flex flex-wrap gap-1.5 mt-2">
            @if($entregaLabel)
                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $entregaLabel[1] }}">{{ $entregaLabel[0] }}</span>
            @endif
            @if($pagoLabel)
                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $pagoLabel[1] }}">{{ $pagoLabel[0] }}</span>
            @endif
            @if($fechaEntregaTexto)
                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ ($diasParaEntrega !== null && $diasParaEntrega <= 2 && $diasParaEntrega >= 0) ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-600' }}">
                    📅 {{ $fechaEntregaTexto }}
                </span>
            @endif
            @if($sia?->direccion)
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">📍 {{ $sia->direccion }}</span>
            @endif
            @if($obs)
                <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-50 text-yellow-700 italic">📝 {{ $obs }}</span>
            @endif
        </div>

        {{-- Timeline de estados --}}
        @if($sia && $siaEstado !== \App\Models\Pedidosia::ESTADO_CANCELADO)
        @php
            $pasos = $sia->tipo_entrega === 'retiro'
                ? [0 => 'Pendiente', 1 => 'Confirmado', 2 => 'Listo', 3 => 'Retirado']
                : [0 => 'Pendiente', 1 => 'Confirmado', 2 => 'Preparado', 3 => 'En camino', 4 => 'Entregado'];
        @endphp
        <div class="flex items-center gap-0 mt-3">
            @foreach($pasos as $paso => $pasoLabel)
                @php
                    $done    = $siaEstado > $paso;
                    $current = $siaEstado === $paso;
                @endphp
                {{-- Círculo --}}
                <div class="flex flex-col items-center shrink-0">
                    <div class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold
                        {{ $done    ? 'bg-red-600 text-white' : '' }}
                        {{ $current ? 'bg-red-100 text-red-700 ring-2 ring-red-500' : '' }}
                        {{ !$done && !$current ? 'bg-gray-100 text-gray-400' : '' }}">
                        @if($done) ✓ @else {{ $paso + 1 }} @endif
                    </div>
                    <span class="text-[10px] mt-0.5 leading-tight text-center
                        {{ $current ? 'text-red-600 font-semibold' : ($done ? 'text-gray-500' : 'text-gray-300') }}">
                        {{ $pasoLabel }}
                    </span>
                </div>
                {{-- Línea conectora --}}
                @if(!$loop->last)
                    <div class="h-px flex-1 mb-3.5 {{ $done ? 'bg-red-500' : 'bg-gray-200' }}"></div>
                @endif
            @endforeach
        </div>
        @endif
    </div>

    {{-- Productos acordados --}}
    <div class="px-4 py-3">
        <p class="text-xs text-gray-400 uppercase font-semibold mb-2">Productos acordados</p>
        <div class="space-y-1.5">
            @foreach($items as $item)
            <div class="flex items-start justify-between gap-2">
                <span class="text-sm text-gray-700 leading-tight">{{ $item->descrip }}</span>
                <div class="text-right shrink-0">
                    <p class="text-xs text-gray-400">
                        @if($item->cant > 0 && $item->kilos > 0)
                            {{ (int) $item->cant }}u · {{ number_format($item->kilos, 2, ',', '.') }}kg
                        @elseif($item->kilos > 0)
                            {{ number_format($item->kilos, 3, ',', '.') }} kg
                        @else
                            {{ (int) $item->cant }} u
                        @endif
                        @if($item->precio)
                            · ${{ number_format($item->precio, 2, ',', '.') }}
                        @endif
                    </p>
                    <p class="text-sm font-semibold text-gray-800">${{ number_format($item->neto, 2, ',', '.') }}</p>
                </div>
            </div>
            @endforeach
        </div>
        <div class="flex justify-between items-center mt-3 pt-2 border-t border-gray-100">
            <span class="text-sm font-semibold text-gray-600">Total acordado</span>
            <span class="text-base font-bold text-red-700">${{ number_format($totalAcordado, 2, ',', '.') }}</span>
        </div>
    </div>

    {{-- Cómo salió (vmayo) --}}
    @if($vmayoRows?->isNotEmpty())
    <div class="px-4 py-3 bg-orange-50 border-t border-orange-100">
        <p class="text-xs text-orange-500 uppercase font-semibold mb-2">Cómo salió</p>
        <div class="space-y-1.5">
            @foreach($vmayoRows as $v)
            <div class="flex items-start justify-between gap-2">
                <span class="text-sm text-gray-700 leading-tight">{{ $v->descrip }}</span>
                <div class="text-right shrink-0">
                    <p class="text-xs text-gray-400">
                        @if($v->cant > 0 && $v->kilos > 0)
                            {{ (int) $v->cant }}u · {{ number_format($v->kilos, 3, ',', '.') }}kg
                        @elseif($v->kilos > 0)
                            {{ number_format($v->kilos, 3, ',', '.') }} kg
                        @else
                            {{ (int) $v->cant }} u
                        @endif
                        @if($v->precio)
                            · ${{ number_format($v->precio, 2, ',', '.') }}
                        @endif
                    </p>
                    <p class="text-sm font-semibold text-gray-800">${{ number_format($v->NETO, 2, ',', '.') }}</p>
                </div>
            </div>
            @endforeach
        </div>
        <div class="flex justify-between items-center mt-3 pt-2 border-t border-orange-100">
            <span class="text-sm font-semibold text-gray-600">Total</span>
            <span class="text-base font-bold text-orange-700">${{ number_format($vmayoRows->sum('NETO'), 2, ',', '.') }}</span>
        </div>
    </div>
    @endif

    {{-- Real facturado --}}
    @if($fact?->isNotEmpty())
    <div class="px-4 py-3 bg-gray-50 border-t">
        <p class="text-xs text-gray-400 uppercase font-semibold mb-2">
            Real facturado
            @if($nroComprobante)
                — <span class="text-gray-700 font-bold">{{ $nroComprobante }}</span>
            @endif
        </p>
        <div class="space-y-1.5">
            @foreach($fact as $f)
            <div class="flex items-start justify-between gap-2">
                <span class="text-sm text-gray-700 leading-tight">{{ $f->descrip }}</span>
                <div class="text-right shrink-0">
                    <p class="text-xs text-gray-400">
                        {{ number_format($f->kilos, 3, ',', '.') }} kg
                    </p>
                    <p class="text-sm font-semibold text-gray-800">${{ number_format($f->neto ?? $f->NETO, 2, ',', '.') }}</p>
                </div>
            </div>
            @endforeach
        </div>
        <div class="flex justify-between items-center mt-3 pt-2 border-t border-gray-200">
            <span class="text-sm font-semibold text-gray-600">Total real</span>
            <span class="text-base font-bold text-gray-800">${{ number_format($fact->sum('neto') ?: $fact->sum('NETO'), 2, ',', '.') }}</span>
        </div>
    </div>
    @endif

</div>
@empty
<div class="bg-white rounded-xl shadow px-5 py-6 text-center text-gray-400 text-sm">Sin pedidos.</div>
@endforelse
