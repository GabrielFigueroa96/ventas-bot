@extends('admin.layout')
@section('title', 'Pedidos')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">Pedidos</h1>

{{-- Filtros --}}
<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <input type="text" name="search" value="{{ request('search') }}"
        placeholder="Buscar cliente..."
        class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">

    <select name="estado" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <option value="">Todos los estados</option>
        <option value="0" {{ request('estado') === '0' ? 'selected' : '' }}>Pendiente</option>
        <option value="1" {{ request('estado') === '1' ? 'selected' : '' }}>Finalizado</option>
    </select>

    <input type="date" name="fecha" value="{{ request('fecha') }}"
        class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">

    <button type="submit" class="bg-red-600 text-white rounded-lg px-5 py-2 text-sm hover:bg-red-700">Filtrar</button>
    <a href="{{ route('admin.pedidos') }}" class="text-sm text-gray-500 hover:underline self-center">Limpiar</a>
</form>

<div class="space-y-4">
    @include('admin.partials.pedidos', compact('pedidos', 'factventas', 'pedidosia'))
</div>
@endsection
