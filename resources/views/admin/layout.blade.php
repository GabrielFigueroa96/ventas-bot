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
    @stack('styles')
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
        .nav-link.active svg { color: #f87171; }
        .nav-link svg { flex-shrink: 0; width: 16px; height: 16px; }

        .nav-section {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            color: rgba(255,255,255,0.22);
            text-transform: uppercase;
            padding: 14px 14px 5px;
        }
        .nav-section:first-child { padding-top: 6px; }

        /* Scrollbar sidebar */
        nav::-webkit-scrollbar { width: 3px; }
        nav::-webkit-scrollbar-track { background: transparent; }
        nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 99px; }
        nav::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        /* Card hover */
        .card { transition: box-shadow 0.18s, transform 0.18s; }
        .card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); transform: translateY(-1px); }

        #sidebar { transition: width 0.22s ease, transform 0.25s ease; }
        @media (max-width: 1023px) {
            #sidebar { transform: translateX(-100%); position: fixed; z-index: 50; height: 100vh; }
            #sidebar.open { transform: translateX(0); }
        }
        /* ── Sidebar colapsado (solo desktop) ── */
        @media (min-width: 1024px) {
            #sidebar.collapsed { width: 4rem; }
            #sidebar.collapsed .sb-text { display: none; }
            #sidebar.collapsed .sb-section { opacity: 0; height: 0; padding: 0; overflow: hidden; margin: 0; }
            #sidebar.collapsed .nav-link { justify-content: center; padding: 8px 0; gap: 0; border-left-color: transparent; }
            #sidebar.collapsed .nav-link.active { border-right: 2px solid #ef4444; }
            #sidebar.collapsed .nav-link svg { width: 20px; height: 20px; }
            #sidebar.collapsed .sb-header { justify-content: center; padding: 1rem 0; }
            #sidebar.collapsed .sb-footer-row { flex-direction: column; align-items: center; gap: 6px; padding: 8px 4px; }
            #sidebar.collapsed #sb-toggle svg { transform: rotate(180deg); }
            #sidebar.collapsed nav { padding-left: 0; padding-right: 0; }
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
            ['route' => 'admin.conversaciones', 'label' => 'Conversaciones', 'match' => 'admin.conversaciones*', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>'],
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
    <div class="px-4 py-4 relative" style="border-bottom:1px solid rgba(255,255,255,0.07)">
        <p class="sb-text text-xs font-semibold mb-2" style="color:rgba(255,255,255,0.28);letter-spacing:.06em">PANEL ADMIN</p>
        <div class="sb-header flex items-center gap-2.5">
            @if(!empty($logoTienda))
                <img src="{{ asset($logoTienda) }}" alt="{{ $empresaNombre }}"
                     class="w-7 h-7 rounded-lg object-cover shrink-0">
            @else
                <div class="w-7 h-7 rounded-lg flex items-center justify-center text-sm shrink-0" style="background:rgba(239,68,68,0.2)">🥩</div>
            @endif
            <p class="sb-text text-white font-semibold text-sm truncate">{{ $empresaNombre }}</p>
        </div>
        {{-- Toggle colapsar (solo desktop) --}}
        <button id="sb-toggle" onclick="toggleSidebar()" title="Retraer menú"
            class="hidden lg:flex absolute right-2 top-1/2 -translate-y-1/2 items-center justify-center w-6 h-6 rounded-md transition-colors"
            style="color:rgba(255,255,255,0.3)" onmouseover="this.style.color='rgba(255,255,255,0.7)'" onmouseout="this.style.color='rgba(255,255,255,0.3)'">
            <svg class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 px-2 py-2 overflow-y-auto">
        @foreach($sections as $sectionLabel => $items)
            <p class="nav-section sb-section">{{ $sectionLabel }}</p>
            @foreach($items as $item)
            <a href="{{ route($item['route']) }}"
               class="nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}"
               title="{{ $item['label'] }}"
               onclick="closeSidebar()">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    {!! $item['icon'] !!}
                </svg>
                <span class="sb-text">{{ $item['label'] }}</span>
            </a>
            @endforeach
        @endforeach
    </nav>

    {{-- Footer usuario --}}
    <div class="px-3 py-3" style="border-top:1px solid rgba(255,255,255,0.07)">
        <div class="sb-footer-row flex items-center gap-2.5 px-2 py-2 rounded-lg" style="background:rgba(255,255,255,0.05)">
            <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0" style="background:#b91c1c">
                {{ strtoupper(substr($userName, 0, 1)) }}
            </div>
            <span class="sb-text text-xs font-medium truncate flex-1" style="color:rgba(255,255,255,0.55)">{{ $userName }}</span>
            <a href="{{ route('admin.cuenta') }}" title="Cambiar contraseña" class="transition-colors shrink-0"
               style="color:rgba(255,255,255,0.3)"
               onmouseover="this.style.color='rgba(255,255,255,0.7)'" onmouseout="this.style.color='rgba(255,255,255,0.3)'">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </a>
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

        {{-- Derecha: fecha + bot status --}}
        <div class="flex items-center gap-3">
            <span class="hidden md:block text-xs text-gray-400 font-medium">
                {{ now()->locale('es')->isoFormat('dddd D [de] MMMM') }}
            </span>
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

{{-- ── Toast container ──────────────────────────────────────────── --}}
<div id="toast-wrap" class="fixed z-[60] flex flex-col gap-2" style="top:4.5rem;right:1rem;width:300px;pointer-events:none"></div>

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('overlay').classList.remove('hidden');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.add('hidden');
}
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const collapsed = sb.classList.toggle('collapsed');
    localStorage.setItem('sb_collapsed', collapsed ? '1' : '0');
}
// Restaurar estado al cargar
(function () {
    if (window.innerWidth >= 1024 && localStorage.getItem('sb_collapsed') === '1') {
        document.getElementById('sidebar').classList.add('collapsed');
    }
    const nav = document.querySelector('#sidebar nav');
    if (nav) nav.scrollTop = nav.scrollHeight;
})();

// ── Toast system ──────────────────────────────────────────────────────────────
(function () {
    const wrap = document.getElementById('toast-wrap');
    const cfg = {
        success: { bg:'#059669', icon:'<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>' },
        error:   { bg:'#dc2626', icon:'<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>' },
        warning: { bg:'#b45309', icon:'<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>' },
        info:    { bg:'#2563eb', icon:'<path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' },
    };

    window.showToast = function (msg, type = 'success') {
        const c = cfg[type] ?? cfg.success;
        const t = document.createElement('div');
        t.style.cssText = [
            'pointer-events:auto',
            'display:flex',
            'align-items:center',
            'gap:10px',
            'padding:11px 14px',
            'border-radius:12px',
            `background:${c.bg}`,
            'color:#fff',
            'font-size:13px',
            'font-weight:500',
            'box-shadow:0 4px 24px rgba(0,0,0,0.18)',
            'transform:translateX(calc(100% + 1.5rem))',
            'transition:transform 0.32s cubic-bezier(0.34,1.4,0.64,1)',
            'font-family:inherit',
        ].join(';');
        t.innerHTML = `
            <svg style="width:15px;height:15px;flex-shrink:0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">${c.icon}</svg>
            <span style="flex:1;line-height:1.4">${msg}</span>
            <button onclick="dismissToast(this.parentNode)" style="opacity:.55;background:none;border:none;color:#fff;cursor:pointer;font-size:18px;line-height:1;padding:0 0 0 4px" title="Cerrar">×</button>`;
        wrap.appendChild(t);
        requestAnimationFrame(() => requestAnimationFrame(() => t.style.transform = 'translateX(0)'));
        setTimeout(() => dismissToast(t), 4500);
    };

    window.dismissToast = function (t) {
        if (!t || !t.parentNode) return;
        t.style.transform = 'translateX(calc(100% + 1.5rem))';
        setTimeout(() => t.remove(), 320);
    };

    // Flash messages del servidor
    @if(session('ok'))
    document.addEventListener('DOMContentLoaded', () => showToast(@json(session('ok')), 'success'));
    @endif
    @if(session('success'))
    document.addEventListener('DOMContentLoaded', () => showToast(@json(session('success')), 'success'));
    @endif
    @if(session('error'))
    document.addEventListener('DOMContentLoaded', () => showToast(@json(session('error')), 'error'));
    @endif
    @if(session('warning'))
    document.addEventListener('DOMContentLoaded', () => showToast(@json(session('warning')), 'warning'));
    @endif
    @if(session('info'))
    document.addEventListener('DOMContentLoaded', () => showToast(@json(session('info')), 'info'));
    @endif
})();

// ── Form submit: deshabilitar botón al enviar ─────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form:not([data-no-loading])').forEach(form => {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('button[type=submit], input[type=submit]');
            if (!btn) return;
            btn.disabled = true;
            const orig = btn.innerHTML || btn.value;
            if (btn.tagName === 'INPUT') {
                btn.value = 'Guardando…';
            } else {
                btn.innerHTML = '<svg class="animate-spin inline w-4 h-4 mr-1.5 -mt-0.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>' + (btn.textContent.trim() || 'Guardando…');
            }
            // restaurar si hay error de validación (navegación hacia atrás)
            window.addEventListener('pageshow', () => { btn.disabled = false; btn.innerHTML = orig; });
        });
    });
});
</script>
@yield('scripts')
</body>
</html>
