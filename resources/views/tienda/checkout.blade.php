@extends('tienda.layout')
@section('title', 'Checkout — ' . ($empresaNombre ?? $empresa->nombre_ia ?? 'Tienda'))

@section('content')
<div class="max-w-xl mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('tienda.index', ['slug' => $slug]) }}"
            class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-lg font-bold text-gray-800">Confirmar pedido</h1>
    </div>

    <div class="space-y-3">

        {{-- Resumen del carrito --}}
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-50">
                <h2 class="text-sm font-semibold text-gray-700">Tu pedido</h2>
            </div>
            <div class="px-4 py-3 space-y-2.5 divide-y divide-gray-50">
                @foreach($carritoData['items'] as $item)
                    @php $esPorKilo = strtolower($item['tipo'] ?? '') !== 'unidad'; @endphp
                    <div class="flex justify-between items-start pt-2.5 first:pt-0">
                        <div class="flex-1 pr-3">
                            <p class="text-sm font-medium text-gray-800">{{ $item['des'] }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $item['cantidad'] }} {{ $esPorKilo ? 'kg' : 'u.' }}
                                × ${{ number_format($item['precio'], 2, ',', '.') }}
                            </p>
                        </div>
                        <p class="text-sm font-bold text-gray-800 whitespace-nowrap">
                            ${{ number_format($item['neto'], 2, ',', '.') }}
                        </p>
                    </div>
                @endforeach
            </div>
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                <span class="text-sm font-bold text-gray-800">Total</span>
                <span class="text-xl font-bold text-red-700">
                    ${{ number_format($carritoData['total'], 2, ',', '.') }}
                </span>
            </div>
        </div>

        {{-- Formulario --}}
        <form method="POST" action="{{ route('tienda.confirmar', ['slug' => $slug]) }}" class="space-y-3" id="form-checkout">
            @csrf

            @if($errors->any())
                <div class="bg-red-50 border border-red-100 text-red-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Tipo de entrega --}}
            <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Tipo de entrega</h2>
                </div>
                <div class="p-3 space-y-2">
                    @if(!$empresa->bot_permite_envio && !$empresa->bot_permite_retiro)
                        <p class="text-sm text-gray-400 px-1">No hay tipos de entrega disponibles.</p>
                    @else
                        @if($empresa->bot_permite_envio)
                            <label class="flex items-center gap-3 cursor-pointer p-3 border-2 rounded-xl transition
                                {{ old('tipo_entrega', 'envio') === 'envio' ? 'border-red-500 bg-red-50' : 'border-gray-100 hover:border-red-200' }}">
                                <input type="radio" name="tipo_entrega" value="envio"
                                    {{ old('tipo_entrega', 'envio') === 'envio' ? 'checked' : '' }}
                                    class="accent-red-600"
                                    onchange="toggleEntrega('envio')">
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-gray-800">Envío</p>
                                    <p class="text-xs text-gray-400">Te lo llevamos</p>
                                </div>
                                <span class="text-xl">🚚</span>
                            </label>
                        @endif

                        @if($empresa->bot_permite_retiro)
                            <label class="flex items-center gap-3 cursor-pointer p-3 border-2 rounded-xl transition
                                {{ old('tipo_entrega') === 'retiro' || !$empresa->bot_permite_envio ? 'border-red-500 bg-red-50' : 'border-gray-100 hover:border-red-200' }}">
                                <input type="radio" name="tipo_entrega" value="retiro"
                                    {{ old('tipo_entrega') === 'retiro' || (!$empresa->bot_permite_envio) ? 'checked' : '' }}
                                    class="accent-red-600"
                                    onchange="toggleEntrega('retiro')">
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-gray-800">Retiro en local</p>
                                    <p class="text-xs text-gray-400">Sin costo de envío</p>
                                </div>
                                <span class="text-xl">🏪</span>
                            </label>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Dirección --}}
            <div id="bloque-direccion"
                class="{{ old('tipo_entrega', $empresa->bot_permite_envio ? 'envio' : 'retiro') === 'retiro' ? 'hidden' : '' }}
                    bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Dirección de entrega</h2>
                </div>
                <div class="p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1.5">Calle</label>
                            <input type="text" name="calle" value="{{ old('calle', $cliente->calle ?? '') }}"
                                placeholder="Av. Pellegrini"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 bg-gray-50 focus:bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1.5">Número</label>
                            <input type="text" name="numero" value="{{ old('numero', $cliente->numero ?? '') }}"
                                placeholder="1234"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 bg-gray-50 focus:bg-white">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Localidad</label>
                        <select name="localidad_id"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 bg-gray-50 focus:bg-white">
                            <option value="">— Seleccioná —</option>
                            @foreach($localidades as $loc)
                                <option value="{{ $loc->id }}"
                                    {{ old('localidad_id', $cliente->localidad_id ?? '') == $loc->id ? 'selected' : '' }}>
                                    {{ $loc->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Piso / Depto / Referencia <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <input type="text" name="dato_extra" value="{{ old('dato_extra', $cliente->dato_extra ?? '') }}"
                            placeholder="Piso 3 B, timbre García"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 bg-gray-50 focus:bg-white">
                    </div>
                </div>
            </div>

            {{-- Fecha deseada --}}
            <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Fecha deseada <span class="font-normal text-gray-400">(opcional)</span></h2>
                </div>
                <div class="p-4">
                    <input type="date" name="fecha_deseada"
                        value="{{ old('fecha_deseada') }}"
                        min="{{ now()->addDay()->format('Y-m-d') }}"
                        class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 bg-gray-50 focus:bg-white">
                    <p class="text-xs text-gray-400 mt-1.5">Sujeto a disponibilidad.</p>
                </div>
            </div>

            {{-- Medio de pago --}}
            <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Medio de pago</h2>
                </div>
                <div class="p-3 space-y-2">
                    @foreach($mediosPago as $key)
                        <label class="flex items-center gap-3 cursor-pointer p-3 border-2 rounded-xl transition
                            {{ (!old('medio_pago') && $loop->first) || old('medio_pago') === $key ? 'border-red-500 bg-red-50' : 'border-gray-100 hover:border-red-200' }}">
                            <input type="radio" name="medio_pago" value="{{ $key }}"
                                {{ old('medio_pago') === $key || (!old('medio_pago') && $loop->first) ? 'checked' : '' }}
                                class="accent-red-600">
                            <span class="text-sm font-medium text-gray-800">
                                {{ \App\Models\IaEmpresa::MEDIOS_PAGO[$key] ?? $key }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Observaciones --}}
            <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Observaciones <span class="font-normal text-gray-400">(opcional)</span></h2>
                </div>
                <div class="p-4">
                    <textarea name="obs" rows="3"
                        placeholder="Alguna aclaración sobre el pedido..."
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 bg-gray-50 focus:bg-white resize-none">{{ old('obs') }}</textarea>
                </div>
            </div>

            {{-- Aviso pedido mínimo --}}
            @if($pedidoMinimo > 0)
            <div class="flex items-center gap-2 bg-amber-50 border border-amber-100 text-amber-800 text-xs px-4 py-3 rounded-xl">
                <svg class="w-4 h-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                Pedido mínimo: <strong>${{ number_format($pedidoMinimo, 2, ',', '.') }}</strong>
            </div>
            @endif

            <button type="submit"
                class="w-full bg-red-700 hover:bg-red-800 active:bg-red-900 text-white font-bold rounded-2xl py-4 text-base transition shadow-sm">
                Confirmar pedido
            </button>

        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleEntrega(tipo) {
    const bloque = document.getElementById('bloque-direccion');
    bloque.classList.toggle('hidden', tipo !== 'envio');

    // Actualizar estilos de los labels
    document.querySelectorAll('input[name="tipo_entrega"]').forEach(radio => {
        const label = radio.closest('label');
        if (!label) return;
        const activo = radio.value === tipo;
        label.classList.toggle('border-red-500', activo);
        label.classList.toggle('bg-red-50', activo);
        label.classList.toggle('border-gray-100', !activo);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const tipoChecked = document.querySelector('input[name="tipo_entrega"]:checked');
    if (tipoChecked) toggleEntrega(tipoChecked.value);

    // Estilos reactivos para medios de pago
    document.querySelectorAll('input[name="medio_pago"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('input[name="medio_pago"]').forEach(r => {
                const label = r.closest('label');
                if (!label) return;
                label.classList.toggle('border-red-500', r.checked);
                label.classList.toggle('bg-red-50', r.checked);
                label.classList.toggle('border-gray-100', !r.checked);
            });
        });
    });
});
</script>
@endpush
