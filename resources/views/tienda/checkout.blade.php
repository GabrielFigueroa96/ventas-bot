@extends('tienda.layout')

@section('title', 'Checkout — ' . ($empresa->nombre_ia ?? 'Tienda'))

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-xl font-bold text-gray-800 mb-5">Confirmar pedido</h1>

    <div class="space-y-5">

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
                                {{ $item['cantidad'] }}
                                {{ $esPorKilo ? 'kg' : 'u.' }}
                                × ${{ number_format($item['precio'], 2, ',', '.') }}
                            </p>
                        </div>
                        <p class="font-semibold text-gray-800 whitespace-nowrap">
                            ${{ number_format($item['neto'], 2, ',', '.') }}
                        </p>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-100">
                <span class="font-bold text-gray-800">Total</span>
                <span class="text-xl font-bold text-red-700">
                    ${{ number_format($carritoData['total'], 2, ',', '.') }}
                </span>
            </div>
        </div>

        {{-- Formulario del pedido --}}
        <form method="POST" action="{{ route('tienda.confirmar', ['slug' => $slug]) }}" class="space-y-5"
            id="form-checkout">
            @csrf

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
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
                                    onchange="toggleDireccion(this.value)">
                                <div>
                                    <p class="font-medium text-gray-800 text-sm">Envío a domicilio</p>
                                    <p class="text-xs text-gray-400">Te lo llevamos a tu casa.</p>
                                </div>
                            </label>
                        @endif

                        @if($empresa->bot_permite_retiro)
                            <label class="flex items-start gap-3 cursor-pointer p-3 border border-gray-200 rounded-xl hover:border-red-300 transition has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                                <input type="radio" name="tipo_entrega" value="retiro"
                                    {{ old('tipo_entrega') === 'retiro' || (!$empresa->bot_permite_envio) ? 'checked' : '' }}
                                    class="mt-0.5 accent-red-600"
                                    onchange="toggleDireccion(this.value)">
                                <div>
                                    <p class="font-medium text-gray-800 text-sm">Retiro en local</p>
                                    <p class="text-xs text-gray-400">Retirás en nuestro local.</p>
                                </div>
                            </label>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Dirección (solo si envío) --}}
            <div id="bloque-direccion" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4
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
                    <select name="localidad_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
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
                    <label class="block text-xs font-medium text-gray-500 mb-1">Dato extra (piso, depto, referencia)</label>
                    <input type="text" name="dato_extra" value="{{ old('dato_extra', $cliente->dato_extra ?? '') }}"
                        placeholder="Piso 3 B, timbre con apellido García"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>
            </div>

            {{-- Fecha deseada --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Fecha deseada</h2>
                <input type="date" name="fecha_deseada"
                    value="{{ old('fecha_deseada') }}"
                    min="{{ now()->addDay()->format('Y-m-d') }}"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                <p class="text-xs text-gray-400 mt-1.5">Opcional. Sujeto a disponibilidad.</p>
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
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Observaciones</h2>
                <textarea name="obs" rows="3"
                    placeholder="Alguna aclaración sobre el pedido..."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">{{ old('obs') }}</textarea>
            </div>

            {{-- Botón confirmar --}}
            <button type="submit"
                class="w-full bg-red-700 hover:bg-red-800 text-white font-bold rounded-xl py-4 text-base transition shadow-sm">
                Confirmar pedido
            </button>

        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleDireccion(tipo) {
    const bloque = document.getElementById('bloque-direccion');
    if (tipo === 'envio') {
        bloque.classList.remove('hidden');
    } else {
        bloque.classList.add('hidden');
    }
}
</script>
@endpush
