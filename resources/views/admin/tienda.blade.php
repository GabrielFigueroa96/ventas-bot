@extends('admin.layout')
@section('title', 'Web')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-xl font-bold text-gray-800">Web para visualizar productos y pedidos</h1>

    {{-- Estado de la tienda --}}
    @if($tiendaActiva)
        <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl">
            <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span><strong>Web habilitada.</strong> Los clientes pueden visualizar productos y su historial de pedidos.</span>
        </div>
    @else
        <div class="flex items-start gap-3 bg-gray-50 border border-gray-200 text-gray-600 text-sm px-4 py-4 rounded-xl">
            <svg class="w-5 h-5 text-gray-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-semibold text-gray-700 mb-1">Web no habilitada</p>
                <p class="text-gray-500">La web no está activada para esta cuenta. Contactá al soporte para habilitarla.</p>
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
