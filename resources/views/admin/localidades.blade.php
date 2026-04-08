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
                <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                    <button onclick="toggleLoc({{ $loc->id }}, this)"
                        data-activo="{{ $loc->activo ? '1' : '0' }}"
                        class="text-xs px-3 py-1 rounded-full font-medium {{ $loc->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $loc->activo ? 'Activa' : 'Inactiva' }}
                    </button>
                    <button id="flash-btn-{{ $loc->id }}"
                        onclick="abrirFlash({{ $loc->id }}, '{{ addslashes($loc->nombre) }}')"
                        class="text-xs px-3 py-1 rounded-full font-medium bg-gray-100 text-gray-600 hover:bg-orange-100 hover:text-orange-700 transition">
                        🚀 Express
                    </button>
                    @if($loc->rec_apertura || $loc->rec_cierre)
                    <button onclick="abrirProbar({{ $loc->id }}, '{{ addslashes($loc->nombre) }}', {{ json_encode(collect($loc->diasConfig())->map(fn($d) => ['dia' => $d['dia'], 'label' => \App\Models\IaEmpresa::DIAS_LABEL[$d['dia']] ?? $d['dia']])->values()) }})"
                        class="text-xs text-indigo-600 hover:underline">Probar</button>
                    @endif
                    <a href="{{ route('admin.localidades.precios', $loc->id) }}"
                        class="text-xs text-green-600 hover:underline">Precios</a>
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

{{-- Modal Express --}}
<div id="modal-flash" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-5 space-y-4 max-h-[90vh] overflow-y-auto">

        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-800">Pedido Express</h3>
                <p id="flash-loc-nombre" class="text-xs text-gray-400 mt-0.5"></p>
            </div>
            <button onclick="cerrarFlash()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
        </div>

        {{-- Sesiones activas --}}
        <div id="flash-sesiones-panel" class="hidden space-y-1">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-orange-700">🚀 Sesiones activas</span>
                <button onclick="desactivarFlash(null)" class="text-xs text-red-500 hover:underline">Desactivar todas</button>
            </div>
            <div id="flash-sesiones-lista"></div>
            <p id="flash-sesiones-pedidos" class="text-xs text-gray-400 mt-1"></p>
        </div>

        {{-- Config nueva sesión --}}
        <div id="flash-config" class="space-y-4">
            <p class="text-xs font-semibold text-gray-600">Nueva sesión express</p>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Válido por</label>
                    <div class="flex items-center gap-1">
                        <input type="number" id="flash-horas" min="1" max="168" value="12"
                            class="w-16 border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-orange-300 focus:outline-none">
                        <span class="text-xs text-gray-500">horas</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Recordatorio de cierre</label>
                    <div class="flex items-center gap-1">
                        <input type="number" id="flash-seg-horas" min="1" max="100" placeholder="—"
                            oninput="document.getElementById('flash-seg-msg-panel').classList.toggle('hidden', !this.value)"
                            class="w-16 border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-orange-300 focus:outline-none">
                        <span class="text-xs text-gray-500">hs antes del cierre</span>
                    </div>
                </div>
            </div>

            <div id="flash-seg-msg-panel" class="hidden">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Mensaje del recordatorio <span class="text-gray-400 font-normal">(a quienes no pidieron)</span></label>
                <textarea id="flash-seg-msg" rows="2"
                    placeholder="Ej: ¡Hola {nombre}! ⏰ Quedan pocas horas para cerrar el pedido express."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-300 focus:outline-none"></textarea>
                <p class="text-xs text-gray-400 mt-0.5">Variable: <code>{nombre}</code></p>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-semibold text-gray-600">
                        Productos
                        <span class="text-gray-400 font-normal ml-1">(opcional — si no elegís ninguno, usa los del día)</span>
                    </label>
                    <button type="button" onclick="cargarProductosFlash()"
                        class="text-xs text-orange-600 hover:underline">Cargar lista</button>
                </div>
                <div id="flash-productos-lista" class="space-y-1 max-h-52 overflow-y-auto pr-1">
                    <p class="text-xs text-gray-400 italic">Presioná "Cargar lista" para ver los productos de esta localidad.</p>
                </div>
            </div>
        </div>

        <div class="flex gap-2 pt-1">
            <button id="flash-activar-btn" onclick="activarFlash()"
                class="flex-1 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold py-2 rounded-lg transition">
                Activar ahora
            </button>
            <button onclick="cerrarFlash()"
                class="px-4 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50">
                Cancelar
            </button>
        </div>
    </div>
</div>

{{-- Modal probar --}}
<div id="modal-probar-loc" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-5 space-y-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">Probar recordatorio</h3>
            <p id="modal-loc-nombre" class="text-xs text-gray-400 mt-0.5"></p>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                <select id="probar-loc-tipo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="apertura">Apertura</option>
                    <option value="cierre">Cierre</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Día de reparto</label>
                <select id="probar-loc-dia" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></select>
            </div>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Teléfono</label>
            <input type="tel" id="probar-loc-phone" placeholder="5491123456789"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div id="probar-loc-preview" class="hidden bg-gray-50 rounded-lg px-3 py-2 text-xs text-gray-600 whitespace-pre-wrap border border-gray-200 max-h-40 overflow-y-auto"></div>
        <div class="flex gap-2 justify-end">
            <button onclick="cerrarProbarLoc()" class="text-sm text-gray-500 hover:underline px-3 py-1.5">Cancelar</button>
            <button id="probar-loc-btn" onclick="enviarPruebaLoc()"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-1.5 rounded-lg">
                Enviar prueba
            </button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let probarLocId = null;

function abrirProbar(id, nombre, dias) {
    probarLocId = id;
    document.getElementById('modal-loc-nombre').textContent = nombre;
    document.getElementById('probar-loc-phone').value = '';
    document.getElementById('probar-loc-preview').classList.add('hidden');
    const sel = document.getElementById('probar-loc-dia');
    sel.innerHTML = dias.map(d => `<option value="${d.dia}">${d.label}</option>`).join('');
    document.getElementById('modal-probar-loc').classList.remove('hidden');
    setTimeout(() => document.getElementById('probar-loc-phone').focus(), 50);
}

function cerrarProbarLoc() {
    document.getElementById('modal-probar-loc').classList.add('hidden');
}

function enviarPruebaLoc() {
    const phone = document.getElementById('probar-loc-phone').value.trim();
    if (!phone) return;
    const tipo = document.getElementById('probar-loc-tipo').value;
    const dia  = document.getElementById('probar-loc-dia').value;
    const btn  = document.getElementById('probar-loc-btn');
    btn.disabled = true; btn.textContent = 'Enviando...';

    fetch(`/admin/localidades/${probarLocId}/probar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        },
        body: JSON.stringify({ phone, tipo, dia }),
    })
    .then(r => r.json())
    .then(data => {
        const prev = document.getElementById('probar-loc-preview');
        prev.classList.remove('hidden');
        if (data.ok) {
            prev.textContent = data.mensaje;
            prev.className = prev.className.replace('text-red-600','') + ' text-gray-600';
        } else {
            prev.textContent = 'Error: ' + data.error;
            prev.className = prev.className.replace('text-gray-600','') + ' text-red-600';
        }
    })
    .finally(() => { btn.disabled = false; btn.textContent = 'Enviar prueba'; });
}

// ── Pedido Express ──────────────────────────────────────────────────────────
let flashLocId   = null;
let flashCatalogo = [];
let flashSeleccion = {}; // { cod: { checked, precio } }

async function abrirFlash(id, nombre) {
    flashLocId     = id;
    flashCatalogo  = [];
    flashSeleccion = {};
    document.getElementById('flash-loc-nombre').textContent = nombre;
    document.getElementById('flash-horas').value = 12;
    document.getElementById('flash-seg-horas').value = '';
    document.getElementById('flash-seg-msg').value = '';
    document.getElementById('flash-seg-msg-panel').classList.add('hidden');
    document.getElementById('flash-productos-lista').innerHTML =
        '<p class="text-xs text-gray-400 italic">Presioná "Cargar lista" para ver los productos de esta localidad.</p>';
    document.getElementById('flash-sesiones-panel').classList.add('hidden');
    document.getElementById('modal-flash').classList.remove('hidden');

    try {
        const res  = await fetch(`/admin/localidades/${id}/flash/estado`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (data.activo) renderSesionesActivas(data.sessions, data.pedidos);
    } catch {}
}

function renderSesionesActivas(sessions, pedidos) {
    if (!sessions || !sessions.length) {
        document.getElementById('flash-sesiones-panel').classList.add('hidden');
        return;
    }
    document.getElementById('flash-sesiones-panel').classList.remove('hidden');
    document.getElementById('flash-sesiones-pedidos').textContent =
        pedidos != null ? `${pedidos} pedido(s) recibido(s) desde la primera sesión` : '';

    document.getElementById('flash-sesiones-lista').innerHTML = sessions.map(s => {
        const expira = s.expira_en ? new Date(s.expira_en).toLocaleString('es-AR', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' }) : '—';
        const modo   = s.productos ? `${s.productos.length} producto(s)` : 'catálogo del día';
        return `<div class="flex items-center justify-between bg-orange-50 border border-orange-100 rounded-lg px-3 py-1.5 text-xs">
            <div>
                <span class="font-medium text-orange-800">${s.nombre ?? '—'}</span>
                <span class="text-gray-400 ml-1">· ${modo} · vence ${expira}</span>
            </div>
            <button onclick="desactivarFlash('${s.id}')" class="text-red-400 hover:text-red-600 ml-2 shrink-0">✕</button>
        </div>`;
    }).join('');
}

function cerrarFlash() {
    document.getElementById('modal-flash').classList.add('hidden');
    flashLocId = null;
}

async function cargarProductosFlash() {
    if (!flashLocId) return;
    try {
        const res  = await fetch(`/admin/localidades/${flashLocId}/flash/productos`, { headers: { Accept: 'application/json' } });
        flashCatalogo = await res.json();
        flashCatalogo.forEach(p => {
            if (!flashSeleccion[p.cod]) flashSeleccion[p.cod] = { checked: false, precio: p.precio };
        });
        renderFlashProductos();
    } catch {
        showToast('Error al cargar productos', 'error');
    }
}

function renderFlashProductos() {
    const cont = document.getElementById('flash-productos-lista');
    if (!flashCatalogo.length) {
        cont.innerHTML = '<p class="text-xs text-gray-400">No hay productos configurados para esta localidad.</p>';
        return;
    }
    cont.innerHTML = flashCatalogo.map(p => {
        const sel = flashSeleccion[p.cod] ?? { checked: false, precio: p.precio };
        return `<div class="flex items-center gap-2 py-1 border-b border-gray-100 last:border-0">
            <input type="checkbox" ${sel.checked ? 'checked' : ''} onchange="toggleFlashProd('${p.cod}', this.checked)"
                class="accent-orange-500 w-4 h-4 shrink-0">
            <span class="text-sm text-gray-700 flex-1 truncate" title="${p.des}">${p.des}</span>
            <span class="text-xs text-gray-400">${p.tipo === 'Peso' ? '/kg' : '/u'}</span>
            <input type="number" value="${sel.precio}" min="0" step="0.01"
                oninput="flashSeleccion['${p.cod}'].precio = parseFloat(this.value) || 0"
                class="w-24 border border-gray-300 rounded px-2 py-0.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-orange-400">
        </div>`;
    }).join('');
}

function toggleFlashProd(cod, checked) {
    if (!flashSeleccion[cod]) {
        const p = flashCatalogo.find(x => x.cod == cod);
        flashSeleccion[cod] = { checked, precio: p?.precio ?? 0 };
    } else {
        flashSeleccion[cod].checked = checked;
    }
}

async function activarFlash() {
    const horas = parseInt(document.getElementById('flash-horas').value);
    if (!horas || horas < 1) { showToast('Ingresá la duración en horas.', 'warning'); return; }

    const seleccionados = flashCatalogo
        .filter(p => flashSeleccion[p.cod]?.checked)
        .map(p => ({ cod: p.cod, des: p.des, precio: flashSeleccion[p.cod]?.precio ?? p.precio, tipo: p.tipo }));

    const segHoras = parseInt(document.getElementById('flash-seg-horas').value) || null;
    const segMsg   = document.getElementById('flash-seg-msg').value.trim() || null;

    const btn = document.getElementById('flash-activar-btn');
    btn.disabled = true; btn.textContent = 'Activando…';

    try {
        const res = await fetch(`/admin/localidades/${flashLocId}/flash`, {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', Accept: 'application/json' },
            body   : JSON.stringify({
                horas,
                productos               : seleccionados.length ? seleccionados : null,
                seguimiento_horas_antes : segHoras,
                seguimiento_mensaje     : segMsg,
            }),
        });
        const data = await res.json();
        if (data.ok) {
            renderSesionesActivas(data.sessions, null);
            actualizarBtnLocalidad(flashLocId, data.sessions.length > 0);
            showToast(`Sesión express activada por ${horas}hs`, 'success');
            // Limpiar form
            document.getElementById('flash-horas').value = 12;
            document.getElementById('flash-seg-horas').value = '';
            document.getElementById('flash-seg-msg').value = '';
            document.getElementById('flash-seg-msg-panel').classList.add('hidden');
            flashCatalogo  = [];
            flashSeleccion = {};
            document.getElementById('flash-productos-lista').innerHTML =
                '<p class="text-xs text-gray-400 italic">Presioná "Cargar lista" para ver los productos de esta localidad.</p>';
        } else {
            showToast(data.error ?? 'Error al activar', 'error');
        }
    } catch {
        showToast('Error de red', 'error');
    } finally {
        btn.disabled = false; btn.textContent = 'Activar ahora';
    }
}

async function desactivarFlash(sessionId) {
    if (!flashLocId) return;
    try {
        const res  = await fetch(`/admin/localidades/${flashLocId}/flash`, {
            method : 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' },
            body   : JSON.stringify({ session_id: sessionId }),
        });
        const data = await res.json();
        renderSesionesActivas(data.sessions ?? [], null);
        actualizarBtnLocalidad(flashLocId, (data.sessions ?? []).length > 0);
        showToast(sessionId ? 'Sesión desactivada' : 'Todas las sesiones desactivadas', 'success');
    } catch {
        showToast('Error al desactivar', 'error');
    }
}

function actualizarBtnLocalidad(id, activo) {
    const btn = document.getElementById(`flash-btn-${id}`);
    if (!btn) return;
    if (activo) {
        btn.className = btn.className.replace('bg-gray-100 text-gray-600', 'bg-orange-100 text-orange-700');
        btn.textContent = '🚀 Express activo';
    } else {
        btn.className = btn.className.replace('bg-orange-100 text-orange-700', 'bg-gray-100 text-gray-600');
        btn.textContent = '🚀 Express';
    }
}

// Al cargar la página, verificar cuáles localidades tienen flash activo
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[id^="flash-btn-"]').forEach(async btn => {
        const id = btn.id.replace('flash-btn-', '');
        try {
            const res  = await fetch(`/admin/localidades/${id}/flash/estado`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (data.activo) {
                btn.className = btn.className.replace('bg-gray-100 text-gray-600', 'bg-orange-100 text-orange-700');
                btn.textContent = '🚀 Express activo';
            }
        } catch {}
    });
});

// Cerrar modal al click fuera
document.getElementById('modal-flash').addEventListener('click', function(e) {
    if (e.target === this) cerrarFlash();
});

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
