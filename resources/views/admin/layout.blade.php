<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — Carnicería Bot</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Montserrat', sans-serif; }</style>
</head>
<body class="bg-gray-100 min-h-screen">

    <nav class="bg-red-700 text-white shadow">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <span class="font-bold text-lg">🥩 @yield('empresa_nombre', 'Carnicería Bot')</span>

                {{-- Hamburger (mobile) --}}
                <button id="nav-toggle" class="md:hidden flex flex-col gap-1.5 p-1" aria-label="Menú">
                    <span class="block w-6 h-0.5 bg-white"></span>
                    <span class="block w-6 h-0.5 bg-white"></span>
                    <span class="block w-6 h-0.5 bg-white"></span>
                </button>

                {{-- Links (desktop) --}}
                <div class="hidden md:flex items-center gap-6 text-sm font-medium">
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-red-200 {{ request()->routeIs('admin.dashboard') ? 'underline' : '' }}">Dashboard</a>
                    <a href="{{ route('admin.clientes') }}"  class="hover:text-red-200 {{ request()->routeIs('admin.clientes*') ? 'underline' : '' }}">Clientes</a>
                    <a href="{{ route('admin.pedidos') }}"   class="hover:text-red-200 {{ request()->routeIs('admin.pedidos') ? 'underline' : '' }}">Pedidos</a>
                    <a href="{{ route('admin.productos') }}" class="hover:text-red-200 {{ request()->routeIs('admin.productos*') ? 'underline' : '' }}">Productos</a>
                    <a href="{{ route('admin.localidades') }}" class="hover:text-red-200 {{ request()->routeIs('admin.localidades*') ? 'underline' : '' }}">Localidades</a>
                    <a href="{{ route('admin.recordatorios') }}" class="hover:text-red-200 {{ request()->routeIs('admin.recordatorios*') ? 'underline' : '' }}">Recordatorios</a>
                    <a href="{{ route('admin.configuracion') }}" class="hover:text-red-200 {{ request()->routeIs('admin.configuracion*') ? 'underline' : '' }}">Bot</a>
                    <span class="text-red-300">|</span>
                    <span class="text-red-200 text-xs">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-red-200 hover:text-white text-xs">Salir</button>
                    </form>
                </div>
            </div>

            {{-- Menú mobile (oculto por defecto) --}}
            <div id="nav-menu" class="hidden md:hidden mt-3 pb-2 flex flex-col gap-3 text-sm font-medium border-t border-red-600 pt-3">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-red-200 {{ request()->routeIs('admin.dashboard') ? 'underline' : '' }}">Dashboard</a>
                <a href="{{ route('admin.clientes') }}"  class="hover:text-red-200 {{ request()->routeIs('admin.clientes*') ? 'underline' : '' }}">Clientes</a>
                <a href="{{ route('admin.pedidos') }}"   class="hover:text-red-200 {{ request()->routeIs('admin.pedidos') ? 'underline' : '' }}">Pedidos</a>
                <a href="{{ route('admin.productos') }}" class="hover:text-red-200 {{ request()->routeIs('admin.productos*') ? 'underline' : '' }}">Productos</a>
                <a href="{{ route('admin.localidades') }}" class="hover:text-red-200 {{ request()->routeIs('admin.localidades*') ? 'underline' : '' }}">Localidades</a>
                <a href="{{ route('admin.recordatorios') }}" class="hover:text-red-200 {{ request()->routeIs('admin.recordatorios*') ? 'underline' : '' }}">Recordatorios</a>
                <a href="{{ route('admin.configuracion') }}" class="hover:text-red-200 {{ request()->routeIs('admin.configuracion*') ? 'underline' : '' }}">Bot</a>
                <div class="flex items-center justify-between pt-1 border-t border-red-600">
                    <span class="text-red-200 text-xs">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-red-200 hover:text-white text-xs">Salir</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-3 sm:px-4 py-5 sm:py-8">
        @yield('content')
    </main>

    <script>
        document.getElementById('nav-toggle')?.addEventListener('click', () => {
            document.getElementById('nav-menu').classList.toggle('hidden');
        });
    </script>
    @yield('scripts')
</body>
</html>
