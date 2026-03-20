@extends('admin.layout')
@section('title', 'Productos')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Productos</h1>
    <span class="text-sm text-gray-400">{{ $productos->count() }} productos</span>
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 rounded-lg mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2 rounded-lg mb-4">{{ session('error') }}</div>
@endif

{{-- Filtros --}}
<form method="GET" class="flex flex-wrap gap-2 mb-4 items-center">
    <input type="text" name="search" value="{{ request('search') }}"
        placeholder="Buscar..."
        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 w-48">

    <select name="catalogo" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <option value="">En catálogo: todos</option>
        <option value="si" @selected(request('catalogo') === 'si')>En catálogo: sí</option>
        <option value="no" @selected(request('catalogo') === 'no')>En catálogo: no</option>
    </select>

    <select name="disponible" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <option value="">Visible: todos</option>
        <option value="si" @selected(request('disponible') === 'si')>Visible al cliente: sí</option>
        <option value="no" @selected(request('disponible') === 'no')>Visible al cliente: no</option>
    </select>

    <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-3 py-1.5 rounded-lg">Filtrar</button>
    @if(request('search') || request('catalogo') || request('disponible'))
        <a href="{{ route('admin.productos') }}" class="text-sm text-gray-400 hover:text-red-500">Limpiar</a>
    @endif
</form>

{{-- Lista --}}
<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
            <tr>
                <th class="px-3 py-2 text-left w-12"></th>
                <th class="px-3 py-2 text-left">Producto</th>
                <th class="px-3 py-2 text-right w-28">Precio sistema</th>
                <th class="px-3 py-2 text-right w-32">Precio bot</th>
                <th class="px-3 py-2 text-center w-24">Catálogo</th>
                <th class="px-3 py-2 text-center w-24">Visible</th>
                <th class="px-3 py-2 text-center w-10"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($productos as $producto)
        @php
            $ia         = $producto->iaProducto;
            $enCatalogo = $ia !== null;
            $imgPath    = $ia?->imagen;
            $tieneImg   = $imgPath && file_exists(public_path($imgPath));
            $imgUrl     = $tieneImg ? asset($imgPath) : null;
            $rowId      = 'row-' . $producto->cod;
        @endphp
        {{-- Fila principal --}}
        <tr class="{{ !$enCatalogo ? 'opacity-60' : '' }}">
            <td class="px-3 py-2">
                @if($imgUrl)
                    <img src="{{ $imgUrl }}" alt="" class="w-9 h-9 object-cover rounded-lg">
                @else
                    <div class="w-9 h-9 bg-gray-100 rounded-lg flex items-center justify-center text-gray-300 text-lg">🥩</div>
                @endif
            </td>
            <td class="px-3 py-2">
                <p class="font-medium text-gray-800 leading-tight">{{ $producto->des }}</p>
                <p class="text-xs text-gray-400">{{ $producto->desgrupo }} · {{ $producto->tipo === 'Unidad' ? 'por unidad' : 'por kg' }}</p>
            </td>
            <td class="px-3 py-2 text-right text-gray-500 tabular-nums">
                ${{ number_format($producto->PRE, 2, ',', '.') }}
            </td>
            <td class="px-3 py-2 text-right tabular-nums">
                @if($enCatalogo)
                    <div class="flex items-center justify-end gap-1">
                        <span class="text-gray-400 text-xs">$</span>
                        <input type="number" step="0.01" min="0"
                            value="{{ number_format($ia->precio, 2, '.', '') }}"
                            data-url="{{ route('admin.productos.precio', $producto->cod) }}"
                            class="precio-input w-24 border border-gray-200 rounded px-2 py-0.5 text-right text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                        <span class="precio-status text-green-500 text-xs hidden">✓</span>
                    </div>
                @else
                    <span class="text-gray-300">—</span>
                @endif
            </td>
            <td class="px-3 py-2 text-center">
                @if($enCatalogo)
                    <button onclick="quitarCatalogo('{{ $producto->cod }}', this)"
                        class="inline-flex items-center gap-1 text-xs bg-green-100 text-green-700 hover:bg-red-50 hover:text-red-600 px-2 py-0.5 rounded-full transition">
                        ✓ Sí
                    </button>
                @else
                    <button onclick="agregarCatalogo('{{ $producto->cod }}', this)"
                        class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-500 hover:bg-green-50 hover:text-green-600 px-2 py-0.5 rounded-full transition">
                        + Agregar
                    </button>
                @endif
            </td>
            <td class="px-3 py-2 text-center">
                @if($enCatalogo)
                    <button onclick="toggleDisponible('{{ $producto->cod }}', this)"
                        data-disponible="{{ $ia->disponible ? '1' : '0' }}"
                        class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full transition
                            {{ $ia->disponible ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400' }}">
                        {{ $ia->disponible ? '👁 Visible' : '🚫 Oculto' }}
                    </button>
                @else
                    <span class="text-gray-300 text-xs">—</span>
                @endif
            </td>
            @if($enCatalogo)
            <td class="px-3 py-2 text-center">
                <button type="button" onclick="toggleDetalle('{{ $rowId }}')"
                    class="text-gray-400 hover:text-red-500 text-sm px-2 py-1 rounded hover:bg-gray-100 transition">
                    ✏️
                </button>
            </td>
            @else
            <td></td>
            @endif
        </tr>

        {{-- Fila de detalle (colapsable) --}}
        @if($enCatalogo)
        <tr id="{{ $rowId }}" class="hidden bg-gray-50">
            <td colspan="7" class="px-4 py-3">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">

                    {{-- Descripción --}}
                    <div class="relative sm:col-span-1">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Descripción (visible al cliente)</label>
                        <textarea
                            data-url="{{ route('admin.productos.descripcion', $producto->cod) }}"
                            placeholder="Ej: corte tierno ideal para asado..."
                            maxlength="500" rows="2"
                            class="desc-input w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 resize-none focus:outline-none focus:ring-2 focus:ring-red-300 placeholder-gray-300"
                        >{{ $ia->descripcion }}</textarea>
                        <span class="desc-status absolute top-6 right-1.5 text-xs text-green-500 hidden">✓</span>
                    </div>

                    {{-- Notas IA --}}
                    <div class="relative sm:col-span-1">
                        <label class="block text-xs font-medium text-purple-500 mb-1">Notas para la IA 🤖</label>
                        <textarea
                            data-url="{{ route('admin.productos.notas_ia', $producto->cod) }}"
                            placeholder="Ej: precio fijo por unidad, no multiplicar por kg..."
                            maxlength="500" rows="2"
                            class="ia-input w-full text-xs border border-purple-200 rounded-lg px-2 py-1.5 resize-none focus:outline-none focus:ring-2 focus:ring-purple-300 bg-purple-50 placeholder-gray-300"
                        >{{ $ia->notas_ia }}</textarea>
                        <span class="ia-status absolute top-6 right-1.5 text-xs text-green-500 hidden">✓</span>
                    </div>

                    {{-- Imagen --}}
                    <div class="sm:col-span-1 flex flex-col gap-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Imagen</label>
                        <div class="flex items-start gap-3">
                            @if($imgUrl)
                                <img src="{{ $imgUrl }}" class="w-16 h-16 object-cover rounded-lg border border-gray-200">
                                <form method="POST" action="{{ route('admin.productos.imagen.delete', $producto->cod) }}"
                                      onsubmit="return confirm('¿Eliminar imagen?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-500 hover:underline">Eliminar</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.productos.imagen', $producto->cod) }}"
                                  enctype="multipart/form-data">
                                @csrf
                                <label class="cursor-pointer text-xs border border-dashed border-gray-300 hover:border-red-400 hover:text-red-500 text-gray-400 rounded-lg px-3 py-1.5 transition block text-center">
                                    <input type="file" name="imagen" accept="image/*" class="hidden" onchange="this.form.submit()">
                                    📷 {{ $imgUrl ? 'Cambiar' : 'Subir imagen' }}
                                </label>
                            </form>
                        </div>
                    </div>

                </div>
            </td>
        </tr>
        @endif

        @empty
        <tr>
            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">No hay productos con esos filtros.</td>
        </tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name=csrf-token]').content;

function toggleDetalle(id) {
    const row = document.getElementById(id);
    if (row) row.classList.toggle('hidden');
}

async function agregarCatalogo(cod, btn) {
    btn.disabled = true;
    btn.textContent = '...';
    const res = await fetch(`/admin/productos/${cod}/catalogo`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    });
    if (res.ok) window.location.reload();
    else { btn.disabled = false; btn.textContent = '+ Agregar'; }
}

async function quitarCatalogo(cod, btn) {
    if (!confirm('¿Quitar del catálogo del bot?')) return;
    btn.disabled = true;
    const res = await fetch(`/admin/productos/${cod}/catalogo`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    });
    if (res.ok) window.location.reload();
    else btn.disabled = false;
}

async function toggleDisponible(cod, btn) {
    btn.disabled = true;
    const res = await fetch(`/admin/productos/${cod}/disponible`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    });
    if (res.ok) {
        const data = await res.json();
        const disp = data.disponible;
        btn.dataset.disponible = disp ? '1' : '0';
        btn.textContent = disp ? '👁 Visible' : '🚫 Oculto';
        btn.className = `inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full transition ${disp ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400'}`;
    }
    btn.disabled = false;
}

function autoSaveTextarea(selector, statusSelector, bodyKey) {
    document.querySelectorAll(selector).forEach(textarea => {
        let original = textarea.value;
        textarea.addEventListener('blur', async () => {
            if (textarea.value === original) return;
            const status = textarea.parentElement.querySelector(statusSelector);
            try {
                await fetch(textarea.dataset.url, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ [bodyKey]: textarea.value }),
                });
                original = textarea.value;
                if (status) { status.classList.remove('hidden'); setTimeout(() => status.classList.add('hidden'), 2000); }
            } catch (_) {}
        });
    });
}

autoSaveTextarea('.desc-input', '.desc-status', 'descripcion');
autoSaveTextarea('.ia-input',   '.ia-status',   'notas_ia');

document.querySelectorAll('.precio-input').forEach(input => {
    let original = input.value;
    input.addEventListener('blur', async () => {
        if (input.value === original) return;
        const status = input.parentElement.querySelector('.precio-status');
        try {
            await fetch(input.dataset.url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ precio: input.value }),
            });
            original = input.value;
            if (status) { status.classList.remove('hidden'); setTimeout(() => status.classList.add('hidden'), 2000); }
        } catch (_) {}
    });
});
</script>
@endsection
