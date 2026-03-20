@extends('admin.layout')
@section('title', 'Configuración del bot')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-xl font-bold text-gray-800">Configuración del bot</h1>

    @if(session('ok'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">{{ session('ok') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.configuracion.save') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        {{-- Identidad del bot --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700">Identidad del bot</h2>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Nombre del asistente</label>
                <input type="text" name="nombre_ia" value="{{ old('nombre_ia', $config->nombre_ia) }}"
                    placeholder="Ej: Martina, Asistente La Carnicería, etc."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                <p class="text-xs text-gray-400 mt-1">El bot se presentará con este nombre al nuevo cliente.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Teléfono del local (para pedidos)</label>
                <input type="text" name="telefono_pedidos" value="{{ old('telefono_pedidos', $config->telefono_pedidos) }}"
                    placeholder="Ej: 341-555-0000"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                <p class="text-xs text-gray-400 mt-1">El bot puede informar este número si el cliente lo consulta.</p>
            </div>
        </div>

        {{-- Imagen de bienvenida --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Imagen de bienvenida</h2>
            <p class="text-xs text-gray-400">Se envía automáticamente al cliente la primera vez que escribe.</p>

            @if($config->imagen_bienvenida)
                <div class="flex items-center gap-4">
                    <img src="{{ asset($config->imagen_bienvenida) }}" alt="Imagen actual"
                        class="w-24 h-24 object-cover rounded-lg border border-gray-200">
                    <div class="text-sm text-gray-500">
                        <p class="font-medium text-gray-700">Imagen actual</p>
                        <label class="flex items-center gap-1.5 mt-2 text-red-600 cursor-pointer text-xs">
                            <input type="checkbox" name="eliminar_imagen" value="1" class="accent-red-600">
                            Eliminar imagen
                        </label>
                    </div>
                </div>
            @endif

            <div>
                <input type="file" name="imagen_bienvenida" accept="image/*"
                    class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                <p class="text-xs text-gray-400 mt-1">JPG, PNG o WebP. Recomendado: hasta 5 MB.</p>
            </div>
        </div>

        {{-- Tipo de entrega --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-3">
            <label class="block text-sm font-semibold text-gray-700">Tipos de entrega habilitados</label>
            <div class="flex gap-6">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="bot_permite_envio" value="1"
                        {{ old('bot_permite_envio', $config->bot_permite_envio ?? true) ? 'checked' : '' }}
                        class="accent-red-600">
                    🚚 Envío
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="bot_permite_retiro" value="1"
                        {{ old('bot_permite_retiro', $config->bot_permite_retiro ?? true) ? 'checked' : '' }}
                        class="accent-red-600">
                    🏪 Retiro en local
                </label>
            </div>
        </div>

        {{-- Medios de pago --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-3">
            <label class="block text-sm font-semibold text-gray-700">Medios de pago habilitados</label>
            <div class="flex flex-wrap gap-4">
                @foreach(\App\Models\IaEmpresa::MEDIOS_PAGO as $key => $label)
                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                        <input type="checkbox" name="bot_medios_pago[]" value="{{ $key }}"
                            {{ in_array($key, old('bot_medios_pago', $config->bot_medios_pago ?? array_keys(\App\Models\IaEmpresa::MEDIOS_PAGO))) ? 'checked' : '' }}
                            class="accent-red-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Info del negocio --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-2">
            <label class="block text-sm font-semibold text-gray-700">Información del negocio</label>
            <p class="text-xs text-gray-400">Dirección, horarios de atención. El bot usa esto para responder consultas generales.</p>
            <textarea name="bot_info" rows="4"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
                placeholder="Ej: Estamos en Av. Italia 1234, Rosario. Atendemos lunes a sábado de 8 a 13hs.">{{ old('bot_info', $config->bot_info) }}</textarea>
        </div>

        {{-- Instrucciones especiales --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-2">
            <label class="block text-sm font-semibold text-gray-700">Instrucciones especiales</label>
            <p class="text-xs text-gray-400">Cómo tratar ciertos productos, restricciones de corte, promociones vigentes, etc.</p>
            <textarea name="bot_instrucciones" rows="6"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
                placeholder="Ej: El asado de tira se vende en piezas de aprox. 2kg. No hacemos cortes especiales salvo pedido previo.">{{ old('bot_instrucciones', $config->bot_instrucciones) }}</textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-6 py-2 rounded-lg">
                Guardar
            </button>
        </div>
    </form>
</div>
@endsection
