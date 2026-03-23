@extends('tienda.layout')
@section('title', '¡Pedido recibido! — ' . ($empresaNombre ?? $empresa->nombre_ia ?? 'Tienda'))

@section('content')
<div class="max-w-md mx-auto mt-4">

    {{-- Éxito --}}
    <div class="text-center mb-6">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-1">¡Pedido recibido!</h1>
        <p class="text-gray-500 text-sm">
            Tu pedido <span class="font-semibold text-gray-700">#{{ $nro }}</span> fue registrado correctamente.
        </p>
        <p class="text-xs text-gray-400 mt-1">Vas a recibir la confirmación por WhatsApp.</p>
    </div>

    <div class="space-y-3">

        {{-- Resumen items --}}
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-50">
                <h2 class="text-sm font-semibold text-gray-700">Resumen del pedido #{{ $nro }}</h2>
            </div>
            <div class="px-4 py-3 space-y-2.5 divide-y divide-gray-50">
                @foreach($items as $item)
                    @php $esPorKilo = strtolower($item['tipo'] ?? '') !== 'unidad'; @endphp
                    <div class="flex justify-between items-center pt-2.5 first:pt-0">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $item['des'] }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $item['cantidad'] }} {{ $esPorKilo ? 'kg' : 'u.' }}
                            </p>
                        </div>
                        <p class="text-sm font-bold text-gray-800">
                            ${{ number_format($item['neto'], 2, ',', '.') }}
                        </p>
                    </div>
                @endforeach
            </div>
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                <span class="text-sm font-bold text-gray-800">Total</span>
                <span class="text-xl font-bold text-red-700">${{ number_format($total, 2, ',', '.') }}</span>
            </div>
        </div>

        {{-- Info entrega / pago --}}
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
                <div class="text-2xl mb-1">{{ $tipoEntrega === 'retiro' ? '🏪' : '🚚' }}</div>
                <p class="text-xs text-gray-400 mb-0.5">Entrega</p>
                <p class="text-sm font-semibold text-gray-800">
                    {{ $tipoEntrega === 'retiro' ? 'Retiro en local' : 'Envío a domicilio' }}
                </p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
                <div class="text-2xl mb-1">💳</div>
                <p class="text-xs text-gray-400 mb-0.5">Pago</p>
                <p class="text-sm font-semibold text-gray-800">
                    {{ \App\Models\IaEmpresa::MEDIOS_PAGO[$medioPago] ?? $medioPago }}
                </p>
            </div>
        </div>

        {{-- Acciones --}}
        <div class="space-y-2">
            <a href="{{ route('tienda.index', ['slug' => $slug]) }}"
                class="flex items-center justify-center gap-2 w-full bg-red-700 hover:bg-red-800 text-white text-sm font-semibold rounded-2xl py-3.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Seguir comprando
            </a>
            <a href="{{ route('tienda.mis_pedidos', ['slug' => $slug]) }}"
                class="flex items-center justify-center gap-2 w-full bg-white border border-gray-200 hover:border-red-300 text-gray-700 hover:text-red-700 text-sm font-semibold rounded-2xl py-3 transition">
                Ver mis pedidos
            </a>
        </div>

    </div>
</div>
@endsection
