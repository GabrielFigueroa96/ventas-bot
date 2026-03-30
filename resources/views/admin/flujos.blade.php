@extends('admin.layout')
@section('title', 'Flujos del Bot')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-lg font-bold text-gray-800">Flujos del Bot</h1>
        <a href="{{ route('admin.flujos.crear') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            + Nuevo flujo
        </a>
    </div>

    @if($flujos->isEmpty())
    <div class="bg-white border border-gray-200 rounded-xl p-10 text-center text-gray-400 text-sm">
        No hay flujos creados. <a href="{{ route('admin.flujos.crear') }}" class="text-blue-600 underline">Crear el primero</a>.
    </div>
    @else
    <div class="grid gap-3">
        @foreach($flujos as $flujo)
        <div class="bg-white border border-gray-200 rounded-xl px-4 py-3 flex items-center gap-4">
            {{-- Estado activo --}}
            <button onclick="toggleActivo({{ $flujo->id }}, this)"
                class="shrink-0 w-10 h-6 rounded-full transition-colors {{ $flujo->activo ? 'bg-green-500' : 'bg-gray-300' }} relative">
                <span class="absolute top-0.5 {{ $flujo->activo ? 'right-0.5' : 'left-0.5' }} w-5 h-5 bg-white rounded-full shadow transition-all"></span>
            </button>

            <div class="flex-1 min-w-0">
                <div class="font-semibold text-gray-800 text-sm">{{ $flujo->nombre }}</div>
                <div class="text-xs text-gray-400">
                    {{ $flujo->activo ? '🟢 Activo' : '⚪ Inactivo' }}
                    · Modificado {{ $flujo->updated_at->diffForHumans() }}
                    @if($flujo->definicion)
                        · {{ count($flujo->definicion['drawflow']['Home']['data'] ?? []) }} nodos
                    @endif
                </div>
            </div>

            <div class="flex gap-2 shrink-0">
                <a href="{{ route('admin.flujos.editar', $flujo) }}"
                   class="text-xs border border-blue-200 text-blue-600 hover:bg-blue-50 px-3 py-1.5 rounded-lg transition">
                    Editar
                </a>
                <button onclick="eliminar({{ $flujo->id }})"
                    class="text-xs border border-red-200 text-red-500 hover:bg-red-50 px-3 py-1.5 rounded-lg transition">
                    Eliminar
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
const csrf = '{{ csrf_token() }}';

async function toggleActivo(id, btn) {
    const res = await fetch(`/admin/flujos/${id}/activar`, {
        method: 'PATCH', headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json'}
    });
    const d = await res.json();
    // Reload to reflect all toggles
    location.reload();
}

async function eliminar(id) {
    if (!confirm('¿Eliminar este flujo?')) return;
    await fetch(`/admin/flujos/${id}`, {
        method: 'DELETE', headers: {'X-CSRF-TOKEN': csrf}
    });
    location.reload();
}
</script>
@endsection
