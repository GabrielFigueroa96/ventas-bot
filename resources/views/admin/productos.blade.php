@extends('admin.layout')
@section('title', 'Productos')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">Productos</h1>
    <span class="text-sm text-gray-400">{{ $productos->count() }} productos</span>
</div>

{{-- Filtros --}}
<form method="GET" data-no-loading class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-4">
    <div class="flex flex-col sm:flex-row flex-wrap gap-2">
        <input type="text" name="search" value="{{ request('search') }}"
            placeholder="Buscar producto..."
            class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 w-full sm:w-48">

        <select name="catalogo" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 w-full sm:w-auto">
            <option value="">Catálogo: todos</option>
            <option value="si" @selected(request('catalogo') === 'si')>En catálogo: sí</option>
            <option value="no" @selected(request('catalogo') === 'no')>En catálogo: no</option>
        </select>

        <select name="disponible" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 w-full sm:w-auto">
            <option value="">Visible: todos</option>
            <option value="si" @selected(request('disponible') === 'si')>Visible: sí</option>
            <option value="no" @selected(request('disponible') === 'no')>Visible: no</option>
        </select>

        <div class="flex gap-2 sm:ml-auto">
            <button type="submit"
                class="flex-1 sm:flex-none bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                Filtrar
            </button>
            @if(request('search') || request('catalogo') || request('disponible'))
            <a href="{{ route('admin.productos') }}"
                class="flex-1 sm:flex-none text-center border border-gray-200 hover:bg-gray-50 text-gray-500 text-sm px-4 py-2 rounded-lg transition-colors">
                Limpiar
            </a>
            @endif
        </div>
    </div>
</form>

{{-- ══ MOBILE: Cards ══════════════════════════════════════════════════════════ --}}
<div class="sm:hidden space-y-2 mb-4">
    @forelse($productos as $producto)
    @php
        $ia         = $producto->iaProducto;
        $enCatalogo = $ia !== null;
        $imgPath    = $ia?->imagen;
        $imgUrl     = ($imgPath && file_exists(public_path($imgPath))) ? asset($imgPath) . '?v=' . ($ia?->updated_at?->timestamp ?? 0) : null;
        $cod        = $producto->cod;
    @endphp

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden {{ !$enCatalogo ? 'opacity-60' : '' }}">

        {{-- Fila principal --}}
        <div class="flex items-center gap-3 px-3 py-3">
            @if($imgUrl)
                <img src="{{ $imgUrl }}" class="w-11 h-11 object-cover rounded-lg shrink-0">
            @else
                <div class="w-11 h-11 bg-gray-100 rounded-lg flex items-center justify-center text-xl shrink-0">🥩</div>
            @endif

            <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-800 text-sm leading-tight truncate">{{ $producto->des }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $producto->desgrupo }} · {{ $producto->tipo === 'Unidad' ? 'por unidad' : 'por kg' }}</p>
            </div>

            <div class="flex items-center gap-1 shrink-0">
                @if($enCatalogo)
                    <button onclick="quitarCatalogo('{{ $cod }}', this)"
                        class="text-xs bg-green-100 text-green-700 hover:bg-red-50 hover:text-red-600 px-2.5 py-1 rounded-full transition-colors font-medium">
                        ✓ Cat.
                    </button>
                    <button type="button" onclick="toggleDetalle('{{ $cod }}')"
                        class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-gray-100 rounded-lg transition-colors">
                        ✏️
                    </button>
                @else
                    <button onclick="agregarCatalogo('{{ $cod }}', this)"
                        class="text-xs bg-gray-100 text-gray-500 hover:bg-green-50 hover:text-green-600 px-2.5 py-1 rounded-full transition-colors font-medium">
                        + Cat.
                    </button>
                @endif
            </div>
        </div>

        {{-- Precios --}}
        <div class="flex items-center gap-3 px-3 pb-3 border-t border-gray-50 pt-2">
            <span class="text-xs text-gray-400">Sistema: <span class="font-medium text-gray-600 tabular-nums">${{ number_format($producto->PRE, 2, ',', '.') }}</span></span>
            @if($enCatalogo)
            <div class="flex items-center gap-1 text-xs">
                <span class="text-gray-400">Bot: $</span>
                <input type="number" step="0.01" min="0"
                    value="{{ number_format($ia->precio, 2, '.', '') }}"
                    data-url="{{ route('admin.productos.precio', $cod) }}"
                    class="precio-input w-20 border border-gray-200 rounded px-2 py-0.5 text-right text-xs tabular-nums focus:outline-none focus:ring-2 focus:ring-red-300">
                <span class="precio-status text-green-500 hidden">✓</span>
            </div>
            <button onclick="toggleDisponible('{{ $cod }}', this)"
                data-disponible="{{ $ia->disponible ? '1' : '0' }}"
                class="ml-auto text-xs px-2.5 py-1 rounded-full transition-colors font-medium
                    {{ $ia->disponible ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400' }}">
                {{ $ia->disponible ? '👁 Visible' : '🚫 Oculto' }}
            </button>
            @endif
        </div>

        {{-- Panel colapsable --}}
        @if($enCatalogo)
        <div id="mob-row-{{ $cod }}" class="hidden border-t border-gray-100 bg-gray-50 px-3 py-3 space-y-3">
            <div class="relative">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-medium text-gray-500">Descripción (visible al cliente)</label>
                    <button type="button" onclick="sugerirDescripcion('{{ $cod }}', this)"
                        class="flex items-center gap-1 text-xs text-purple-600 border border-purple-200 bg-purple-50 px-2 py-0.5 rounded-full transition-colors">
                        🤖 Sugerir
                    </button>
                </div>
                <textarea data-url="{{ route('admin.productos.descripcion', $cod) }}" data-cod="{{ $cod }}"
                    maxlength="500" rows="2" placeholder="Ej: corte tierno ideal para asado..."
                    class="desc-input w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 resize-none focus:outline-none focus:ring-2 focus:ring-red-300 placeholder-gray-300"
                >{{ $ia->descripcion }}</textarea>
                <span class="desc-status absolute bottom-1.5 right-1.5 text-xs text-green-500 hidden">✓</span>
            </div>

            <div class="relative">
                <label class="block text-xs font-medium text-purple-500 mb-1">Notas para la IA 🤖</label>
                <textarea data-url="{{ route('admin.productos.notas_ia', $cod) }}"
                    maxlength="500" rows="2" placeholder="Ej: precio fijo por unidad..."
                    class="ia-input w-full text-xs border border-purple-200 rounded-lg px-2 py-1.5 resize-none focus:outline-none focus:ring-2 focus:ring-purple-300 bg-purple-50 placeholder-gray-300"
                >{{ $ia->notas_ia }}</textarea>
                <span class="ia-status absolute top-6 right-1.5 text-xs text-green-500 hidden">✓</span>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-2">Imagen</label>
                <div class="flex items-center gap-3">
                    @if($imgUrl)
                        <img src="{{ $imgUrl }}" class="w-12 h-12 object-cover rounded-lg border border-gray-200">
                        <form method="POST" action="{{ route('admin.productos.imagen.delete', $cod) }}"
                              onsubmit="return confirm('¿Eliminar imagen?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-500 hover:underline">Eliminar</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.productos.imagen', $cod) }}" enctype="multipart/form-data">
                        @csrf
                        <label class="cursor-pointer text-xs border border-dashed border-gray-300 hover:border-red-400 hover:text-red-500 text-gray-400 rounded-lg px-3 py-1.5 transition-colors block text-center">
                            <input type="file" name="imagen" accept="image/*" class="hidden" onchange="this.form.submit()">
                            📷 {{ $imgUrl ? 'Cambiar' : 'Subir imagen' }}
                        </label>
                    </form>
                </div>
            </div>
        </div>
        @endif

    </div>
    @empty
    <p class="text-center text-gray-400 text-sm py-8">No hay productos con esos filtros.</p>
    @endforelse
</div>

{{-- ══ DESKTOP: Tabla ══════════════════════════════════════════════════════════ --}}
<div class="hidden sm:block bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
            <tr>
                <th class="px-3 py-3 text-left w-12"></th>
                <th class="px-3 py-3 text-left">Producto</th>
                <th class="px-3 py-3 text-right w-28">Precio sistema</th>
                <th class="px-3 py-3 text-right w-32">Precio bot</th>
                <th class="px-3 py-3 text-center w-24">Catálogo</th>
                <th class="px-3 py-3 text-center w-24">Visible</th>
                <th class="px-3 py-3 w-10"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($productos as $producto)
        @php
            $ia         = $producto->iaProducto;
            $enCatalogo = $ia !== null;
            $imgPath    = $ia?->imagen;
            $imgUrl     = ($imgPath && file_exists(public_path($imgPath))) ? asset($imgPath) : null;
            $cod        = $producto->cod;
        @endphp
        <tr class="hover:bg-gray-50/60 transition-colors {{ !$enCatalogo ? 'opacity-60' : '' }}">
            <td class="px-3 py-2">
                @if($imgUrl)
                    <img src="{{ $imgUrl }}" class="w-9 h-9 object-cover rounded-lg">
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
                            data-url="{{ route('admin.productos.precio', $cod) }}"
                            class="precio-input w-24 border border-gray-200 rounded px-2 py-0.5 text-right text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                        <span class="precio-status text-green-500 text-xs hidden">✓</span>
                    </div>
                @else
                    <span class="text-gray-300">—</span>
                @endif
            </td>
            <td class="px-3 py-2 text-center">
                @if($enCatalogo)
                    <button onclick="quitarCatalogo('{{ $cod }}', this)"
                        class="inline-flex items-center gap-1 text-xs bg-green-100 text-green-700 hover:bg-red-50 hover:text-red-600 px-2 py-0.5 rounded-full transition-colors">
                        ✓ Sí
                    </button>
                @else
                    <button onclick="agregarCatalogo('{{ $cod }}', this)"
                        class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-500 hover:bg-green-50 hover:text-green-600 px-2 py-0.5 rounded-full transition-colors">
                        + Agregar
                    </button>
                @endif
            </td>
            <td class="px-3 py-2 text-center">
                @if($enCatalogo)
                    <button onclick="toggleDisponible('{{ $cod }}', this)"
                        data-disponible="{{ $ia->disponible ? '1' : '0' }}"
                        class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full transition-colors
                            {{ $ia->disponible ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400' }}">
                        {{ $ia->disponible ? '👁 Visible' : '🚫 Oculto' }}
                    </button>
                @else
                    <span class="text-gray-300 text-xs">—</span>
                @endif
            </td>
            <td class="px-3 py-2 text-center">
                @if($enCatalogo)
                <button type="button" onclick="toggleDetalle('{{ $cod }}')"
                    class="text-gray-400 hover:text-red-500 text-sm px-2 py-1 rounded hover:bg-gray-100 transition-colors">
                    ✏️
                </button>
                @endif
            </td>
        </tr>

        {{-- Fila detalle colapsable (desktop) --}}
        @if($enCatalogo)
        <tr id="row-{{ $cod }}" class="hidden bg-gray-50">
            <td colspan="7" class="px-4 py-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="relative">
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-medium text-gray-500">Descripción (visible al cliente)</label>
                            <button type="button" onclick="sugerirDescripcion('{{ $cod }}', this)"
                                class="flex items-center gap-1 text-xs text-purple-600 hover:text-purple-800 border border-purple-200 hover:border-purple-400 bg-purple-50 hover:bg-purple-100 px-2 py-0.5 rounded-full transition-colors">
                                🤖 Sugerir
                            </button>
                        </div>
                        <textarea data-url="{{ route('admin.productos.descripcion', $cod) }}" data-cod="{{ $cod }}"
                            maxlength="500" rows="2" placeholder="Ej: corte tierno ideal para asado..."
                            class="desc-input w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 resize-none focus:outline-none focus:ring-2 focus:ring-red-300 placeholder-gray-300"
                        >{{ $ia->descripcion }}</textarea>
                        <span class="desc-status absolute bottom-1.5 right-1.5 text-xs text-green-500 hidden">✓</span>
                    </div>

                    <div class="relative">
                        <label class="block text-xs font-medium text-purple-500 mb-1">Notas para la IA 🤖</label>
                        <textarea data-url="{{ route('admin.productos.notas_ia', $cod) }}"
                            maxlength="500" rows="2" placeholder="Ej: precio fijo por unidad, no multiplicar por kg..."
                            class="ia-input w-full text-xs border border-purple-200 rounded-lg px-2 py-1.5 resize-none focus:outline-none focus:ring-2 focus:ring-purple-300 bg-purple-50 placeholder-gray-300"
                        >{{ $ia->notas_ia }}</textarea>
                        <span class="ia-status absolute top-6 right-1.5 text-xs text-green-500 hidden">✓</span>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="block text-xs font-medium text-gray-500">Imagen</label>
                        <div class="flex items-start gap-3">
                            @if($imgUrl)
                                <img src="{{ $imgUrl }}" class="w-16 h-16 object-cover rounded-lg border border-gray-200">
                                <form method="POST" action="{{ route('admin.productos.imagen.delete', $cod) }}"
                                      onsubmit="return confirm('¿Eliminar imagen?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-500 hover:underline">Eliminar</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.productos.imagen', $cod) }}" enctype="multipart/form-data">
                                @csrf
                                <label class="cursor-pointer text-xs border border-dashed border-gray-300 hover:border-red-400 hover:text-red-500 text-gray-400 rounded-lg px-3 py-1.5 transition-colors block text-center">
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

// Abre/cierra el panel de detalle en mobile (mob-row-) y desktop (row-)
function toggleDetalle(cod) {
    ['row-' + cod, 'mob-row-' + cod].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('hidden');
    });
}

async function agregarCatalogo(cod, btn) {
    btn.disabled = true;
    btn.textContent = '...';
    try {
        const res = await fetch(`/admin/productos/${cod}/catalogo`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        if (res.ok) {
            window.location.reload();
        } else {
            showToast('Error al agregar al catálogo', 'error');
            btn.disabled = false; btn.textContent = '+ Agregar';
        }
    } catch (e) {
        showToast('Error de conexión', 'error');
        btn.disabled = false; btn.textContent = '+ Agregar';
    }
}

async function quitarCatalogo(cod, btn) {
    if (!confirm('¿Quitar del catálogo del bot?')) return;
    btn.disabled = true;
    try {
        const res = await fetch(`/admin/productos/${cod}/catalogo`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        if (res.ok) {
            window.location.reload();
        } else {
            showToast('Error al quitar del catálogo', 'error');
            btn.disabled = false;
        }
    } catch (e) {
        showToast('Error de conexión', 'error');
        btn.disabled = false;
    }
}

async function toggleDisponible(cod, btn) {
    btn.disabled = true;
    try {
        const res = await fetch(`/admin/productos/${cod}/disponible`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            const disp = data.disponible;
            // Actualizar todos los botones con este cod (mobile + desktop)
            document.querySelectorAll(`[onclick="toggleDisponible('${cod}', this)"]`).forEach(b => {
                b.dataset.disponible = disp ? '1' : '0';
                b.textContent = disp ? '👁 Visible' : '🚫 Oculto';
                b.className = b.className.replace(/bg-(blue|gray)-\d+\s+text-(blue|gray)-\d+/g, '');
                b.classList.add(...(disp ? ['bg-blue-100','text-blue-700'] : ['bg-gray-100','text-gray-400']));
            });
        }
    } catch (_) {}
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

async function sugerirDescripcion(cod, btn) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Pensando...';
    try {
        const res  = await fetch(`/admin/productos/${cod}/sugerir-descripcion`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!res.ok || data.error) {
            showToast(data.error ?? 'No se pudo obtener la sugerencia', 'error');
            return;
        }
        // Buscar el textarea visible (mobile o desktop)
        const textareas = document.querySelectorAll(`.desc-input[data-cod="${cod}"]`);
        const textarea  = Array.from(textareas).find(t => t.offsetParent !== null) ?? textareas[0];
        if (!textarea) return;

        let panel = document.getElementById(`sugerencia-panel-${cod}`);
        if (!panel) {
            panel = document.createElement('div');
            panel.id = `sugerencia-panel-${cod}`;
            panel.className = 'mt-1 p-2 bg-purple-50 border border-purple-200 rounded-lg text-xs text-purple-800';
            textarea.parentElement.appendChild(panel);
        }
        panel.innerHTML = `
            <p class="font-medium mb-1">Sugerencia IA:</p>
            <p class="italic mb-2">"${data.sugerencia}"</p>
            <div class="flex gap-2">
                <button onclick="aplicarSugerencia('${cod}', this)" data-sugerencia="${data.sugerencia.replace(/"/g, '&quot;')}"
                    class="text-xs bg-purple-600 hover:bg-purple-700 text-white px-2 py-0.5 rounded-full transition">
                    Usar esta descripción
                </button>
                <button onclick="document.getElementById('sugerencia-panel-${cod}').remove()"
                    class="text-xs text-gray-500 hover:text-gray-700 px-2 py-0.5 rounded-full border border-gray-200 hover:bg-gray-50 transition">
                    Descartar
                </button>
            </div>`;
    } catch (e) {
        showToast('Error de conexión', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

async function aplicarSugerencia(cod, btn) {
    const sugerencia = btn.dataset.sugerencia;
    // Actualizar todos los textareas con este cod (mobile + desktop)
    document.querySelectorAll(`.desc-input[data-cod="${cod}"]`).forEach(t => t.value = sugerencia);
    const textarea = document.querySelector(`.desc-input[data-cod="${cod}"]`);
    if (!textarea) return;
    try {
        await fetch(textarea.dataset.url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ descripcion: sugerencia }),
        });
        showToast('Descripción actualizada', 'success');
    } catch (_) {}
    document.getElementById(`sugerencia-panel-${cod}`)?.remove();
}

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
