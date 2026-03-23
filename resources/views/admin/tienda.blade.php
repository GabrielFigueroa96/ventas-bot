@extends('admin.layout')
@section('title', 'Tienda online')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-xl font-bold text-gray-800">Tienda online</h1>

    {{-- Estado de la tienda --}}
    @if($tiendaActiva)
        <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl">
            <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span><strong>Tienda habilitada.</strong> Los clientes pueden comprar desde la tienda online.</span>
        </div>
    @else
        <div class="flex items-start gap-3 bg-gray-50 border border-gray-200 text-gray-600 text-sm px-4 py-4 rounded-xl">
            <svg class="w-5 h-5 text-gray-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-semibold text-gray-700 mb-1">Tienda no habilitada</p>
                <p class="text-gray-500">La tienda online no está activada para esta cuenta. Contactá al soporte para habilitarla.</p>
            </div>
        </div>
    @endif

    @if($tiendaActiva)

        @if(session('ok'))
            <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">{{ session('ok') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.tienda.save') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            {{-- URL pública --}}
            <div class="bg-white rounded-xl shadow p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700">URL pública</h2>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Slug de la tienda</label>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400 whitespace-nowrap">{{ request()->getSchemeAndHttpHost() }}/tienda/</span>
                        <input type="text" name="slug_tienda" value="{{ old('slug_tienda', $config->slug ?? '') }}"
                            placeholder="mi-empresa"
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Solo letras, números y guiones.</p>
                </div>

                @if($config->slug)
                    <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-500">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                        <span>
                            Enlace:
                            <a href="{{ request()->getSchemeAndHttpHost() }}/tienda/{{ $config->slug }}"
                               target="_blank" class="text-red-600 hover:underline font-medium">
                                {{ request()->getSchemeAndHttpHost() }}/tienda/{{ $config->slug }}
                            </a>
                        </span>
                    </div>
                @endif
            </div>

            {{-- Logo de la tienda --}}
            <div class="bg-white rounded-xl shadow p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700">Logo de la tienda</h2>
                <p class="text-xs text-gray-400">Imagen que aparece en el encabezado de la tienda online (el logo del negocio).</p>

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

            {{-- Pedido mínimo --}}
            <div class="bg-white rounded-xl shadow p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700">Pedido mínimo</h2>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Monto mínimo ($)</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-400">$</span>
                        <input type="number" name="pedido_minimo"
                            value="{{ old('pedido_minimo', $config->pedido_minimo ?? 0) }}"
                            min="0" step="0.01" placeholder="0"
                            class="w-40 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Poner 0 para no exigir mínimo.</p>
                </div>
            </div>

            {{-- Precios visibles --}}
            <div class="bg-white rounded-xl shadow p-5">
                <div class="flex items-start gap-3">
                    <input type="checkbox" name="tienda_ocultar_precios" value="1" id="tienda_ocultar_precios"
                        {{ old('tienda_ocultar_precios', $config->tienda_ocultar_precios ?? false) ? 'checked' : '' }}
                        class="mt-0.5 accent-red-600">
                    <label for="tienda_ocultar_precios" class="text-sm cursor-pointer">
                        <span class="font-medium text-gray-700">Ocultar precios a usuarios no logueados</span>
                        <span class="block text-xs text-gray-400 mt-0.5">Los precios solo serán visibles para clientes que hayan iniciado sesión.</span>
                    </label>
                </div>
            </div>

            {{-- Redes sociales --}}
            <div class="bg-white rounded-xl shadow p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700">Redes sociales</h2>
                <p class="text-xs text-gray-400 -mt-2">Los botones aparecerán en la tienda. Ingresá la URL completa (ej: https://instagram.com/tu-negocio).</p>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Facebook</label>
                    <input type="url" name="tienda_facebook"
                        value="{{ old('tienda_facebook', $config->tienda_facebook ?? '') }}"
                        placeholder="https://facebook.com/tu-negocio"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Instagram</label>
                    <input type="url" name="tienda_instagram"
                        value="{{ old('tienda_instagram', $config->tienda_instagram ?? '') }}"
                        placeholder="https://instagram.com/tu-negocio"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">TikTok</label>
                    <input type="url" name="tienda_tiktok"
                        value="{{ old('tienda_tiktok', $config->tienda_tiktok ?? '') }}"
                        placeholder="https://tiktok.com/@tu-negocio"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-6 py-2 rounded-lg">
                    Guardar
                </button>
            </div>
        </form>

    @endif
</div>
@endsection
