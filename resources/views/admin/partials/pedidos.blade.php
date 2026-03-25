@forelse($pedidos as $nro => $items)
@php
    $first     = $items->first();
    $key       = "{$first->venta}-{$first->pv}";
    $fact      = $factventas->get($key);
    $sia       = $pedidosia->get($nro) ?? null;
    $vmayoRows = ($vmayo ?? collect())->get($nro);

    $nroComprobante = ($first->venta && $first->venta > 0)
        ? str_pad($first->pv, 4, '0', STR_PAD_LEFT) . '-' . str_pad($first->venta, 8, '0', STR_PAD_LEFT)
        : null;

    $fechaEntrega      = $first->fecha ? \Carbon\Carbon::parse($first->fecha) : null;
    $diasParaEntrega   = $fechaEntrega ? now()->startOfDay()->diffInDays($fechaEntrega->startOfDay(), false) : null;
    $fechaEntregaTexto = null;
    if ($fechaEntrega) {
        $fechaEntregaTexto = ($diasParaEntrega !== null && $diasParaEntrega >= 0 && $diasParaEntrega <= 7)
            ? ucfirst($fechaEntrega->locale('es')->isoFormat('dddd D/MM/YYYY'))
            : $fechaEntrega->format('d/m/Y');
    }

    $pedidoAt  = $first->pedido_at
        ? \Carbon\Carbon::parse($first->pedido_at)->format('d/m/Y H:i')
        : null;

    $siaEstado = $sia ? (int) $sia->estado : null;
    $siaLabel  = $sia ? $sia->estadoLabel() : null;
    $siaCss    = $sia ? $sia->estadoCss()   : null;
    $siaMax    = \App\Models\Pedidosia::ESTADO_ENTREGADO;

    $totalAcordado = $items->sum('neto');
    $obs           = $sia?->obs ?: $first->obs;

    // Próximo estado label para el botón
    $estadosSia = \App\Models\Pedidosia::ESTADOS;
    $nextLabel  = isset($estadosSia[$siaEstado + 1]) ? $estadosSia[$siaEstado + 1]['label'] : null;
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

    {{-- Header --}}
    <div class="px-4 pt-4 pb-3">

        {{-- Fila superior: nombre + badge/botones (col en mobile, row en sm+) --}}
        <div class="flex flex-col gap-1.5 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
            <div class="min-w-0">
                <div class="flex items-baseline gap-2 flex-wrap">
                    <span class="text-xs font-bold text-gray-400 tracking-wide">#{{ $nro }}</span>
                    <span class="text-base font-bold text-gray-800 truncate">
                        {{ $sia?->nomcli ?: $first->nomcli }}
                    </span>
                </div>
                @if($pedidoAt)
                    <p class="text-xs text-gray-400 mt-0.5">Pedido el {{ $pedidoAt }}</p>
                @endif
            </div>

            {{-- Badge + botones de acción --}}
            <div class="flex items-center gap-1.5 flex-wrap sm:shrink-0">
                @if($sia)
                    <span id="badge-sia-{{ $sia->id }}"
                          class="inline-flex items-center text-xs px-2.5 py-1 rounded-full font-semibold {{ $siaCss }}">
                        {{ $siaLabel }}
                    </span>
                    @if($siaEstado < $sia->estadoMax() && $siaEstado !== \App\Models\Pedidosia::ESTADO_CANCELADO)
                        <button onclick="avanzarEstado({{ $sia->id }}, this)"
                            data-max="{{ $sia->estadoMax() }}"
                            data-estado="{{ $siaEstado }}"
                            data-label="{{ $nextLabel ?? '›' }}"
                            class="text-xs bg-gray-800 hover:bg-gray-700 text-white px-2.5 py-1 rounded-full transition-colors font-medium shrink-0">
                            {{ $nextLabel ?? '›' }}
                        </button>
                    @endif
                    @if($siaEstado === \App\Models\Pedidosia::ESTADO_PENDIENTE)
                        <button id="cancel-sia-{{ $sia->id }}" onclick="cancelarPedido({{ $sia->id }}, this)"
                            class="text-xs bg-red-50 hover:bg-red-100 text-red-500 px-2 py-1 rounded-full transition-colors shrink-0">
                            Cancelar
                        </button>
                    @endif
                @else
                    <span class="inline-flex items-center text-xs px-2.5 py-1 rounded-full font-semibold
                        {{ $first->estado == \App\Models\Pedido::ESTADO_CANCELADO ? 'bg-red-100 text-red-600' : ($first->estado == 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                        {{ $first->estado_texto }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Badges: entrega, pago, fecha, dirección, obs --}}
        @php
            $entregaBadge = match($sia?->tipo_entrega) {
                'envio'  => ['🚚 Envío',  'bg-blue-50 text-blue-700'],
                'retiro' => ['🏪 Retiro', 'bg-gray-100 text-gray-600'],
                default  => null,
            };
            $pagoBadge = match($sia?->forma_pago) {
                'efectivo'         => ['💵 Efectivo',    'bg-green-50 text-green-700'],
                'transferencia'    => ['📲 Transferencia','bg-purple-50 text-purple-700'],
                'cuenta_corriente' => ['📋 Cta. cte.',   'bg-orange-50 text-orange-700'],
                'otro'             => ['💳 Otro',         'bg-gray-100 text-gray-600'],
                default            => null,
            };
            $fechaUrgente = $diasParaEntrega !== null && $diasParaEntrega <= 1 && $diasParaEntrega >= 0;
            $fechaProxima = $diasParaEntrega !== null && $diasParaEntrega <= 3 && $diasParaEntrega >= 0;
        @endphp
        <div class="flex flex-wrap gap-1.5 mt-2.5">
            @if($entregaBadge)
                <span class="text-xs px-2.5 py-0.5 rounded-full font-medium {{ $entregaBadge[1] }}">{{ $entregaBadge[0] }}</span>
            @endif
            @if($fechaEntregaTexto)
                <span class="text-xs px-2.5 py-0.5 rounded-full font-medium
                    {{ $fechaUrgente ? 'bg-red-100 text-red-700 font-semibold' : ($fechaProxima ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                    📅 {{ $fechaEntregaTexto }}
                </span>
            @endif
            @if($pagoBadge)
                <span class="text-xs px-2.5 py-0.5 rounded-full font-medium {{ $pagoBadge[1] }}">{{ $pagoBadge[0] }}</span>
            @endif
            @if($sia?->direccion)
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-600">📍 {{ $sia->direccion }}</span>
            @endif
            @if($obs)
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-yellow-50 text-yellow-700">📝 {{ $obs }}</span>
            @endif
        </div>

        {{-- Timeline --}}
        @if($sia && $siaEstado !== \App\Models\Pedidosia::ESTADO_CANCELADO)
        @php
            $pasos = $sia->tipo_entrega === 'retiro'
                ? [0 => 'Pendiente', 1 => 'Confirmado', 2 => 'Listo', 3 => 'Retirado']
                : [0 => 'Pendiente', 1 => 'Confirmado', 2 => 'Preparado', 3 => 'En camino', 4 => 'Entregado'];
        @endphp
        <div id="timeline-sia-{{ $sia->id }}" data-tipo="{{ $sia->tipo_entrega }}"
             class="flex items-center gap-0 mt-4 px-1">
            @foreach($pasos as $paso => $pasoLabel)
                @php
                    $done    = $siaEstado > $paso;
                    $current = $siaEstado === $paso;
                @endphp
                <div class="flex flex-col items-center shrink-0">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                        {{ $done    ? 'bg-red-600 text-white' : '' }}
                        {{ $current ? 'bg-red-100 text-red-700 ring-2 ring-red-500' : '' }}
                        {{ !$done && !$current ? 'bg-gray-100 text-gray-400' : '' }}">
                        @if($done) ✓ @else {{ $paso + 1 }} @endif
                    </div>
                    <span class="text-[10px] mt-1 leading-tight text-center w-12
                        {{ $current ? 'text-red-600 font-semibold' : ($done ? 'text-gray-500' : 'text-gray-300') }}">
                        {{ $pasoLabel }}
                    </span>
                </div>
                @if(!$loop->last)
                    <div class="h-0.5 flex-1 mb-4 {{ $done ? 'bg-red-400' : 'bg-gray-200' }}"></div>
                @endif
            @endforeach
        </div>
        @endif

    </div>

    {{-- Productos acordados --}}
    <div class="px-4 py-3 border-t border-gray-50">
        <p class="text-[11px] text-gray-400 uppercase font-semibold tracking-wide mb-2">Productos acordados</p>
        <div class="space-y-1">
            @foreach($items as $item)
            <div class="flex items-center justify-between gap-2 py-0.5">
                <span class="text-sm text-gray-700 leading-tight flex-1 min-w-0 truncate">{{ $item->descrip }}</span>
                <div class="text-right shrink-0 flex items-baseline gap-2">
                    <span class="text-xs text-gray-400">
                        @if($item->cant > 0 && $item->kilos > 0)
                            {{ (int) $item->cant }}u · {{ number_format($item->kilos, 2, ',', '.') }}kg
                        @elseif($item->kilos > 0)
                            {{ number_format($item->kilos, 3, ',', '.') }} kg
                        @else
                            {{ (int) $item->cant }} u
                        @endif
                    </span>
                    <span class="text-sm font-semibold text-gray-800 w-24 text-right">${{ number_format($item->neto, 2, ',', '.') }}</span>
                </div>
            </div>
            @endforeach
        </div>
        <div class="flex justify-between items-center mt-2.5 pt-2.5 border-t border-gray-100">
            <span class="text-sm text-gray-500">Total acordado</span>
            <span class="text-base font-bold text-red-700">${{ number_format($totalAcordado, 2, ',', '.') }}</span>
        </div>
    </div>

    {{-- Cómo salió (vmayo) --}}
    @if($vmayoRows?->isNotEmpty())
    <div class="px-4 py-3 border-t border-orange-100 bg-orange-50/60">
        <p class="text-[11px] text-orange-500 uppercase font-semibold tracking-wide mb-2">Cómo salió</p>
        <div class="space-y-1">
            @foreach($vmayoRows as $v)
            <div class="flex items-center justify-between gap-2 py-0.5">
                <span class="text-sm text-gray-700 leading-tight flex-1 min-w-0 truncate">{{ $v->descrip }}</span>
                <div class="text-right shrink-0 flex items-baseline gap-2">
                    <span class="text-xs text-gray-400">
                        @if($v->cant > 0 && $v->kilos > 0)
                            {{ (int) $v->cant }}u · {{ number_format($v->kilos, 3, ',', '.') }}kg
                        @elseif($v->kilos > 0)
                            {{ number_format($v->kilos, 3, ',', '.') }} kg
                        @else
                            {{ (int) $v->cant }} u
                        @endif
                    </span>
                    <span class="text-sm font-semibold text-gray-800 w-24 text-right">${{ number_format($v->NETO, 2, ',', '.') }}</span>
                </div>
            </div>
            @endforeach
        </div>
        <div class="flex justify-between items-center mt-2.5 pt-2.5 border-t border-orange-100">
            <span class="text-sm text-gray-500">Total</span>
            <span class="text-base font-bold text-orange-700">${{ number_format($vmayoRows->sum('NETO'), 2, ',', '.') }}</span>
        </div>
    </div>
    @endif

    {{-- Real facturado --}}
    @if($fact?->isNotEmpty())
    <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/60">
        <p class="text-[11px] text-gray-400 uppercase font-semibold tracking-wide mb-2">
            Real facturado
            @if($nroComprobante)
                <span class="text-gray-600 ml-1">— {{ $nroComprobante }}</span>
            @endif
        </p>
        <div class="space-y-1">
            @foreach($fact as $f)
            <div class="flex items-center justify-between gap-2 py-0.5">
                <span class="text-sm text-gray-700 leading-tight flex-1 min-w-0 truncate">{{ $f->descrip }}</span>
                <div class="text-right shrink-0 flex items-baseline gap-2">
                    <span class="text-xs text-gray-400">{{ number_format($f->kilos, 3, ',', '.') }} kg</span>
                    <span class="text-sm font-semibold text-gray-800 w-24 text-right">${{ number_format($f->neto ?? $f->NETO, 2, ',', '.') }}</span>
                </div>
            </div>
            @endforeach
        </div>
        <div class="flex justify-between items-center mt-2.5 pt-2.5 border-t border-gray-200">
            <span class="text-sm text-gray-500">Total real</span>
            <span class="text-base font-bold text-gray-800">${{ number_format($fact->sum('neto') ?: $fact->sum('NETO'), 2, ',', '.') }}</span>
        </div>
    </div>
    @endif

</div>
@empty
<div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-8 text-center">
    <p class="text-gray-400 text-sm">No hay pedidos para los filtros seleccionados.</p>
</div>
@endforelse
