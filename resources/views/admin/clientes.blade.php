@extends('admin.layout')
@section('title', 'Clientes')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Clientes</h1>
    <span class="text-sm text-gray-500">{{ $clientes->total() }} registrados</span>
</div>

{{-- Buscador --}}
<form method="GET" class="mb-4">
    <input type="text" name="search" value="{{ request('search') }}"
        placeholder="Buscar por nombre o teléfono..."
        class="w-full md:w-80 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
</form>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-5 py-3 text-left">Nombre</th>
                <th class="px-5 py-3 text-left">Teléfono</th>
                <th class="px-5 py-3 text-left">Estado</th>
                <th class="px-5 py-3 text-left">Mensajes</th>
                <th class="px-5 py-3 text-left">Desde</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($clientes as $c)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium text-gray-800">{{ $c->name ?? '—' }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $c->phone }}</td>
                <td class="px-5 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $c->estado === 'activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $c->estado ?? '—' }}
                    </span>
                </td>
                <td class="px-5 py-3 text-gray-600">{{ $c->messages_count }}</td>
                <td class="px-5 py-3 text-gray-400">{{ $c->created_at->format('d/m/Y') }}</td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('admin.cliente', $c) }}" class="text-red-600 hover:underline text-xs">Ver detalle</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-5 py-6 text-center text-gray-400">No hay clientes.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $clientes->withQueryString()->links() }}</div>
@endsection
