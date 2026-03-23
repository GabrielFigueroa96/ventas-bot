<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $empresaNombre ?? $empresa->nombre_ia ?? 'Tienda')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        .line-clamp-2 { display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
        .line-clamp-3 { display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden; }
        .scrollbar-hide::-webkit-scrollbar { display:none; }
        .scrollbar-hide { -ms-overflow-style:none; scrollbar-width:none; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
        .fade-in { animation: fadeIn .25s ease both; }
    </style>
    @stack('head')
</head>
<body class="bg-gray-50 min-h-screen font-sans antialiased">

{{-- Header --}}
<header class="bg-white border-b border-gray-100 sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between gap-3">

        {{-- Logo / Nombre --}}
        <a href="{{ route('tienda.index', ['slug' => $slug]) }}" class="flex items-center gap-2.5 min-w-0">
            @if(!empty($empresa->imagen_tienda))
                <img src="{{ asset($empresa->imagen_tienda) }}" alt="Logo"
                    class="w-8 h-8 rounded-lg object-cover flex-shrink-0">
            @elseif(!empty($empresa->imagen_bienvenida))
                <img src="{{ asset($empresa->imagen_bienvenida) }}" alt="Logo"
                    class="w-8 h-8 rounded-lg object-cover flex-shrink-0">
            @else
                <div class="w-8 h-8 rounded-lg bg-red-700 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            @endif
            <span class="font-bold text-gray-900 text-base truncate leading-tight">
                {{ $empresaNombre ?? $empresa->nombre_ia ?? 'Tienda' }}
            </span>
        </a>

        {{-- Acciones --}}
        <div class="flex items-center gap-1.5 flex-shrink-0">
            @if(isset($cliente) && $cliente)
                <a href="{{ route('tienda.mis_pedidos', ['slug' => $slug]) }}"
                    class="hidden sm:flex items-center gap-1.5 text-xs text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg px-2.5 py-1.5 transition font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Mis pedidos
                </a>
                <form method="POST" action="{{ route('tienda.logout', ['slug' => $slug]) }}" class="inline">
                    @csrf
                    <button type="submit" title="Salir"
                        class="flex items-center gap-1 text-xs text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg px-2.5 py-1.5 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span class="hidden sm:inline">Salir</span>
                    </button>
                </form>
            @else
                <a href="{{ route('tienda.login', ['slug' => $slug]) }}"
                    class="text-xs text-white bg-red-700 hover:bg-red-800 rounded-lg px-3 py-1.5 transition font-semibold">
                    Ingresar
                </a>
            @endif

            {{-- Carrito --}}
            @isset($carritoData)
                <a href="{{ route('tienda.checkout', ['slug' => $slug]) }}"
                    class="relative flex items-center justify-center w-9 h-9 text-gray-700 hover:text-red-700 hover:bg-red-50 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span id="carrito-badge"
                        class="{{ ($carritoData['count'] ?? 0) > 0 ? '' : 'hidden' }} absolute -top-0.5 -right-0.5 bg-red-600 text-white text-[10px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-0.5 leading-none">
                        {{ $carritoData['count'] ?? 0 }}
                    </span>
                </a>
            @endisset
        </div>
    </div>
</header>

{{-- Flash messages --}}
@if(session('info'))
    <div class="max-w-6xl mx-auto px-4 pt-3">
        <div class="bg-blue-50 border border-blue-100 text-blue-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ session('info') }}
        </div>
    </div>
@endif
@if(session('ok'))
    <div class="max-w-6xl mx-auto px-4 pt-3">
        <div class="bg-green-50 border border-green-100 text-green-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ session('ok') }}
        </div>
    </div>
@endif

{{-- Main --}}
<main class="max-w-6xl mx-auto px-4 py-5 pb-28 lg:pb-10">
    @yield('content')
</main>

{{-- Barra inferior carrito (móvil) --}}
@isset($carritoData)
<div id="carrito-bar"
    class="{{ ($carritoData['count'] ?? 0) > 0 ? '' : 'hidden' }} lg:hidden fixed bottom-0 left-0 right-0 z-50 p-3 bg-white border-t border-gray-100 shadow-lg">
    <a href="{{ route('tienda.checkout', ['slug' => $slug]) }}"
        class="flex items-center justify-between w-full bg-red-700 hover:bg-red-800 active:bg-red-900 text-white rounded-xl px-4 py-3 font-semibold text-sm transition">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span id="bar-count">{{ $carritoData['count'] ?? 0 }} producto{{ ($carritoData['count'] ?? 0) !== 1 ? 's' : '' }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span id="bar-total" class="font-bold">${{ number_format($carritoData['total'] ?? 0, 2, ',', '.') }}</span>
            <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </div>
    </a>
</div>
@endisset

@stack('scripts')
</body>
</html>
