@extends('tienda.layout')

@section('title', 'Ingresar — ' . ($empresa->nombre_ia ?? 'Tienda'))

@section('content')
<div class="max-w-sm mx-auto mt-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">

        <div class="text-center space-y-1">
            @if(!empty($empresa->imagen_bienvenida))
                <img src="{{ asset($empresa->imagen_bienvenida) }}" alt="Logo"
                    class="w-16 h-16 rounded-full object-cover mx-auto mb-3 border border-gray-200">
            @endif
            <h1 class="text-xl font-bold text-gray-800">Ingresar a la tienda</h1>
            <p class="text-sm text-gray-500">Te enviaremos un código por WhatsApp para verificar tu identidad.</p>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('tienda.login.post', ['slug' => $slug]) }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Número de teléfono
                </label>
                <input
                    type="tel"
                    name="phone"
                    value="{{ old('phone') }}"
                    placeholder="Ej: 3415550000"
                    autofocus
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 @error('phone') border-red-400 @enderror">
                <p class="text-xs text-gray-400 mt-1.5">
                    Ingresá tu número sin el 0 ni el 15 (solo los dígitos).
                </p>
            </div>

            <button type="submit"
                class="w-full bg-red-700 hover:bg-red-800 text-white font-semibold rounded-xl py-3 text-sm transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    <path d="M11.999 0C5.373 0 0 5.373 0 12c0 2.117.549 4.1 1.514 5.827L.057 23.4a.75.75 0 00.916.916l5.573-1.457A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0h-.001zm0 21.818a9.818 9.818 0 01-5.007-1.367l-.358-.213-3.713.972.99-3.613-.234-.372A9.818 9.818 0 012.18 12c0-5.42 4.4-9.818 9.818-9.818S21.818 6.58 21.818 12 17.418 21.818 12 21.818z"/>
                </svg>
                Enviar código por WhatsApp
            </button>
        </form>

        <div class="text-center">
            <a href="{{ route('tienda.index', ['slug' => $slug]) }}"
                class="text-sm text-gray-400 hover:text-gray-600 transition">
                Volver al catálogo
            </a>
        </div>
    </div>
</div>
@endsection
