@extends('admin.layout')
@section('title', 'Configuración')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-xl font-bold text-gray-800">Configuración</h1>

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
                <label class="block text-xs font-medium text-gray-500 mb-1">Teléfono de contacto</label>
                <input type="text" name="telefono_pedidos" value="{{ old('telefono_pedidos', $config->telefono_pedidos) }}"
                    placeholder="Ej: 5493415550000 (con código de país, sin +)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                <p class="text-xs text-gray-400 mt-1">Se usa para recibir copia de pedidos por WhatsApp y para enviar el código de verificación al iniciar sesión.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Sucursal</label>
                <input type="text" name="suc" value="{{ old('suc', $config->suc) }}"
                    placeholder="Ej: 001"
                    maxlength="10"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                <p class="text-xs text-gray-400 mt-1">Código de sucursal que se asignará a todos los pedidos generados por el bot.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Punto de venta</label>
                <input type="text" name="pv" value="{{ old('pv', $config->pv) }}"
                    placeholder="Ej: 0001"
                    maxlength="10"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                <p class="text-xs text-gray-400 mt-1">Punto de venta que se asignará a todos los pedidos generados por el bot.</p>
            </div>

        </div>

        {{-- Logo --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Logo</h2>
            <p class="text-xs text-gray-400">Imagen que aparece en el encabezado de la tienda online y en el menú del panel de administración.</p>

            @if($config->imagen_tienda)
                <div class="flex items-center gap-4">
                    <img src="{{ asset($config->imagen_tienda) }}?v={{ $config->updated_at?->timestamp }}"
                        alt="Logo actual" class="w-20 h-20 object-cover rounded-xl border border-gray-200">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Logo actual</p>
                        <label class="flex items-center gap-1.5 mt-2 text-red-600 cursor-pointer text-xs">
                            <input type="checkbox" name="eliminar_imagen_tienda" value="1" class="accent-red-600">
                            Eliminar logo
                        </label>
                    </div>
                </div>
            @endif

            <input type="file" name="imagen_tienda" accept="image/*"
                class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
            <p class="text-xs text-gray-400">JPG, PNG o WebP. Recomendado: cuadrado, hasta 2 MB.</p>
        </div>

        {{-- Imagen de bienvenida --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Imagen de bienvenida</h2>
            <p class="text-xs text-gray-400">Se envía automáticamente al cliente la primera vez que escribe.</p>

            @if($config->imagen_bienvenida)
                <div class="flex items-center gap-4">
                    <img src="{{ asset($config->imagen_bienvenida) }}?v={{ $config->updated_at?->timestamp }}" alt="Imagen actual"
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

        {{-- Comportamiento del bot --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700">Comportamiento del bot</h2>

            {{-- Clientes nuevos --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-2">Atención de clientes nuevos</label>
                <div class="flex flex-col gap-2">
                    <label class="flex items-start gap-2 text-sm cursor-pointer">
                        <input type="radio" name="bot_atiende_nuevos" value="bot"
                            {{ old('bot_atiende_nuevos', $config->bot_atiende_nuevos ?? 'bot') === 'bot' ? 'checked' : '' }}
                            class="mt-0.5 accent-red-600">
                        <span>
                            <span class="font-medium text-gray-700">Bot</span>
                            <span class="block text-xs text-gray-400">El bot registra al cliente y toma pedidos normalmente.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 text-sm cursor-pointer">
                        <input type="radio" name="bot_atiende_nuevos" value="humano"
                            {{ old('bot_atiende_nuevos', $config->bot_atiende_nuevos ?? 'bot') === 'humano' ? 'checked' : '' }}
                            class="mt-0.5 accent-red-600">
                        <span>
                            <span class="font-medium text-gray-700">Humano</span>
                            <span class="block text-xs text-gray-400">El bot solo envía el mensaje de bienvenida. La atención queda a cargo de una persona.</span>
                        </span>
                    </label>
                </div>
            </div>

            <hr class="border-gray-100">

            <div class="flex items-start gap-3">
                <input type="checkbox" name="bot_puede_pedir" value="1" id="bot_puede_pedir"
                    {{ old('bot_puede_pedir', $config->bot_puede_pedir ?? true) ? 'checked' : '' }}
                    class="mt-0.5 accent-red-600">
                <label for="bot_puede_pedir" class="text-sm cursor-pointer">
                    <span class="font-medium text-gray-700">Puede tomar pedidos</span>
                    <span class="block text-xs text-gray-400">Si está desactivado, el bot solo informa precios. El cliente debe llamar o escribir al negocio para pedir.</span>
                </label>
            </div>

            <div class="flex items-start gap-3">
                <input type="checkbox" name="bot_puede_sugerir" value="1" id="bot_puede_sugerir"
                    {{ old('bot_puede_sugerir', $config->bot_puede_sugerir ?? true) ? 'checked' : '' }}
                    class="mt-0.5 accent-red-600">
                <label for="bot_puede_sugerir" class="text-sm cursor-pointer">
                    <span class="font-medium text-gray-700">Puede sugerir productos</span>
                    <span class="block text-xs text-gray-400">El bot puede proponer productos al cliente de forma proactiva durante la conversación.</span>
                </label>
            </div>

            <div class="flex items-start gap-3">
                <input type="checkbox" name="bot_puede_mas_vendidos" value="1" id="bot_puede_mas_vendidos"
                    {{ old('bot_puede_mas_vendidos', $config->bot_puede_mas_vendidos ?? false) ? 'checked' : '' }}
                    class="mt-0.5 accent-red-600">
                <label for="bot_puede_mas_vendidos" class="text-sm cursor-pointer">
                    <span class="font-medium text-gray-700">Puede informar los más vendidos</span>
                    <span class="block text-xs text-gray-400">El bot puede mencionar cuáles son los productos más pedidos del negocio.</span>
                </label>
            </div>

            <hr class="border-gray-100">

            <div class="flex items-start gap-3">
                <input type="checkbox" name="bot_notifica_estados" value="1" id="bot_notifica_estados"
                    {{ old('bot_notifica_estados', $config->bot_notifica_estados ?? true) ? 'checked' : '' }}
                    class="mt-0.5 accent-red-600">
                <label for="bot_notifica_estados" class="text-sm cursor-pointer">
                    <span class="font-medium text-gray-700">Notificar estados al cliente por WhatsApp</span>
                    <span class="block text-xs text-gray-400">Cuando se avanza el estado de un pedido (confirmado, preparado, en camino, entregado), se envía un mensaje automático al cliente.</span>
                </label>
            </div>
        </div>

        {{-- Tipos de entrega --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Tipos de entrega</h2>
            <div class="flex flex-col gap-2">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="bot_permite_envio" value="1" id="bot_permite_envio"
                        {{ old('bot_permite_envio', $config->bot_permite_envio ?? true) ? 'checked' : '' }}
                        class="accent-red-600">
                    <span class="font-medium text-gray-700">Envío</span>
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="bot_permite_retiro" value="1" id="bot_permite_retiro"
                        {{ old('bot_permite_retiro', $config->bot_permite_retiro ?? true) ? 'checked' : '' }}
                        class="accent-red-600">
                    <span class="font-medium text-gray-700">Retiro en local</span>
                </label>
            </div>
        </div>

        {{-- Medios de pago --}}
        <div class="bg-white rounded-xl shadow p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Medios de pago</h2>
            <div class="flex flex-col gap-2">
                @foreach(\App\Models\IaEmpresa::MEDIOS_PAGO as $key => $label)
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="bot_medios_pago[]" value="{{ $key }}"
                            {{ in_array($key, old('bot_medios_pago', $config->bot_medios_pago ?? array_keys(\App\Models\IaEmpresa::MEDIOS_PAGO))) ? 'checked' : '' }}
                            class="accent-red-600">
                        <span class="text-gray-700">{{ $label }}</span>
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
