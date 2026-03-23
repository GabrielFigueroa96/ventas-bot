@extends('tienda.layout')
@section('title', 'Checkout — ' . ($empresaNombre ?? $empresa->nombre_ia ?? 'Tienda'))

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-xl font-bold text-gray-800 mb-5">Confirmar pedido</h1>

    <div class="space-y-4">

        {{-- Resumen del carrito --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Tu pedido</h2>
            <div class="space-y-3 divide-y divide-gray-50">
                @foreach($carritoData['items'] as $item)
                    @php $esPorKilo = strtolower($item['tipo'] ?? '') !== 'unidad'; @endphp
                    <div class="flex justify-between items-start pt-3 first:pt-0 text-sm">
                        <div class="flex-1 pr-3">
                            <p class="font-medium text-gray-700">{{ $item['des'] }}</p>
                            <p class="text-gray-400 text-xs mt-0.5">
                                {{ $item['cantidad'] }} {{ $esPorKilo ? 'kg' : 'u.' }}
                                × ${{ number_format($item['precio'], 2, ',', '.') }}
                            </p>
                        </div>
                        <p class="font-semibold text-gray-800 whitespace-nowrap">
                            ${{ number_format($item['neto'], 2, ',', '.') }}
                        </p>
                    </div>
                @endforeach
            </div>

            {{-- Subtotal / Total --}}
            <div class="mt-4 pt-4 border-t border-gray-100 space-y-1.5">
                <div class="flex justify-between text-sm text-gray-500" id="row-subtotal">
                    <span>Subtotal</span>
                    <span id="subtotal-val">${{ number_format($carritoData['total'], 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-sm text-gray-500 hidden" id="row-envio">
                    <span>Costo de envío</span>
                    <span id="envio-val">$0,00</span>
                </div>
                <div class="flex justify-between items-center pt-1 border-t border-gray-100">
                    <span class="font-bold text-gray-800">Total</span>
                    <span class="text-xl font-bold text-red-700" id="total-val">
                        ${{ number_format($carritoData['total'], 2, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Formulario --}}
        <form method="POST" action="{{ route('tienda.confirmar', ['slug' => $slug]) }}" class="space-y-4" id="form-checkout">
            @csrf

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Tipo de entrega --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Tipo de entrega</h2>

                @if(!$empresa->bot_permite_envio && !$empresa->bot_permite_retiro)
                    <p class="text-sm text-gray-400">No hay tipos de entrega disponibles.</p>
                @else
                    <div class="space-y-3">
                        @if($empresa->bot_permite_envio)
                            <label class="flex items-start gap-3 cursor-pointer p-3 border border-gray-200 rounded-xl hover:border-red-300 transition has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                                <input type="radio" name="tipo_entrega" value="envio"
                                    {{ old('tipo_entrega', 'envio') === 'envio' ? 'checked' : '' }}
                                    class="mt-0.5 accent-red-600"
                                    onchange="toggleEntrega('envio')">
                                <div>
                                    <p class="font-medium text-gray-800 text-sm">🚚 Envío a domicilio</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Te lo llevamos a tu casa.</p>
                                </div>
                            </label>
                        @endif

                        @if($empresa->bot_permite_retiro)
                            <label class="flex items-start gap-3 cursor-pointer p-3 border border-gray-200 rounded-xl hover:border-red-300 transition has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                                <input type="radio" name="tipo_entrega" value="retiro"
                                    {{ old('tipo_entrega') === 'retiro' || (!$empresa->bot_permite_envio) ? 'checked' : '' }}
                                    class="mt-0.5 accent-red-600"
                                    onchange="toggleEntrega('retiro')">
                                <div>
                                    <p class="font-medium text-gray-800 text-sm">🏪 Retiro en local</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Retirás en nuestro local sin costo de envío.</p>
                                </div>
                            </label>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Dirección (solo si envío) --}}
            <div id="bloque-direccion"
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4
                {{ old('tipo_entrega', $empresa->bot_permite_envio ? 'envio' : 'retiro') === 'retiro' ? 'hidden' : '' }}">
                <h2 class="text-sm font-semibold text-gray-700">Dirección de entrega</h2>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Calle</label>
                        <input type="text" name="calle" value="{{ old('calle', $cliente->calle ?? '') }}"
                            placeholder="Av. Pellegrini"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Número</label>
                        <input type="text" name="numero" value="{{ old('numero', $cliente->numero ?? '') }}"
                            placeholder="1234"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Localidad</label>
                    <select name="localidad_id" id="select-localidad"
                        onchange="actualizarCostoEnvio(this.value)"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                        <option value="">— Seleccioná —</option>
                        @foreach($localidades as $loc)
                            <option value="{{ $loc->id }}"
                                data-costo="{{ $loc->costo_extra ?? 0 }}"
                                {{ old('localidad_id', $cliente->localidad_id ?? '') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->nombre }}
                                @if(($loc->costo_extra ?? 0) > 0)
                                    (+${{ number_format($loc->costo_extra, 2, ',', '.') }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Piso / Depto / Referencia</label>
                    <input type="text" name="dato_extra" value="{{ old('dato_extra', $cliente->dato_extra ?? '') }}"
                        placeholder="Piso 3 B, timbre con apellido García"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>
            </div>

            {{-- Fecha deseada --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Fecha deseada <span class="font-normal text-gray-400">(opcional)</span></h2>
                <input type="date" name="fecha_deseada"
                    value="{{ old('fecha_deseada') }}"
                    min="{{ now()->addDay()->format('Y-m-d') }}"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                <p class="text-xs text-gray-400 mt-1.5">Sujeto a disponibilidad.</p>
            </div>

            {{-- Medio de pago --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Medio de pago</h2>
                <div class="space-y-2">
                    @foreach($mediosPago as $key)
                        <label class="flex items-center gap-3 cursor-pointer p-3 border border-gray-200 rounded-xl hover:border-red-300 transition has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                            <input type="radio" name="medio_pago" value="{{ $key }}"
                                {{ old('medio_pago') === $key || (!old('medio_pago') && $loop->first) ? 'checked' : '' }}
                                class="accent-red-600">
                            <span class="text-sm text-gray-700 font-medium">
                                {{ \App\Models\IaEmpresa::MEDIOS_PAGO[$key] ?? $key }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Observaciones --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Observaciones <span class="font-normal text-gray-400">(opcional)</span></h2>
                <textarea name="obs" rows="3"
                    placeholder="Alguna aclaración sobre el pedido..."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">{{ old('obs') }}</textarea>
            </div>

            {{-- Aviso pedido mínimo --}}
            @if($pedidoMinimo > 0)
            <div class="flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-800 text-xs px-4 py-2.5 rounded-xl">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Pedido mínimo: <strong>${{ number_format($pedidoMinimo, 2, ',', '.') }}</strong>
            </div>
            @endif

            <button type="submit"
                class="w-full bg-red-700 hover:bg-red-800 active:bg-red-900 text-white font-bold rounded-xl py-4 text-base transition shadow-sm">
                Confirmar pedido
            </button>

        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const SUBTOTAL = {{ $carritoData['total'] }};

function toggleEntrega(tipo) {
    const bloque = document.getElementById('bloque-direccion');
    bloque.classList.toggle('hidden', tipo !== 'envio');
    if (tipo === 'retiro') {
        actualizarTotales(0);
    } else {
        const sel = document.getElementById('select-localidad');
        if (sel) actualizarCostoEnvio(sel.value);
    }
}

function actualizarCostoEnvio(localidadId) {
    const sel = document.getElementById('select-localidad');
    if (!sel) return;
    const opt = sel.querySelector(`option[value="${localidadId}"]`);
    const costo = opt ? parseFloat(opt.dataset.costo || 0) : 0;
    actualizarTotales(costo);
}

function actualizarTotales(costoEnvio) {
    const total = SUBTOTAL + costoEnvio;
    const rowEnvio = document.getElementById('row-envio');
    const envioVal = document.getElementById('envio-val');
    const totalVal = document.getElementById('total-val');

    if (rowEnvio) rowEnvio.classList.toggle('hidden', costoEnvio === 0);
    if (envioVal) envioVal.textContent = '$' + formatNum(costoEnvio);
    if (totalVal) totalVal.textContent = '$' + formatNum(total);
}

function formatNum(n) {
    return parseFloat(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Inicializar según selección actual
document.addEventListener('DOMContentLoaded', () => {
    const tipoChecked = document.querySelector('input[name="tipo_entrega"]:checked');
    if (tipoChecked) toggleEntrega(tipoChecked.value);
});
</script>
@endpush
