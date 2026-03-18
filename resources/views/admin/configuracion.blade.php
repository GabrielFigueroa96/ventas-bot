@extends('admin.layout')
@section('title', 'Configuración del bot')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-xl font-bold text-gray-800">Configuración del bot</h1>

    @if(session('ok'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
            {{ session('ok') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.configuracion.save') }}" class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl shadow p-5 space-y-2">
            <label class="block text-sm font-semibold text-gray-700">Información del negocio</label>
            <p class="text-xs text-gray-400">Horarios, dirección, teléfono, datos de contacto. El bot los usa para responder consultas generales.</p>
            <textarea name="bot_info" rows="5"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
                placeholder="Ej: Estamos en Av. Italia 1234, Rosario. Atendemos lunes a sábado de 8 a 13hs. Tel: 341-555-0000.">{{ old('bot_info', $empresa?->bot_info) }}</textarea>
        </div>

        <div class="bg-white rounded-xl shadow p-5 space-y-2">
            <label class="block text-sm font-semibold text-gray-700">Instrucciones especiales</label>
            <p class="text-xs text-gray-400">Cómo tratar ciertos productos, restricciones, aclaraciones de cortes, promociones, etc.</p>
            <textarea name="bot_instrucciones" rows="8"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
                placeholder="Ej: El asado de tira se vende en piezas de aprox. 2kg. No hacemos cortes especiales salvo pedido previo. Los miércoles hay 10% de descuento en pollo.">{{ old('bot_instrucciones', $empresa?->bot_instrucciones) }}</textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-6 py-2 rounded-lg">
                Guardar
            </button>
        </div>
    </form>
</div>
@endsection
