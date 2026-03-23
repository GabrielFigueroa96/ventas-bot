<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $empresa->nombre_ia ?? 'Tienda')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
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
            @if(!empty($empresa->imagen_bienvenida))
                <img src="{{ asset($empresa->imagen_bienvenida) }}" alt="Logo"
                    class="w-10 h-10 rounded-full object-cover border border-gray-200 flex-shrink-0">
            @endif
            <span class="font-bold text-gray-800 text-lg truncate">
                {{ $empresa->nombre_ia ?? 'Tienda' }}
            </span>
        </a>

        {{-- Acciones --}}
        <div class="flex items-center gap-3 flex-shrink-0">
            @if(isset($cliente) && $cliente)
                <span class="hidden sm:block text-sm text-gray-500 truncate max-w-[120px]">
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
                    class="relative flex items-center gap-1 text-gray-700 hover:text-red-700 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    @if(($carritoData['count'] ?? 0) > 0)
                        <span id="carrito-badge"
                            class="absolute -top-1.5 -right-1.5 bg-red-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                            {{ $carritoData['count'] }}
                        </span>
                    @else
                        <span id="carrito-badge" class="hidden absolute -top-1.5 -right-1.5 bg-red-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold"></span>
                    @endif
                </a>
            @endisset
        </div>
    </div>
</header>

{{-- Flash messages --}}
@if(session('info'))
    <div class="max-w-6xl mx-auto px-4 mt-4">
        <div class="bg-blue-50 border border-blue-200 text-blue-700 text-sm px-4 py-3 rounded-lg">
            {{ session('info') }}
        </div>
    </div>
@endif

@if(session('ok'))
    <div class="max-w-6xl mx-auto px-4 mt-4">
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
            {{ session('ok') }}
        </div>
    </div>
@endif

{{-- Main content --}}
<main class="max-w-6xl mx-auto px-4 py-6 pb-24 lg:pb-6">
    @yield('content')
</main>

{{-- Bottom nav mobile (carrito flotante) --}}
@isset($carritoData)
    @if(($carritoData['count'] ?? 0) > 0)
        <div id="carrito-bar"
            class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-3 z-50 shadow-lg">
            <a href="{{ route('tienda.checkout', ['slug' => $slug]) }}"
                class="flex items-center justify-between w-full bg-red-700 hover:bg-red-800 text-white rounded-xl px-4 py-3 font-semibold text-sm transition">
                <span class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span id="bar-count">{{ $carritoData['count'] }} producto(s)</span>
                </span>
                <span id="bar-total">${{ number_format($carritoData['total'], 2, ',', '.') }}</span>
            </a>
        </div>
    @else
        <div id="carrito-bar" class="hidden lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-3 z-50 shadow-lg">
            <a href="{{ route('tienda.checkout', ['slug' => $slug]) }}"
                class="flex items-center justify-between w-full bg-red-700 text-white rounded-xl px-4 py-3 font-semibold text-sm">
                <span class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span id="bar-count">0 productos</span>
                </span>
                <span id="bar-total">$0,00</span>
            </a>
        </div>
    @endif
@endisset

@stack('scripts')
</body>
</html>
