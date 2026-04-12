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
        @php $recs = $recsPorLoc[$loc->id] ?? collect(); @endphp
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
                    <button onclick="this.closest('.bg-white').querySelector('.recs-panel').classList.toggle('hidden')"
                        class="text-xs text-purple-600 hover:underline flex items-center gap-1">
                        📋 Recordatorios
                        @if($recs->count()) <span class="bg-purple-100 text-purple-700 rounded-full px-1.5">{{ $recs->count() }}</span> @endif
                    </button>
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

            {{-- Panel Recordatorios --}}
            <div class="recs-panel hidden border-t px-5 py-3 bg-purple-50/40">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-purple-700">Recordatorios Express</span>
                    <a href="{{ route('admin.recordatorios') }}" class="text-xs text-purple-500 hover:underline">+ Nuevo</a>
                </div>
                @if($recs->isEmpty())
                    <p class="text-xs text-gray-400 italic">No hay recordatorios express para esta localidad.</p>
                @else
                <div class="space-y-2">
                    @foreach($recs as $rec)
                    <div class="flex items-center gap-2 flex-wrap bg-white rounded-lg px-3 py-2 border border-purple-100">
                        {{-- Activo --}}
                        <button onclick="toggleRecActivo({{ $rec->id }}, this)"
                            data-activo="{{ $rec->activo ? '1' : '0' }}"
                            class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0 {{ $rec->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }}">
                            {{ $rec->activo ? 'Activo' : 'Pausado' }}
                        </button>

                        {{-- Nombre + horario --}}
                        <span class="text-sm text-gray-700 font-medium flex-1 min-w-0 truncate">{{ $rec->nombre }}</span>
                        <span class="text-xs text-gray-400 shrink-0">🕐 {{ substr($rec->hora, 0, 5) }} · {{ $rec->diasTexto() }}</span>

                        {{-- Express badge --}}
                        @if(!empty($rec->productos_flash) || !empty($rec->flash_localidades))
                        <span class="text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 shrink-0">🚀 Express</span>
                        @endif

                        {{-- Acciones --}}
                        <div class="flex items-center gap-2 shrink-0 ml-auto">
                            <button onclick="abrirProbarRec({{ $rec->id }}, '{{ addslashes($rec->nombre) }}')"
                                class="text-xs text-indigo-500 hover:underline">Probar</button>
                            <a href="{{ route('admin.recordatorios.edit', $rec) }}"
                                class="text-xs text-blue-500 hover:underline">Editar</a>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
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

{{-- Modal Probar Recordatorio (desde localidades) --}}
<div id="modal-probar-rec" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-5 space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">Probar recordatorio</h3>
            <p id="modal-rec-nombre" class="text-xs text-gray-400 mt-0.5"></p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Número de WhatsApp</label>
            <input type="tel" id="probar-rec-phone" placeholder="5491123456789"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
        </div>
        <div id="probar-rec-preview" class="hidden bg-gray-50 rounded-lg px-3 py-2 text-xs text-gray-600 whitespace-pre-wrap border border-gray-200 max-h-36 overflow-y-auto"></div>
        <div class="flex gap-2">
            <button id="probar-rec-btn" onclick="enviarPruebaRec()"
                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg">
                Enviar prueba
            </button>
            <button onclick="document.getElementById('modal-probar-rec').classList.add('hidden')"
                class="px-4 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50">
                Cancelar
            </button>
        </div>
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

@endsection

@section('scripts')
<script>
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

// ── Recordatorios desde localidades ─────────────────────────────────────────
function toggleRecActivo(id, btn) {
    fetch(`/admin/recordatorios/${id}/toggle`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(data => {
        btn.dataset.activo = data.activo ? '1' : '0';
        btn.textContent    = data.activo ? 'Activo' : 'Pausado';
        btn.className = btn.className
            .replace(/bg-\w+-100 text-\w+-\d+/, '')
            .trim() + ' ' + (data.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400');
    });
}


let probarRecLocId = null;
function abrirProbarRec(id, nombre) {
    probarRecLocId = id;
    document.getElementById('modal-rec-nombre').textContent = nombre;
    document.getElementById('probar-rec-phone').value = '';
    document.getElementById('probar-rec-preview').classList.add('hidden');
    document.getElementById('modal-probar-rec').classList.remove('hidden');
    setTimeout(() => document.getElementById('probar-rec-phone').focus(), 50);
}

async function enviarPruebaRec() {
    const phone = document.getElementById('probar-rec-phone').value.trim().replace(/\D/g, '');
    if (!phone) { showToast('Ingresá un número.', 'warning'); return; }
    const btn = document.getElementById('probar-rec-btn');
    btn.disabled = true; btn.textContent = 'Enviando…';
    try {
        const res  = await fetch(`/admin/recordatorios/${probarRecLocId}/probar`, {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', Accept: 'application/json' },
            body   : JSON.stringify({ phone }),
        });
        const data = await res.json();
        const prev = document.getElementById('probar-rec-preview');
        prev.classList.remove('hidden');
        if (data.ok) {
            prev.textContent = data.mensaje;
            showToast('Enviado', 'success');
        } else {
            prev.textContent = data.error ?? 'Error';
            showToast(data.error ?? 'Error', 'error');
        }
    } catch { showToast('Error de red', 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Enviar prueba'; }
}

document.getElementById('modal-probar-rec')?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
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
