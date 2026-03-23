@extends('tienda.layout')

@section('title', 'Pedido confirmado — ' . ($empresa->nombre_ia ?? 'Tienda'))

@section('content')
<div class="max-w-md mx-auto mt-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">

        {{-- Ícono de éxito --}}
        <div class="text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-9 h-9 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">¡Pedido recibido!</h1>
            <p class="text-sm text-gray-500 mt-1">
                Tu pedido <span class="font-semibold text-gray-700">#{{ $nro }}</span> fue enviado correctamente.
            </p>
            <p class="text-xs text-gray-400 mt-1">
                Te enviamos la confirmación por WhatsApp.
            </p>
        </div>

        {{-- Resumen --}}
        <div class="bg-gray-50 rounded-xl p-4 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Resumen del pedido #{{ $nro }}</h2>

            <div class="space-y-2 divide-y divide-gray-100">
                @foreach($items as $item)
                    @php $esPorKilo = strtolower($item['tipo'] ?? '') !== 'unidad'; @endphp
                    <div class="flex justify-between items-center pt-2 first:pt-0 text-sm">
                        <div>
                            <p class="font-medium text-gray-700">{{ $item['des'] }}</p>
                            <p class="text-xs text-gray-400">
                                {{ $item['cantidad'] }} {{ $esPorKilo ? 'kg' : 'u.' }}
                            </p>
                        </div>
                        <p class="font-semibold text-gray-800">
                            ${{ number_format($item['neto'], 2, ',', '.') }}
                        </p>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                <span class="font-bold text-gray-800">Total</span>
                <span class="text-lg font-bold text-red-700">
                    ${{ number_format($total, 2, ',', '.') }}
                </span>
            </div>
        </div>

        {{-- Info entrega / pago --}}
        <div class="flex gap-3">
            <div class="flex-1 bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-gray-400 mb-1">Entrega</p>
                <p class="text-sm font-semibold text-gray-700">
                    {{ $tipoEntrega === 'retiro' ? 'Retiro en local' : 'Envío a domicilio' }}
                </p>
            </div>
            <div class="flex-1 bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-gray-400 mb-1">Pago</p>
                <p class="text-sm font-semibold text-gray-700">
                    {{ \App\Models\IaEmpresa::MEDIOS_PAGO[$medioPago] ?? $medioPago }}
                </p>
            </div>
        </div>

        {{-- Acciones --}}
        <div class="space-y-2">
            <a href="{{ route('tienda.index', ['slug' => $slug]) }}"
                class="block w-full bg-red-700 hover:bg-red-800 text-white text-center font-semibold rounded-xl py-3 text-sm transition">
                Volver a la tienda
            </a>
        </div>

    </div>
</div>
@endsection
