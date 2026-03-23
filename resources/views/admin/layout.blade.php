<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — {{ $empresaNombre ?? 'Panel' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #9ca3af;
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .nav-link:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-link.active { background: rgba(220,38,38,0.75); color: #fff; }
        .nav-link svg { flex-shrink: 0; width: 18px; height: 18px; opacity: 0.8; }
        .nav-link.active svg { opacity: 1; }
        #sidebar { transition: transform 0.25s ease; }
        @media (max-width: 1023px) {
            #sidebar { transform: translateX(-100%); position: fixed; z-index: 50; }
            #sidebar.open { transform: translateX(0); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen lg:flex">

@php
    $empresaNombre = $empresaNombre ?? (isset($empresa) ? $empresa->nombre : 'Panel Admin');
    $userName = auth()->user()->name ?? '';

    $nav = [
        ['route' => 'admin.dashboard',     'label' => 'Dashboard',    'match' => 'admin.dashboard',     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
        ['route' => 'admin.clientes',      'label' => 'Clientes',     'match' => 'admin.clientes*',     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
        ['route' => 'admin.pedidos',       'label' => 'Pedidos',      'match' => 'admin.pedidos',       'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>'],
        ['route' => 'admin.productos',     'label' => 'Productos',    'match' => 'admin.productos*',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>'],
        ['route' => 'admin.localidades',   'label' => 'Localidades',  'match' => 'admin.localidades*',  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>'],
        ['route' => 'admin.recordatorios', 'label' => 'Recordatorios','match' => 'admin.recordatorios*','icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>'],
        ['route' => 'admin.tienda',        'label' => 'Web',          'match' => 'admin.tienda*',       'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>'],
        ['route' => 'admin.configuracion', 'label' => 'Configuración',          'match' => 'admin.configuracion*','icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H4a2 2 0 01-2-2V5a2 2 0 012-2h16a2 2 0 012 2v10a2 2 0 01-2 2h-1"/>'],
        ['route' => 'admin.uso_ia',        'label' => 'Uso IA',       'match' => 'admin.uso_ia',        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>'],
    ];
@endphp

{{-- ── Overlay mobile ─────────────────────────────────────────── --}}
<div id="overlay" class="hidden fixed inset-0 bg-black/50 z-40 lg:hidden" onclick="closeSidebar()"></div>

{{-- ── Sidebar ─────────────────────────────────────────────────── --}}
<aside id="sidebar" class="w-64 bg-gray-900 min-h-screen flex flex-col shrink-0">

    {{-- Logo / Negocio --}}
    <div class="px-5 py-5 border-b border-white/10">
        <div class="flex items-center gap-3">
            @if(!empty($logoTienda))
                <img src="{{ asset($logoTienda) }}" alt="{{ $empresaNombre }}"
                    class="w-9 h-9 rounded-xl object-cover shrink-0">
            @else
                <div class="w-9 h-9 rounded-xl bg-red-600 flex items-center justify-center text-lg shrink-0">🥩</div>
            @endif
            <div class="min-w-0">
                <p class="text-white font-semibold text-sm leading-tight truncate">{{ $empresaNombre }}</p>
                <p class="text-gray-500 text-xs mt-0.5">Panel de administración</p>
            </div>
        </div>
    </div>

    {{-- Nav links --}}
    <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        @foreach($nav as $item)
        <a href="{{ route($item['route']) }}"
           class="nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}"
           onclick="closeSidebar()">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                {!! $item['icon'] !!}
            </svg>
            {{ $item['label'] }}
        </a>
        @endforeach
    </nav>

    {{-- Usuario / Salir --}}
    <div class="px-3 py-4 border-t border-white/10">
        <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-white/5">
            <div class="w-7 h-7 rounded-full bg-red-700 flex items-center justify-center text-white text-xs font-bold shrink-0">
                {{ strtoupper(substr($userName, 0, 1)) }}
            </div>
            <span class="text-gray-300 text-xs font-medium truncate flex-1">{{ $userName }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" title="Salir" class="text-gray-500 hover:text-red-400 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- ── Contenido principal ─────────────────────────────────────── --}}
<div class="flex-1 flex flex-col min-w-0">

    {{-- Top bar mobile --}}
    <header class="lg:hidden bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between sticky top-0 z-30 shadow-sm">
        <button id="hamburger" onclick="openSidebar()" class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors" aria-label="Menú">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <div class="flex items-center gap-2">
            @if(!empty($logoTienda))
                <img src="{{ asset($logoTienda) }}" alt="{{ $empresaNombre }}" class="w-6 h-6 rounded-lg object-cover">
            @else
                <span class="text-base">🥩</span>
            @endif
            <span class="font-semibold text-gray-800 text-sm truncate max-w-[180px]">{{ $empresaNombre }}</span>
        </div>
        <div class="w-8"></div>{{-- espaciador para centrar --}}
    </header>

    {{-- Breadcrumb / título página --}}
    <div class="hidden lg:block bg-white border-b border-gray-200 px-6 py-3">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">@yield('title', 'Dashboard')</p>
    </div>

    <main class="flex-1 px-4 sm:px-6 py-5 sm:py-7 max-w-7xl w-full mx-auto">
        @yield('content')
    </main>

</div>

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('overlay').classList.remove('hidden');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.add('hidden');
}
</script>
@yield('scripts')
</body>
</html>
