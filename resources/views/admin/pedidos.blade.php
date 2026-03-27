@extends('admin.layout')
@section('title', 'Pedidos')

@section('content')
@php
    $nFiltrosActivos = (request('search') ? 1 : 0)
        + (request('estado') !== '' && request('estado') !== null ? 1 : 0)
        + (request()->has('fecha') && request('fecha') !== today()->format('Y-m-d') ? 1 : 0)
        + ($porEntrega ? 1 : 0);
@endphp

<div class="flex items-center justify-between mb-5">
    <h1 class="text-2xl font-bold text-gray-800">Pedidos</h1>
    <div class="flex items-center gap-2">

        {{-- Botón filtros (solo mobile) --}}
        <button onclick="abrirFiltros()" class="md:hidden relative flex items-center gap-1.5 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            Filtros
            @if($nFiltrosActivos > 0)
                <span class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center leading-none">{{ $nFiltrosActivos }}</span>
            @endif
        </button>

        <a href="{{ route('admin.pedidos.hoja_de_ruta') }}"
            class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            <span class="hidden sm:inline">Hoja de ruta</span>
            <span class="sm:hidden">Ruta</span>
        </a>
    </div>
</div>

{{-- Filtros desktop (oculto en mobile) --}}
<div class="hidden md:block bg-white rounded-xl shadow p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Cliente</label>
            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="Buscar..."
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 w-44">
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Estado</label>
            <select name="estado" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                <option value="">Todos</option>
                @foreach(\App\Models\Pedidosia::ESTADOS as $val => $info)
                    <option value="{{ $val }}" {{ request('estado') !== '' && request('estado') !== null && (string)request('estado') === (string)$val ? 'selected' : '' }}>{{ $info['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Fecha</label>
            <input type="date" name="fecha" value="{{ $fecha }}"
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        </div>

        <label class="flex flex-col gap-1 cursor-pointer select-none">
            <span class="text-xs font-medium text-gray-500 uppercase">Tipo de fecha</span>
            <div class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 h-[38px]">
                <div class="relative">
                    <input type="checkbox" name="por_entrega" value="1" class="sr-only peer"
                        {{ $porEntrega ? 'checked' : '' }}>
                    <div class="w-8 h-4 bg-gray-300 rounded-full peer-checked:bg-red-500 transition-colors"></div>
                    <div class="absolute top-0.5 left-0.5 w-3 h-3 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                </div>
                <span class="text-sm text-gray-600">Por entrega</span>
            </div>
        </label>

        <div class="flex gap-2 self-end">
            <button type="submit"
                class="bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-2 text-sm font-medium transition-colors">
                Filtrar
            </button>
            <a href="{{ route('admin.pedidos') }}"
                class="border border-gray-200 hover:bg-gray-50 text-gray-500 rounded-lg px-4 py-2 text-sm transition-colors">
                Limpiar
            </a>
        </div>

    </form>
</div>

{{-- Modal filtros mobile --}}
<div id="modal-filtros" class="hidden fixed inset-0 z-50 bg-black/40 flex items-end md:hidden" onclick="cerrarFiltros(event)">
    <div class="bg-white rounded-t-2xl w-full max-h-[90vh] overflow-y-auto"
         style="transform:translateY(100%);transition:transform .25s ease" id="panel-filtros">
        <div class="px-5 pt-5 pb-2 flex items-center justify-between border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Filtros</h3>
            <button onclick="cerrarFiltros()" class="text-gray-400 hover:text-gray-600 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form method="GET" class="px-5 py-4 space-y-4">

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 uppercase">Cliente</label>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Buscar cliente..."
                    class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 uppercase">Estado</label>
                <select name="estado" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:outline-none focus:ring-2 focus:ring-red-400">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Pedidosia::ESTADOS as $val => $info)
                        <option value="{{ $val }}" {{ request('estado') !== '' && request('estado') !== null && (string)request('estado') === (string)$val ? 'selected' : '' }}>{{ $info['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 uppercase">Fecha</label>
                <input type="date" name="fecha" value="{{ $fecha }}"
                    class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm w-full focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>

            <label class="flex items-center gap-3 cursor-pointer select-none">
                <div class="relative shrink-0">
                    <input type="checkbox" name="por_entrega" value="1" class="sr-only peer"
                        {{ $porEntrega ? 'checked' : '' }}>
                    <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-red-500 transition-colors"></div>
                    <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                </div>
                <span class="text-sm text-gray-700">Filtrar por fecha de entrega</span>
            </label>

            <div class="flex gap-3 pt-1 pb-2">
                <button type="submit"
                    class="flex-1 bg-red-600 hover:bg-red-700 text-white rounded-xl py-3 text-sm font-semibold transition-colors">
                    Aplicar filtros
                </button>
                <a href="{{ route('admin.pedidos') }}"
                    class="flex-1 text-center border border-gray-200 hover:bg-gray-50 text-gray-500 rounded-xl py-3 text-sm transition-colors">
                    Limpiar
                </a>
            </div>

        </form>
    </div>
</div>

{{-- Resultados --}}
<div class="space-y-3">
    @include('admin.partials.pedidos', compact('pedidos', 'factventas', 'pedidosia', 'vmayo'))
</div>

{{-- Modal elegir estado destino (desde Confirmado) --}}
<div id="modal-estado-destino" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xs p-5 space-y-3">
        <h3 class="text-sm font-semibold text-gray-800">¿A qué estado querés pasar?</h3>
        <div class="flex flex-col gap-2 pt-1">
            <button type="button" id="estado-destino-preparado"
                class="w-full text-left px-4 py-3 rounded-lg border border-orange-200 hover:border-orange-400 hover:bg-orange-50 transition text-sm font-medium text-orange-700">
                📦 Preparado
            </button>
            <button type="button" id="estado-destino-final"
                class="w-full text-left px-4 py-3 rounded-lg border border-green-200 hover:border-green-400 hover:bg-green-50 transition text-sm font-medium text-green-700">
                ✅ Entregado
            </button>
        </div>
        <div class="pt-2 border-t border-gray-100">
            <button id="estado-destino-cancelar" type="button"
                class="w-full text-xs text-red-400 hover:text-red-600 border border-gray-200 rounded-lg py-2">
                Cancelar
            </button>
        </div>
    </div>
</div>

{{-- Modal vincular vmayo --}}
<div id="modal-vmayo" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-5 space-y-3">
        <h3 class="text-sm font-semibold text-gray-800">Vincular con pedido procesado</h3>
        <p class="text-xs text-gray-500">Seleccioná el registro que corresponde a este pedido.</p>
        <div id="vmayo-lista" class="space-y-2 max-h-64 overflow-y-auto"></div>
        <div class="flex gap-2 pt-2 border-t border-gray-100">
            <button id="vmayo-cancelar" type="button"
                class="flex-1 text-xs text-red-400 hover:text-red-600 border border-gray-200 rounded-lg py-2">
                Cancelar
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name=csrf-token]').content;

// ── Modal filtros mobile ───────────────────────────────────────────────────────
function abrirFiltros() {
    const modal = document.getElementById('modal-filtros');
    const panel = document.getElementById('panel-filtros');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => { panel.style.transform = 'translateY(0)'; });
}
function cerrarFiltros(e) {
    if (e && e.target !== document.getElementById('modal-filtros')) return;
    const modal = document.getElementById('modal-filtros');
    const panel = document.getElementById('panel-filtros');
    panel.style.transform = 'translateY(100%)';
    setTimeout(() => modal.classList.add('hidden'), 250);
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarFiltros();
});
const ESTADO_CONFIRMADO = 1;

// ── Avanzar estado ────────────────────────────────────────────────────────────
async function avanzarEstado(id, btn) {
    const max          = parseInt(btn.dataset.max ?? 4);
    const estadoActual = parseInt(btn.dataset.estado ?? 0);
    btn.disabled     = true;
    btn.innerHTML    = '<span class="animate-pulse">…</span>';

    let vmayoNro = null;
    let estadoDestino = null;

    if (estadoActual === ESTADO_CONFIRMADO) {
        const tipoEntrega = btn.dataset.tipoEntrega ?? 'envio';

        // Envío: elegir entre Preparado o Entregado
        if (tipoEntrega === 'envio') {
            estadoDestino = await pedirEstadoDestino(max, tipoEntrega);
            if (estadoDestino === false) {
                btn.disabled  = false;
                btn.innerHTML = btn.dataset.label ?? '›';
                return;
            }
        }
        // Retiro: avanza directo a "Listo para retirar" (estado 2), sin elección

        // Vincular vmayo en ambos casos
        vmayoNro = await pedirVmayo(id);
        if (vmayoNro === false) {
            btn.disabled  = false;
            btn.innerHTML = btn.dataset.label ?? '›';
            return;
        }
    }

    try {
        const payload = {};
        if (vmayoNro != null)     payload.vmayo_nro      = vmayoNro;
        if (estadoDestino != null) payload.estado_destino = estadoDestino;
        const body = Object.keys(payload).length ? JSON.stringify(payload) : null;

        const res  = await fetch(`/admin/pedidos/ia/${id}/estado`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            body,
        });
        const data = await res.json();
        if (!res.ok) { showToast(data.error ?? 'Error al actualizar el pedido', 'error'); btn.disabled = false; btn.innerHTML = btn.dataset.label ?? '›'; return; }

        const badge = document.getElementById(`badge-sia-${id}`);
        if (badge) {
            badge.textContent = data.label;
            badge.className   = `inline-flex items-center text-xs px-2.5 py-1 rounded-full font-semibold ${data.css}`;
        }
        const cancelBtn = document.getElementById(`cancel-sia-${id}`);
        if (cancelBtn) cancelBtn.remove();

        btn.dataset.estado = data.estado;
        actualizarTimeline(id, data.estado);

        showToast('Estado actualizado: ' + data.label, 'success');

        if (data.estado >= max) {
            btn.remove();
        } else {
            btn.disabled  = false;
            btn.innerHTML = btn.dataset.label ?? '›';
        }
    } catch (e) {
        showToast('Error de conexión', 'error');
        btn.disabled = false; btn.innerHTML = btn.dataset.label ?? '›';
    }
}

// ── Selector de estado destino (desde Confirmado) ─────────────────────────────
function pedirEstadoDestino(max, tipoEntrega) {
    const esRetiro = tipoEntrega === 'retiro';
    const btnPreparado = document.getElementById('estado-destino-preparado');
    const btnFinal     = document.getElementById('estado-destino-final');

    btnPreparado.textContent = esRetiro ? '🏪 Listo para retirar' : '📦 Preparado';
    btnFinal.textContent     = esRetiro ? '✅ Retirado' : '✅ Entregado';
    btnPreparado.onclick = () => elegirEstadoDestino(2);
    btnFinal.onclick     = () => elegirEstadoDestino(max);

    return new Promise(resolve => {
        document.getElementById('estado-destino-cancelar').onclick = () => {
            document.getElementById('modal-estado-destino').classList.add('hidden');
            resolve(false);
        };
        window._estadoDestinoResolve = resolve;
        document.getElementById('modal-estado-destino').classList.remove('hidden');
    });
}

function elegirEstadoDestino(estado) {
    document.getElementById('modal-estado-destino').classList.add('hidden');
    if (window._estadoDestinoResolve) { window._estadoDestinoResolve(estado); window._estadoDestinoResolve = null; }
}

document.getElementById('modal-estado-destino')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.add('hidden');
        window._estadoDestinoResolve?.(false);
        window._estadoDestinoResolve = null;
    }
});

// ── Selector de vmayo ─────────────────────────────────────────────────────────
async function pedirVmayo(id) {
    const res  = await fetch(`/admin/pedidos/ia/${id}/vmayo-opciones`);
    const data = await res.json();

    if (!res.ok) {
        showToast(data.error ?? 'Error al cargar registros de vmayo', 'error');
        return false;
    }

    const opciones = data.opciones ?? [];
    if (opciones.length === 0) {
        showToast('No hay registros de vmayo disponibles para este cliente', 'error');
        return false;
    }

    return new Promise(resolve => {
        const lista = document.getElementById('vmayo-lista');
        lista.innerHTML = opciones.map(op => `
            <button type="button" onclick="elegirVmayo(${op.nro})"
                class="w-full text-left px-3 py-2.5 rounded-lg border border-gray-200 hover:border-orange-400 hover:bg-orange-50 transition text-sm flex items-center justify-between gap-2">
                <span><span class="font-semibold text-gray-800">#${op.nro}</span> <span class="text-gray-600">${op.nomcli}</span></span>
                <span class="text-xs text-gray-400 shrink-0">${op.items} ítems · $${op.total_fmt}</span>
            </button>`).join('');

        document.getElementById('vmayo-cancelar').onclick = () => { cerrarModalVmayo(); resolve(false); };
        window._vmayoResolve = resolve;
        document.getElementById('modal-vmayo').classList.remove('hidden');
    });
}

function elegirVmayo(nro) {
    cerrarModalVmayo();
    if (window._vmayoResolve) { window._vmayoResolve(nro); window._vmayoResolve = null; }
}

// ── Timeline ──────────────────────────────────────────────────────────────────
function actualizarTimeline(id, estado) {
    const el = document.getElementById(`timeline-sia-${id}`);
    if (!el) return;
    const tipo  = el.dataset.tipo;
    const pasos = tipo === 'retiro'
        ? ['Pendiente', 'Confirmado', 'Listo', 'Retirado']
        : ['Pendiente', 'Confirmado', 'Preparado', 'En camino', 'Entregado'];

    let html = '';
    pasos.forEach((label, i) => {
        const done    = estado > i;
        const current = estado === i;
        const circleClass = done
            ? 'bg-red-600 text-white'
            : current
                ? 'bg-red-100 text-red-700 ring-2 ring-red-500'
                : 'bg-gray-100 text-gray-400';
        const labelClass = current
            ? 'text-red-600 font-semibold'
            : done ? 'text-gray-500' : 'text-gray-300';
        const lineClass = done ? 'bg-red-400' : 'bg-gray-200';

        html += `<div class="flex flex-col items-center shrink-0">
            <div class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold ${circleClass}">
                ${done ? '✓' : i + 1}
            </div>
            <span class="text-[10px] mt-0.5 leading-tight text-center ${labelClass}">${label}</span>
        </div>`;
        if (i < pasos.length - 1) {
            html += `<div class="h-px flex-1 mb-3.5 ${lineClass}"></div>`;
        }
    });
    el.innerHTML = html;
}

function cerrarModalVmayo() {
    document.getElementById('modal-vmayo').classList.add('hidden');
}
document.getElementById('modal-vmayo')?.addEventListener('click', function(e) {
    if (e.target === this) { cerrarModalVmayo(); window._vmayoResolve?.(false); window._vmayoResolve = null; }
});

// ── Cancelar pedido ───────────────────────────────────────────────────────────
async function cancelarPedido(id, btn) {
    if (!confirm('¿Cancelar este pedido?')) return;
    btn.disabled    = true;
    btn.textContent = '…';
    try {
        const res  = await fetch(`/admin/pedidos/ia/${id}/cancelar`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) { showToast(data.error ?? 'Error al cancelar el pedido', 'error'); btn.disabled = false; btn.textContent = '✕'; return; }

        const badge = document.getElementById(`badge-sia-${id}`);
        if (badge) {
            badge.textContent = data.label;
            badge.className   = `inline-flex items-center text-xs px-2.5 py-1 rounded-full font-semibold ${data.css}`;
        }
        showToast('Pedido cancelado', 'warning');
        btn.remove();
        const avanzarBtn = document.querySelector(`[onclick="avanzarEstado(${id}, this)"]`);
        if (avanzarBtn) avanzarBtn.remove();
    } catch (e) {
        showToast('Error de conexión', 'error');
        btn.disabled = false; btn.textContent = '✕';
    }
}
</script>
@endsection
