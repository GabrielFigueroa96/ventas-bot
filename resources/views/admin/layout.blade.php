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
            gap: 10px;
            padding: 7px 12px;
            border-radius: 6px;
            font-size: 13.5px;
            font-weight: 500;
            color: rgba(255,255,255,0.45);
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
            border-left: 2px solid transparent;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.85);
        }
        .nav-link.active {
            background: rgba(255,255,255,0.09);
            color: #fff;
            border-left-color: #ef4444;
        }
        .nav-link svg { flex-shrink: 0; width: 16px; height: 16px; }

        .nav-section {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            color: rgba(255,255,255,0.22);
            text-transform: uppercase;
            padding: 14px 14px 5px;
        }

        #sidebar { transition: transform 0.25s ease; }
        @media (max-width: 1023px) {
            #sidebar { transform: translateX(-100%); position: fixed; z-index: 50; height: 100vh; }
            #sidebar.open { transform: translateX(0); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen lg:flex">

@php
    $empresaNombre ??= isset($empresa) ? $empresa->nombre : 'Panel Admin';
    $userName = auth()->user()->name ?? '';

    $sections = [
        'Operaciones' => [
            ['route' => 'admin.dashboard',     'label' => 'Dashboard',     'match' => 'admin.dashboard',      'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
            ['route' => 'admin.pedidos',       'label' => 'Pedidos',       'match' => 'admin.pedidos',         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>'],
            ['route' => 'admin.recordatorios', 'label' => 'Recordatorios', 'match' => 'admin.recordatorios*', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>'],
        ],
        'Catálogo' => [
            ['route' => 'admin.productos',   'label' => 'Productos',   'match' => 'admin.productos*',   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>'],
            ['route' => 'admin.localidades', 'label' => 'Localidades', 'match' => 'admin.localidades*', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>'],
        ],
        'Clientes' => [
            ['route' => 'admin.clientes', 'label' => 'Clientes', 'match' => 'admin.clientes*', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
        ],
        'Ajustes' => [
            ['route' => 'admin.tienda',        'label' => 'Web',          'match' => 'admin.tienda*',        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>'],
            ['route' => 'admin.configuracion', 'label' => 'Configuración','match' => 'admin.configuracion*', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'],
            ['route' => 'admin.uso_ia',        'label' => 'Uso IA',       'match' => 'admin.uso_ia',         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>'],
        ],
    ];
@endphp

{{-- ── Overlay mobile ──────────────────────────────────────────── --}}
<div id="overlay" class="hidden fixed inset-0 bg-black/50 z-40 lg:hidden" onclick="closeSidebar()"></div>

{{-- ── Sidebar ──────────────────────────────────────────────────── --}}
<aside id="sidebar" class="w-56 flex flex-col shrink-0 lg:sticky lg:top-0 lg:h-screen" style="background:#0d1829">

    {{-- Header --}}
    <div class="px-4 py-4" style="border-bottom:1px solid rgba(255,255,255,0.07)">
        <p class="text-xs font-semibold mb-2" style="color:rgba(255,255,255,0.28);letter-spacing:.06em">PANEL ADMIN</p>
        <div class="flex items-center gap-2.5">
            @if(!empty($logoTienda))
                <img src="{{ asset($logoTienda) }}" alt="{{ $empresaNombre }}"
                     class="w-7 h-7 rounded-lg object-cover shrink-0">
            @else
                <div class="w-7 h-7 rounded-lg flex items-center justify-center text-sm shrink-0" style="background:rgba(239,68,68,0.2)">🥩</div>
            @endif
            <p class="text-white font-semibold text-sm truncate">{{ $empresaNombre }}</p>
        </div>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 px-2 py-2 overflow-y-auto">
        @foreach($sections as $sectionLabel => $items)
            <p class="nav-section">{{ $sectionLabel }}</p>
            @foreach($items as $item)
            <a href="{{ route($item['route']) }}"
               class="nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}"
               onclick="closeSidebar()">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    {!! $item['icon'] !!}
                </svg>
                {{ $item['label'] }}
            </a>
            @endforeach
        @endforeach
    </nav>

    {{-- Footer usuario --}}
    <div class="px-3 py-3" style="border-top:1px solid rgba(255,255,255,0.07)">
        <div class="flex items-center gap-2.5 px-2 py-2 rounded-lg" style="background:rgba(255,255,255,0.05)">
            <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0" style="background:#b91c1c">
                {{ strtoupper(substr($userName, 0, 1)) }}
            </div>
            <span class="text-xs font-medium truncate flex-1" style="color:rgba(255,255,255,0.55)">{{ $userName }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" title="Salir" class="transition-colors" style="color:rgba(255,255,255,0.3)" onmouseover="this.style.color='#f87171'" onmouseout="this.style.color='rgba(255,255,255,0.3)'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- ── Contenido principal ──────────────────────────────────────── --}}
<div class="flex-1 flex flex-col min-w-0">

    {{-- Top bar --}}
    <header class="bg-white sticky top-0 z-30 flex items-center justify-between px-5 py-3" style="border-bottom:1px solid #e5e7eb">
        {{-- Izquierda: hamburger (mobile) + breadcrumb --}}
        <div class="flex items-center gap-3">
            <button id="hamburger" onclick="openSidebar()" class="lg:hidden p-1 rounded-md hover:bg-gray-100 transition-colors" aria-label="Menú">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div class="flex items-center gap-1.5 text-sm">
                <span class="text-gray-400 hidden lg:inline">{{ $empresaNombre }}</span>
                <span class="text-gray-300 hidden lg:inline">/</span>
                <span class="text-gray-700 font-semibold">@yield('title', 'Dashboard')</span>
            </div>
        </div>

        {{-- Derecha: bot status --}}
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex items-center gap-1.5 text-xs text-gray-400 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Bot activo
            </div>
            <div class="lg:hidden flex items-center gap-2">
                @if(!empty($logoTienda))
                    <img src="{{ asset($logoTienda) }}" alt="{{ $empresaNombre }}" class="w-6 h-6 rounded-md object-cover">
                @else
                    <span class="text-base">🥩</span>
                @endif
                <span class="font-semibold text-gray-800 text-sm truncate max-w-[140px]">{{ $empresaNombre }}</span>
            </div>
        </div>
    </header>

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
