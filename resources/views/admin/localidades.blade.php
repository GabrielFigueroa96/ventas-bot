@extends('admin.layout')
@section('title', 'Localidades')

@section('content')
@php $diasLabel = \App\Models\Empresa::DIAS_LABEL; @endphp
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-800">Localidades de reparto</h1>
        <button onclick="document.getElementById('form-nueva').classList.toggle('hidden')"
            class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nueva</button>
    </div>

    @if(session('ok'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">{{ session('ok') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">{{ $errors->first() }}</div>
    @endif

    {{-- Formulario nueva --}}
    <div id="form-nueva" class="hidden bg-white rounded-xl shadow p-5">
        <h2 class="font-semibold text-gray-700 mb-4">Nueva localidad</h2>
        @include('admin.partials.localidad-form', ['action' => route('admin.localidades.store'), 'method' => 'POST', 'loc' => null])
    </div>

    {{-- Lista --}}
    <div class="space-y-3">
        @forelse($localidades as $loc)
        <div class="bg-white rounded-xl shadow" x-data="{ editando: false }">
            <div class="flex items-center justify-between px-5 py-4 gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-gray-800">{{ $loc->nombre }}</span>
                        <span class="text-xs text-gray-400">{{ $loc->diasTexto() }}</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $loc->clientes()->count() }} cliente(s) vinculados</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button onclick="toggleLoc({{ $loc->id }}, this)"
                        data-activo="{{ $loc->activo ? '1' : '0' }}"
                        class="text-xs px-3 py-1 rounded-full font-medium {{ $loc->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $loc->activo ? 'Activa' : 'Inactiva' }}
                    </button>
                    <button onclick="this.closest('.bg-white').querySelector('.edit-form').classList.toggle('hidden')"
                        class="text-xs text-blue-600 hover:underline">Editar</button>
                    <form method="POST" action="{{ route('admin.localidades.destroy', $loc) }}"
                        onsubmit="return confirm('¿Eliminar {{ $loc->nombre }}?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-500 hover:underline">Eliminar</button>
                    </form>
                </div>
            </div>
            <div class="edit-form hidden border-t px-5 py-4">
                @include('admin.partials.localidad-form', [
                    'action' => route('admin.localidades.update', $loc),
                    'method' => 'PUT',
                    'loc'    => $loc,
                ])
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl shadow px-5 py-6 text-center text-gray-400 text-sm">
            No hay localidades. Creá la primera con "+ Nueva".
        </div>
        @endforelse
    </div>
</div>
@endsection

@section('scripts')
<script>
function toggleLoc(id, btn) {
    fetch(`/admin/localidades/${id}/toggle`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(data => {
        btn.dataset.activo = data.activo ? '1' : '0';
        btn.textContent    = data.activo ? 'Activa' : 'Inactiva';
        btn.className = 'text-xs px-3 py-1 rounded-full font-medium ' +
            (data.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500');
    });
}
</script>
@endsection
