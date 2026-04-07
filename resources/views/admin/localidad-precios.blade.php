@extends('admin.layout')
@section('title', 'Precios — ' . $localidad->nombre)

@section('content')
@php
    $diasLabelCorto = [0=>'Dom',1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb'];
    $diasLoc = collect($diasLocConfig)->pluck('dia')->toArray(); // días de reparto de esta localidad
    $habilitados = $plConfigs->keys()->map(fn($k) => (float)$k)->toArray();
@endphp

{{-- Header --}}
<div class="flex flex-wrap items-center gap-3 mb-5">
    <a href="{{ route('admin.localidades') }}"
        class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
        ← Localidades
    </a>
    <h1 class="text-xl font-bold text-gray-800">Precios — <span class="text-red-600">{{ $localidad->nombre }}</span></h1>

    {{-- Switcher de localidad --}}
    <div class="ml-auto">
        <select onchange="window.location='/admin/localidades/'+this.value+'/precios'"
            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
            @foreach($todas as $loc)
            <option value="{{ $loc->id }}" @selected($loc->id === $localidad->id)>{{ $loc->nombre }}</option>
            @endforeach
        </select>
    </div>
</div>

{{-- Buscador + acción bulk --}}
<form method="GET" data-no-loading class="mb-4 flex gap-2">
    <input type="text" name="search" value="{{ $search }}"
        placeholder="Buscar producto..."
        class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 flex-1 max-w-xs">
    <button type="submit"
        class="bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2 rounded-lg transition-colors">
        Buscar
    </button>
    @if($search)
    <a href="{{ route('admin.localidades.precios', $localidad->id) }}"
        class="border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm px-4 py-2 rounded-lg transition-colors">
        Limpiar
    </a>
    @endif
</form>

{{-- Leyenda + acción bulk --}}
<div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <p class="text-xs text-gray-400">
        Activá los productos disponibles para esta localidad. Precio vacío = usa el precio base del catálogo.
        @if(!empty($diasLoc)) Los días controlan en qué reparto aparece cada producto. @endif
    </p>
    @if(!empty($diasLoc))
    <button id="btn-reset-dias" onclick="resetearDias()"
        class="text-xs text-indigo-600 border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 px-3 py-1 rounded-full transition-colors whitespace-nowrap">
        Usar todos los días de la localidad
    </button>
    @endif
</div>

{{-- Tabla desktop --}}
<div class="hidden sm:block bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
            <tr>
                <th class="px-3 py-3 text-center w-10">Activo</th>
                <th class="px-3 py-3 text-left">Producto</th>
                <th class="px-3 py-3 text-left w-32">Grupo</th>
                <th class="px-3 py-3 text-right w-28">Precio base</th>
                <th class="px-3 py-3 text-right w-32">Precio localidad</th>
                @if(!empty($diasLoc))
                <th class="px-3 py-3 text-center">Días disponibles</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($productos as $p)
        @php
            $pl      = $plConfigs->get($p->cod);
            $activo  = $pl !== null;
            $rowDias = $activo ? collect($pl->dias_reparto ?? [])->map(fn($d) => is_array($d) ? (int)$d['dia'] : (int)$d)->toArray() : [];
        @endphp
        <tr class="hover:bg-gray-50/60 transition-colors {{ !$activo ? 'opacity-50' : '' }}"
            data-cod="{{ $p->cod }}" data-activo="{{ $activo ? '1' : '0' }}">
            <td class="px-3 py-2 text-center">
                <input type="checkbox" {{ $activo ? 'checked' : '' }}
                    class="toggle-activo w-4 h-4 accent-red-600 cursor-pointer"
                    data-cod="{{ $p->cod }}"
                    data-precio-base="{{ number_format($p->precio, 2, '.', '') }}">
            </td>
            <td class="px-3 py-2">
                <p class="font-medium text-gray-800 leading-tight">{{ $p->des }}</p>
                <p class="text-xs text-gray-400">{{ $p->tipo === 'Unidad' ? 'por unidad' : 'por kg' }}</p>
            </td>
            <td class="px-3 py-2 text-xs text-gray-500">{{ $p->desgrupo ?: '—' }}</td>
            <td class="px-3 py-2 text-right text-gray-400 tabular-nums">
                ${{ number_format($p->precio, 2, ',', '') }}
            </td>
            <td class="px-3 py-2 text-right">
                <div class="flex items-center justify-end gap-1">
                    <span class="text-gray-400 text-xs">$</span>
                    <input type="number" step="0.01" min="0"
                        placeholder="{{ number_format($p->precio, 2, '.', '') }}"
                        value="{{ $activo && $pl->precio !== null ? number_format($pl->precio, 2, '.', '') : '' }}"
                        {{ !$activo ? 'disabled' : '' }}
                        class="precio-input w-24 border border-gray-200 rounded px-2 py-1 text-right tabular-nums text-sm focus:outline-none focus:ring-1 focus:ring-red-300 disabled:opacity-30 disabled:cursor-not-allowed"
                        data-cod="{{ $p->cod }}">
                    <span class="precio-ok text-green-500 text-xs hidden">✓</span>
                </div>
            </td>
            @if(!empty($diasLoc))
            <td class="px-3 py-2 text-center">
                <div class="flex flex-wrap justify-center gap-2 dias-container" data-cod="{{ $p->cod }}">
                    @foreach($diasLoc as $dNum)
                    <label class="flex items-center gap-0.5 text-xs cursor-pointer {{ !$activo ? 'opacity-30 pointer-events-none' : '' }}">
                        <input type="checkbox" value="{{ $dNum }}"
                            {{ (empty($rowDias) || in_array($dNum, $rowDias)) ? 'checked' : '' }}
                            {{ !$activo ? 'disabled' : '' }}
                            class="dia-check accent-blue-600"
                            data-cod="{{ $p->cod }}">
                        <span>{{ $diasLabelCorto[$dNum] ?? $dNum }}</span>
                    </label>
                    @endforeach
                </div>
            </td>
            @endif
        </tr>
        @empty
        <tr>
            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">
                No hay productos en el catálogo.
            </td>
        </tr>
        @endforelse
        </tbody>
    </table>
</div>

{{-- Cards mobile --}}
<div class="sm:hidden space-y-2">
    @forelse($productos as $p)
    @php
        $pl      = $plConfigs->get($p->cod);
        $activo  = $pl !== null;
        $rowDias = $activo ? collect($pl->dias_reparto ?? [])->map(fn($d) => is_array($d) ? (int)$d['dia'] : (int)$d)->toArray() : [];
    @endphp
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 {{ !$activo ? 'opacity-50' : '' }}"
        data-cod="{{ $p->cod }}" data-activo="{{ $activo ? '1' : '0' }}">
        <div class="flex items-start gap-3">
            <input type="checkbox" {{ $activo ? 'checked' : '' }}
                class="toggle-activo mt-1 w-4 h-4 accent-red-600 cursor-pointer shrink-0"
                data-cod="{{ $p->cod }}"
                data-precio-base="{{ number_format($p->precio, 2, '.', '') }}">
            <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-800 text-sm">{{ $p->des }}</p>
                <p class="text-xs text-gray-400">{{ $p->desgrupo ?: '' }} · {{ $p->tipo === 'Unidad' ? 'por unidad' : 'por kg' }}</p>
                <div class="flex items-center gap-2 mt-2">
                    <span class="text-xs text-gray-400">Base: ${{ number_format($p->precio, 2, ',', '') }}</span>
                    <span class="text-xs text-gray-300">|</span>
                    <div class="flex items-center gap-1">
                        <span class="text-gray-400 text-xs">$</span>
                        <input type="number" step="0.01" min="0"
                            placeholder="{{ number_format($p->precio, 2, '.', '') }}"
                            value="{{ $activo && $pl->precio !== null ? number_format($pl->precio, 2, '.', '') : '' }}"
                            {{ !$activo ? 'disabled' : '' }}
                            class="precio-input w-24 border border-gray-200 rounded px-2 py-1 text-right tabular-nums text-xs focus:outline-none focus:ring-1 focus:ring-red-300 disabled:opacity-30 disabled:cursor-not-allowed"
                            data-cod="{{ $p->cod }}">
                        <span class="precio-ok text-green-500 text-xs hidden">✓</span>
                    </div>
                </div>
                @if(!empty($diasLoc))
                <div class="flex flex-wrap gap-2 mt-2 dias-container" data-cod="{{ $p->cod }}">
                    @foreach($diasLoc as $dNum)
                    <label class="flex items-center gap-0.5 text-xs cursor-pointer {{ !$activo ? 'opacity-30 pointer-events-none' : '' }}">
                        <input type="checkbox" value="{{ $dNum }}"
                            {{ (empty($rowDias) || in_array($dNum, $rowDias)) ? 'checked' : '' }}
                            {{ !$activo ? 'disabled' : '' }}
                            class="dia-check accent-blue-600"
                            data-cod="{{ $p->cod }}">
                        <span>{{ $diasLabelCorto[$dNum] ?? $dNum }}</span>
                    </label>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
    @empty
    <p class="text-center text-gray-400 text-sm py-8">No hay productos en el catálogo.</p>
    @endforelse
</div>

@endsection

@section('scripts')
<script>
const csrfToken  = document.querySelector('meta[name=csrf-token]').content;
const locId      = {{ $localidad->id }};
const baseUrl    = `/admin/localidades/${locId}/precios/`;

// ── Activar / desactivar producto ───────────────────────────────────────────
document.querySelectorAll('.toggle-activo').forEach(cb => {
    cb.addEventListener('change', async () => {
        const cod  = cb.dataset.cod;
        const rows = document.querySelectorAll(`[data-cod="${cod}"]`);

        if (cb.checked) {
            // Crear con precio null, días null
            await fetch(baseUrl + cod, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({}),
            });
            // Habilitar inputs en todas las filas con este cod
            rows.forEach(row => {
                row.classList.remove('opacity-50');
                row.querySelectorAll('.precio-input, .dia-check').forEach(el => {
                    el.disabled = false;
                    el.closest('label')?.classList.remove('opacity-30', 'pointer-events-none');
                });
                row.querySelectorAll('.dias-container label').forEach(lbl => {
                    lbl.classList.remove('opacity-30', 'pointer-events-none');
                });
            });
        } else {
            if (!confirm('¿Quitar "' + cod + '" de esta localidad?')) {
                cb.checked = true; return;
            }
            await fetch(baseUrl + cod, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            });
            rows.forEach(row => {
                row.classList.add('opacity-50');
                row.querySelectorAll('.toggle-activo').forEach(c => c.checked = false);
                row.querySelectorAll('.precio-input').forEach(el => { el.disabled = true; el.value = ''; });
                row.querySelectorAll('.dia-check').forEach(el => { el.disabled = true; });
                row.querySelectorAll('.dias-container label').forEach(lbl => {
                    lbl.classList.add('opacity-30', 'pointer-events-none');
                });
            });
        }
    });
});

// ── Auto-save precio al perder el foco ──────────────────────────────────────
document.querySelectorAll('.precio-input').forEach(input => {
    let original = input.value;
    input.addEventListener('blur', async () => {
        if (input.value === original || input.disabled) return;
        original = input.value;
        const cod    = input.dataset.cod;
        const okSpan = input.parentElement.querySelector('.precio-ok');
        await fetch(baseUrl + cod, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ precio: input.value === '' ? null : parseFloat(input.value) }),
        });
        if (okSpan) { okSpan.classList.remove('hidden'); setTimeout(() => okSpan.classList.add('hidden'), 2000); }
    });
});

// ── Resetear días de todos los productos a null (usar todos los días de la localidad) ──
async function resetearDias() {
    if (!confirm('¿Resetear los días de TODOS los productos a "todos los días de la localidad"?\n\nEsto borra las restricciones por día de cada producto.')) return;
    const btn = document.getElementById('btn-reset-dias');
    btn.disabled = true; btn.textContent = '...';
    try {
        const res = await fetch(`/admin/localidades/${locId}/precios`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({}),
        });
        if (res.ok) {
            window.location.reload();
        } else {
            alert('Error al resetear días');
            btn.disabled = false; btn.textContent = 'Usar todos los días de la localidad';
        }
    } catch(e) {
        alert('Error de conexión');
        btn.disabled = false; btn.textContent = 'Usar todos los días de la localidad';
    }
}

// ── Auto-save días al cambiar checkbox ──────────────────────────────────────
document.addEventListener('change', async e => {
    const cb = e.target.closest('.dia-check');
    if (!cb || cb.disabled) return;
    const cod       = cb.dataset.cod;
    const container = document.querySelector(`.dias-container[data-cod="${cod}"]`);
    if (!container) return;
    const checked = [...container.querySelectorAll('.dia-check:checked')].map(c => ({ dia: parseInt(c.value) }));
    await fetch(baseUrl + cod, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ dias_reparto: checked.length > 0 ? checked : null }),
    });
});
</script>
@endsection
