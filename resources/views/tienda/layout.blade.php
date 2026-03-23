<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $empresaNombre ?? $empresa->nombre_ia ?? 'Tienda')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
    @stack('head')
</head>
<body class="bg-gray-50 min-h-screen">

{{-- Header --}}
<header class="bg-white shadow-sm sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">

        {{-- Logo / Nombre --}}
        <a href="{{ route('tienda.index', ['slug' => $slug]) }}" class="flex items-center gap-3 min-w-0">
            @if(!empty($empresa->imagen_tienda))
                <img src="{{ asset($empresa->imagen_tienda) }}" alt="Logo"
                    class="w-10 h-10 rounded-full object-cover border border-gray-200 flex-shrink-0">
            @elseif(!empty($empresa->imagen_bienvenida))
                <img src="{{ asset($empresa->imagen_bienvenida) }}" alt="Logo"
                    class="w-10 h-10 rounded-full object-cover border border-gray-200 flex-shrink-0">
            @endif
            <span class="font-bold text-gray-800 text-lg truncate">
                {{ $empresaNombre ?? $empresa->nombre_ia ?? 'Tienda' }}
            </span>
        </a>

        {{-- Acciones --}}
        <div class="flex items-center gap-2 flex-shrink-0">
            @if(isset($cliente) && $cliente)
                {{-- Link mis pedidos --}}
                <a href="{{ route('tienda.mis_pedidos', ['slug' => $slug]) }}"
                    class="hidden sm:flex items-center gap-1.5 text-xs text-gray-500 hover:text-red-600 border border-gray-200 rounded-lg px-3 py-1.5 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Mis pedidos
                </a>
                <span class="hidden sm:block text-xs text-gray-400 truncate max-w-[100px]">
                    {{ $cliente->name ?: $cliente->phone }}
                </span>
                <form method="POST" action="{{ route('tienda.logout', ['slug' => $slug]) }}" class="inline">
                    @csrf
                    <button type="submit"
                        class="text-xs text-gray-500 hover:text-red-600 border border-gray-200 rounded-lg px-3 py-1.5 transition">
                        Salir
                    </button>
                </form>
            @else
                <a href="{{ route('tienda.login', ['slug' => $slug]) }}"
                    class="text-xs text-white bg-red-700 hover:bg-red-800 rounded-lg px-3 py-1.5 transition font-medium">
                    Ingresar
                </a>
            @endif

            {{-- Carrito badge --}}
            @isset($carritoData)
                <a href="{{ route('tienda.checkout', ['slug' => $slug]) }}"
                    class="relative flex items-center text-gray-700 hover:text-red-700 transition p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    @if(($carritoData['count'] ?? 0) > 0)
                        <span id="carrito-badge"
                            class="absolute -top-0.5 -right-0.5 bg-red-600 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center font-bold leading-none">
                            {{ $carritoData['count'] }}
                        </span>
                    @else
                        <span id="carrito-badge" class="hidden absolute -top-0.5 -right-0.5 bg-red-600 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center font-bold leading-none"></span>
                    @endif
                </a>
            @endisset
        </div>
    </div>
</header>

{{-- Flash messages --}}
@if(session('info'))
    <div class="max-w-6xl mx-auto px-4 mt-3">
        <div class="bg-blue-50 border border-blue-200 text-blue-700 text-sm px-4 py-3 rounded-xl">
            {{ session('info') }}
        </div>
    </div>
@endif
@if(session('ok'))
    <div class="max-w-6xl mx-auto px-4 mt-3">
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
            {{ session('ok') }}
        </div>
    </div>
@endif

{{-- Main content --}}
<main class="max-w-6xl mx-auto px-4 py-5 pb-28 lg:pb-8">
    @yield('content')
</main>

{{-- Bottom bar móvil: carrito --}}
@isset($carritoData)
    <div id="carrito-bar"
        class="{{ ($carritoData['count'] ?? 0) > 0 ? '' : 'hidden' }} lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-3 z-50 shadow-lg">
        <a href="{{ route('tienda.checkout', ['slug' => $slug]) }}"
            class="flex items-center justify-between w-full bg-red-700 hover:bg-red-800 text-white rounded-xl px-4 py-3 font-semibold text-sm transition">
            <span class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span id="bar-count">{{ $carritoData['count'] }} producto(s)</span>
            </span>
            <span id="bar-total">${{ number_format($carritoData['total'] ?? 0, 2, ',', '.') }}</span>
        </a>
    </div>
@endisset

@stack('scripts')
</body>
</html>
