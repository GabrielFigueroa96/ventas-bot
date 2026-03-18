@extends('admin.layout')
@section('title', 'Configuración del bot')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-xl font-bold text-gray-800">Configuración del bot</h1>

    @if(session('ok'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">{{ session('ok') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.configuracion.save') }}" class="space-y-5">
        @csrf

        {{-- Días de reparto --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-3">
            <label class="block text-sm font-semibold text-gray-700">Días de reparto</label>
            <p class="text-xs text-gray-400">El bot informa al cliente cuándo puede recibir su pedido. Vacío = todos los días.</p>
            <div class="flex flex-wrap gap-3">
                @foreach(\App\Models\Empresa::DIAS_LABEL as $num => $label)
                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                        <input type="checkbox" name="bot_dias_reparto[]" value="{{ $num }}"
                            {{ in_array($num, old('bot_dias_reparto', $empresa?->bot_dias_reparto ?? [])) ? 'checked' : '' }}
                            class="accent-red-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Tipo de entrega --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-3">
            <label class="block text-sm font-semibold text-gray-700">Tipos de entrega habilitados</label>
            <div class="flex gap-6">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="bot_permite_envio" value="1"
                        {{ old('bot_permite_envio', $empresa?->bot_permite_envio ?? true) ? 'checked' : '' }}
                        class="accent-red-600">
                    🚚 Envío a domicilio
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="bot_permite_retiro" value="1"
                        {{ old('bot_permite_retiro', $empresa?->bot_permite_retiro ?? true) ? 'checked' : '' }}
                        class="accent-red-600">
                    🏪 Retiro en local
                </label>
            </div>
        </div>

        {{-- Medios de pago --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-3">
            <label class="block text-sm font-semibold text-gray-700">Medios de pago habilitados</label>
            <div class="flex flex-wrap gap-4">
                @foreach(\App\Models\Empresa::MEDIOS_PAGO as $key => $label)
                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                        <input type="checkbox" name="bot_medios_pago[]" value="{{ $key }}"
                            {{ in_array($key, old('bot_medios_pago', $empresa?->bot_medios_pago ?? array_keys(\App\Models\Empresa::MEDIOS_PAGO))) ? 'checked' : '' }}
                            class="accent-red-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Info del negocio --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-2">
            <label class="block text-sm font-semibold text-gray-700">Información del negocio</label>
            <p class="text-xs text-gray-400">Dirección, teléfono, horarios de atención. El bot usa esto para responder consultas generales.</p>
            <textarea name="bot_info" rows="4"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
                placeholder="Ej: Estamos en Av. Italia 1234, Rosario. Tel: 341-555-0000. Atendemos lunes a sábado de 8 a 13hs.">{{ old('bot_info', $empresa?->bot_info) }}</textarea>
        </div>

        {{-- Instrucciones especiales --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-2">
            <label class="block text-sm font-semibold text-gray-700">Instrucciones especiales</label>
            <p class="text-xs text-gray-400">Cómo tratar ciertos productos, restricciones de corte, promociones vigentes, etc.</p>
            <textarea name="bot_instrucciones" rows="6"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
                placeholder="Ej: El asado de tira se vende en piezas de aprox. 2kg. No hacemos cortes especiales salvo pedido previo.">{{ old('bot_instrucciones', $empresa?->bot_instrucciones) }}</textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-6 py-2 rounded-lg">
                Guardar
            </button>
        </div>
    </form>
</div>
@endsection
