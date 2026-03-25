@extends('admin.layout')
@section('title', 'Clientes')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Clientes</h1>
    <div class="flex items-center gap-3">
        <span class="text-sm text-gray-500">{{ $clientes->total() }} registrados</span>
        <button onclick="document.getElementById('modal-nuevo').classList.remove('hidden')"
            class="flex items-center gap-1.5 bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Nuevo cliente
        </button>
    </div>
</div>

{{-- Modal nuevo cliente --}}
<div id="modal-nuevo" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-bold text-gray-800">Nuevo cliente</h2>
            <button onclick="document.getElementById('modal-nuevo').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route('admin.clientes.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Teléfono <span class="text-red-600">*</span></label>
                <input type="text" name="phone" value="{{ old('phone') }}" required
                    placeholder="Ej: 5493415550000"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                <p class="text-xs text-gray-400 mt-1">Con código de país, sin + ni espacios.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Nombre</label>
                <input type="text" name="name" value="{{ old('name') }}"
                    placeholder="Nombre del cliente"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
            </div>

            {{-- Dirección --}}
            <div class="grid grid-cols-3 gap-2">
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Calle</label>
                    <input type="text" name="calle" value="{{ old('calle') }}"
                        placeholder="Ej: San Martín"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Número</label>
                    <input type="text" name="numero" value="{{ old('numero') }}"
                        placeholder="123"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Piso / Depto / Referencia</label>
                <input type="text" name="dato_extra" value="{{ old('dato_extra') }}"
                    placeholder="Ej: Piso 2 Dto B"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Localidad</label>
                @if($localidades->isNotEmpty())
                    <select name="localidad_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                        <option value="">— Sin localidad —</option>
                        @foreach($localidades as $loc)
                            <option value="{{ $loc->id }}" {{ old('localidad_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->nombre }}@if($loc->provincia) ({{ $loc->provincia }})@endif
                            </option>
                        @endforeach
                    </select>
                @else
                    <input type="text" name="localidad" value="{{ old('localidad') }}"
                        placeholder="Ej: Rosario"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                    <input type="text" name="provincia" value="{{ old('provincia') }}"
                        placeholder="Provincia"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-2 focus:outline-none focus:ring-2 focus:ring-red-300">
                @endif
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit"
                    class="flex-1 bg-red-700 hover:bg-red-800 text-white text-sm font-semibold py-2.5 rounded-lg transition">
                    Crear cliente
                </button>
                <button type="button" onclick="document.getElementById('modal-nuevo').classList.add('hidden')"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold py-2.5 rounded-lg transition">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Buscador --}}
<form method="GET" class="mb-4">
    <input type="text" name="search" value="{{ request('search') }}"
        placeholder="Buscar por nombre o teléfono..."
        class="w-full md:w-80 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
</form>

{{-- Mobile: cards --}}
<div class="sm:hidden space-y-3">
    @forelse($clientes as $c)
    <a href="{{ route('admin.cliente', $c) }}" class="block bg-white rounded-xl shadow px-4 py-3">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="font-semibold text-gray-800 truncate">{{ $c->name ?? '—' }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $c->phone }}</p>
                @if($c->cuenta)
                    <p class="text-xs text-blue-600 mt-0.5">{{ $c->cuenta->nom }} <span class="text-gray-400">#{{ $c->cuenta->cod }}</span></p>
                @endif
            </div>
            <div class="flex flex-col items-end gap-1 shrink-0">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                    {{ $c->estado === 'activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $c->estado ?? '—' }}
                </span>
                @if($c->modo === 'humano')
                <span class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-50 text-orange-600">
                    <span class="w-1.5 h-1.5 bg-orange-500 rounded-full animate-pulse"></span>
                    Manual
                </span>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
            <span>💬 {{ $c->messages_count }}</span>
            <span>Desde {{ $c->created_at->format('d/m/Y') }}</span>
        </div>
    </a>
    @empty
    <p class="text-center text-gray-400 text-sm py-6">No hay clientes.</p>
    @endforelse
</div>

{{-- Desktop: tabla --}}
<div class="hidden sm:block bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-100">
            <tr>
                <th class="px-5 py-3 text-left">Nombre</th>
                <th class="px-5 py-3 text-left">Cuenta</th>
                <th class="px-5 py-3 text-left">Teléfono</th>
                <th class="px-5 py-3 text-left">Estado</th>
                <th class="px-5 py-3 text-left">Mensajes</th>
                <th class="px-5 py-3 text-left">Desde</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($clientes as $c)
            <tr class="hover:bg-red-50/40 cursor-pointer transition-colors duration-100"
                onclick="window.location='{{ route('admin.cliente', $c) }}'">
                <td class="px-5 py-3 font-medium text-gray-800">{{ $c->name ?? '—' }}</td>
                <td class="px-5 py-3">
                    @if($c->cuenta)
                        <span class="text-gray-700 font-medium">{{ $c->cuenta->nom }}</span>
                        <span class="text-gray-400 text-xs ml-1">#{{ $c->cuenta->cod }}</span>
                    @else
                        <span class="text-gray-300 text-xs">Sin vincular</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-gray-600">{{ $c->phone }}</td>
                <td class="px-5 py-3">
                    <div class="flex flex-wrap items-center gap-1">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $c->estado === 'activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $c->estado ?? '—' }}
                        </span>
                        @if($c->modo === 'humano')
                        <span class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-50 text-orange-600">
                            <span class="w-1.5 h-1.5 bg-orange-500 rounded-full animate-pulse"></span>
                            Manual
                        </span>
                        @endif
                    </div>
                </td>
                <td class="px-5 py-3 text-gray-600">{{ $c->messages_count }}</td>
                <td class="px-5 py-3 text-gray-400">{{ $c->created_at->format('d/m/Y') }}</td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('admin.cliente', $c) }}" class="text-red-600 hover:underline text-xs">Ver detalle</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-5 py-6 text-center text-gray-400">No hay clientes.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $clientes->withQueryString()->links() }}</div>

@if($errors->any() || old('phone'))
<script>document.getElementById('modal-nuevo').classList.remove('hidden');</script>
@endif
@endsection
