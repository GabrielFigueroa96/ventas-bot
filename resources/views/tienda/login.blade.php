@extends('tienda.layout')
@section('title', 'Ingresar — ' . ($empresaNombre ?? $empresa->nombre_ia ?? 'Tienda'))

@section('content')
<div class="max-w-sm mx-auto mt-6">
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">

        {{-- Header --}}
        <div class="bg-gradient-to-br from-red-700 to-red-800 px-6 py-8 text-center">
            @if(!empty($empresa->imagen_tienda) || !empty($empresa->imagen_bienvenida))
                <img src="{{ asset($empresa->imagen_tienda ?: $empresa->imagen_bienvenida) }}" alt="Logo"
                    class="w-16 h-16 rounded-2xl object-cover mx-auto mb-4 border-2 border-white/30 shadow-lg">
            @else
                <div class="w-16 h-16 rounded-2xl bg-white/20 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            @endif
            <h1 class="text-xl font-bold text-white mb-1">
                {{ $empresaNombre ?? $empresa->nombre_ia ?? 'Tienda' }}
            </h1>
            <p class="text-red-200 text-sm">Ingresá para ver precios y comprar</p>
        </div>

        {{-- Form --}}
        <div class="p-6 space-y-4">
            <div>
                <p class="text-sm text-gray-600 text-center mb-4">
                    Te enviaremos un código de verificación por WhatsApp.
                </p>
            </div>

            @if($errors->any())
                <div class="bg-red-50 border border-red-100 text-red-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('tienda.login.post', ['slug' => $slug]) }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Número de WhatsApp
                    </label>
                    <input type="tel" name="phone" value="{{ old('phone') }}"
                        placeholder="Ej: 3415550000"
                        autofocus
                        class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 bg-gray-50 focus:bg-white @error('phone') border-red-400 @enderror">
                    <p class="text-xs text-gray-400 mt-1.5">Sin el 0 ni el 15, solo los dígitos.</p>
                </div>

                <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 active:bg-green-800 text-white font-semibold rounded-xl py-3 text-sm transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        <path d="M11.999 0C5.373 0 0 5.373 0 12c0 2.117.549 4.1 1.514 5.827L.057 23.4a.75.75 0 00.916.916l5.573-1.457A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0h-.001zm0 21.818a9.818 9.818 0 01-5.007-1.367l-.358-.213-3.713.972.99-3.613-.234-.372A9.818 9.818 0 012.18 12c0-5.42 4.4-9.818 9.818-9.818S21.818 6.58 21.818 12 17.418 21.818 12 21.818z"/>
                    </svg>
                    Enviar código por WhatsApp
                </button>
            </form>

            <div class="text-center pt-1">
                <a href="{{ route('tienda.index', ['slug' => $slug]) }}"
                    class="text-sm text-gray-400 hover:text-gray-600 transition">
                    ← Volver al catálogo
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
