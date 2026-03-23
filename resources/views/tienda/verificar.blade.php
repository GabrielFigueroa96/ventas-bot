@extends('tienda.layout')

@section('title', 'Verificar código — ' . ($empresa->nombre_ia ?? 'Tienda'))

@section('content')
<div class="max-w-sm mx-auto mt-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">

        <div class="text-center space-y-1">
            <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-800">Ingresá el código</h1>
            <p class="text-sm text-gray-500">
                Enviamos un código de 4 dígitos al número<br>
                <strong class="text-gray-700">+{{ $phone }}</strong>
            </p>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('tienda.verificar.post', ['slug' => $slug]) }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5 text-center">
                    Código de verificación
                </label>
                <input
                    type="text"
                    name="code"
                    inputmode="numeric"
                    pattern="[0-9]{4}"
                    maxlength="4"
                    placeholder="0000"
                    autofocus
                    autocomplete="one-time-code"
                    class="w-full border border-gray-300 rounded-xl px-4 py-4 text-2xl font-mono text-center tracking-widest focus:outline-none focus:ring-2 focus:ring-red-300 @error('code') border-red-400 @enderror">
                <p class="text-xs text-gray-400 mt-1.5 text-center">
                    El código expira en 10 minutos.
                </p>
            </div>

            <button type="submit"
                class="w-full bg-red-700 hover:bg-red-800 text-white font-semibold rounded-xl py-3 text-sm transition">
                Verificar y entrar
            </button>
        </form>

        <div class="text-center space-y-2">
            <p class="text-sm text-gray-400">¿No recibiste el código?</p>
            <a href="{{ route('tienda.login', ['slug' => $slug]) }}"
                class="text-sm text-red-600 hover:text-red-800 font-medium transition">
                Reenviar código
            </a>
        </div>

    </div>
</div>
@endsection
