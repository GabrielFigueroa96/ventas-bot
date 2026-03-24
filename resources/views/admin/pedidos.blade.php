@extends('admin.layout')
@section('title', 'Pedidos')

@section('content')
<div class="flex items-center justify-between mb-5">
    <h1 class="text-2xl font-bold text-gray-800">Pedidos</h1>
</div>

{{-- Filtros --}}
<div class="bg-white rounded-xl shadow p-4 mb-5">
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
                    <option value="{{ $val }}" {{ request('estado') == $val ? 'selected' : '' }}>{{ $info['label'] }}</option>
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

{{-- Resultados --}}
<div class="space-y-3">
    @include('admin.partials.pedidos', compact('pedidos', 'factventas', 'pedidosia', 'vmayo'))
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
const ESTADO_CONFIRMADO = 1;

// ── Avanzar estado ────────────────────────────────────────────────────────────
async function avanzarEstado(id, btn) {
    const max          = parseInt(btn.dataset.max ?? 4);
    const estadoActual = parseInt(btn.dataset.estado ?? 0);
    btn.disabled     = true;
    btn.innerHTML    = '<span class="animate-pulse">…</span>';

    let vmayoNro = null;
    if (estadoActual === ESTADO_CONFIRMADO) {
        vmayoNro = await pedirVmayo(id);
        if (vmayoNro === false) {
            btn.disabled  = false;
            btn.innerHTML = btn.dataset.label ?? '›';
            return;
        }
    }

    try {
        const body = vmayoNro != null ? JSON.stringify({ vmayo_nro: vmayoNro }) : null;
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
        if (!res.ok) { alert(data.error ?? 'Error'); btn.disabled = false; btn.innerHTML = btn.dataset.label ?? '›'; return; }

        const badge = document.getElementById(`badge-sia-${id}`);
        if (badge) {
            badge.textContent = data.label;
            badge.className   = `inline-flex items-center text-xs px-2.5 py-1 rounded-full font-semibold ${data.css}`;
        }
        const cancelBtn = document.getElementById(`cancel-sia-${id}`);
        if (cancelBtn) cancelBtn.remove();

        btn.dataset.estado = data.estado;
        actualizarTimeline(id, data.estado);

        if (data.estado >= max) {
            btn.remove();
        } else {
            btn.disabled  = false;
            btn.innerHTML = btn.dataset.label ?? '›';
        }
    } catch (e) {
        btn.disabled = false; btn.innerHTML = btn.dataset.label ?? '›';
    }
}

// ── Selector de vmayo ─────────────────────────────────────────────────────────
async function pedirVmayo(id) {
    const res      = await fetch(`/admin/pedidos/ia/${id}/vmayo-opciones`);
    const data     = await res.json();
    const opciones = data.opciones ?? [];

    if (opciones.length === 0) return null;

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
        if (!res.ok) { alert(data.error ?? 'Error'); btn.disabled = false; btn.textContent = '✕'; return; }

        const badge = document.getElementById(`badge-sia-${id}`);
        if (badge) {
            badge.textContent = data.label;
            badge.className   = `inline-flex items-center text-xs px-2.5 py-1 rounded-full font-semibold ${data.css}`;
        }
        btn.remove();
        const avanzarBtn = document.querySelector(`[onclick="avanzarEstado(${id}, this)"]`);
        if (avanzarBtn) avanzarBtn.remove();
    } catch (e) {
        btn.disabled = false; btn.textContent = '✕';
    }
}
</script>
@endsection
