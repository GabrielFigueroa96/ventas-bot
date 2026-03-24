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
        @foreach(\App\Models\Pedidosia::ESTADOS as $val => $info)
            <option value="{{ $val }}" {{ request('estado') == $val ? 'selected' : '' }}>{{ $info['label'] }}</option>
        @endforeach
    </select>

    <input type="date" name="fecha" value="{{ $fecha }}"
        class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">

    <button type="submit" class="bg-red-600 text-white rounded-lg px-5 py-2 text-sm hover:bg-red-700">Filtrar</button>
    <a href="{{ route('admin.pedidos') }}" class="text-sm text-gray-500 hover:underline self-center">Limpiar</a>
</form>

<div class="space-y-4">
    @include('admin.partials.pedidos', compact('pedidos', 'factventas', 'pedidosia', 'vmayo'))
</div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name=csrf-token]').content;

async function avanzarEstado(id, btn) {
    const max = parseInt(btn.dataset.max ?? 4);
    btn.disabled = true;
    btn.textContent = '...';
    try {
        const res  = await fetch(`/admin/pedidos/ia/${id}/estado`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) { alert(data.error ?? 'Error'); btn.disabled = false; btn.textContent = '›'; return; }

        const badge = document.getElementById(`badge-sia-${id}`);
        if (badge) {
            badge.textContent = data.label;
            badge.className   = `text-xs px-2 py-0.5 rounded-full font-medium ${data.css}`;
        }
        // Si avanzó desde pendiente, quitar el botón cancelar
        const cancelBtn = document.getElementById(`cancel-sia-${id}`);
        if (cancelBtn) cancelBtn.remove();

        if (data.estado >= max) {
            btn.remove();
        } else {
            btn.disabled    = false;
            btn.textContent = '›';
        }
    } catch (e) {
        btn.disabled = false; btn.textContent = '›';
    }
}

async function cancelarPedido(id, btn) {
    if (!confirm('¿Cancelar este pedido?')) return;
    btn.disabled = true;
    btn.textContent = '...';
    try {
        const res  = await fetch(`/admin/pedidos/ia/${id}/cancelar`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) { alert(data.error ?? 'Error'); btn.disabled = false; btn.textContent = '✕'; return; }

        const badge = document.getElementById(`badge-sia-${id}`);
        if (badge) {
            badge.textContent = data.label;
            badge.className   = `text-xs px-2 py-0.5 rounded-full font-medium ${data.css}`;
        }
        // Quitar ambos botones
        btn.remove();
        const avanzarBtn = document.querySelector(`[onclick="avanzarEstado(${id}, this)"]`);
        if (avanzarBtn) avanzarBtn.remove();
    } catch (e) {
        btn.disabled = false; btn.textContent = '✕';
    }
}
</script>
@endsection
