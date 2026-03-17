<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — Carnicería Bot</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <nav class="bg-red-700 text-white shadow">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <span class="font-bold text-lg">🥩 @yield('empresa_nombre', 'Carnicería Bot')</span>
            <div class="flex items-center gap-6 text-sm font-medium">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-red-200 {{ request()->routeIs('admin.dashboard') ? 'underline' : '' }}">Dashboard</a>
                <a href="{{ route('admin.clientes') }}" class="hover:text-red-200 {{ request()->routeIs('admin.clientes*') ? 'underline' : '' }}">Clientes</a>
                <a href="{{ route('admin.pedidos') }}" class="hover:text-red-200 {{ request()->routeIs('admin.pedidos') ? 'underline' : '' }}">Pedidos</a>
                <span class="text-red-300">|</span>
                <span class="text-red-200 text-xs">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-red-200 hover:text-white text-xs">Salir</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-8">
        @yield('content')
    </main>

</body>
</html>
