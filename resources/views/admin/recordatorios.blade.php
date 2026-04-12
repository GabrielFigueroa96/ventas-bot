@extends('admin.layout')
@section('title', 'Recordatorios')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-800">Recordatorios</h1>
        <button onclick="document.getElementById('form-panel').classList.toggle('hidden')"
            class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">
            + Nuevo
        </button>
    </div>

    @if(session('ok'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">{{ session('ok') }}</div>
    @endif

    {{-- Formulario --}}
    @php $editando = $editando ?? null; @endphp
    <div id="form-panel" class="{{ $editando ? '' : 'hidden' }} bg-white rounded-xl shadow p-5 space-y-4">
        <h2 class="font-semibold text-gray-700">{{ $editando ? 'Editar recordatorio' : 'Nuevo recordatorio' }}</h2>

        <form method="POST"
              action="{{ $editando ? route('admin.recordatorios.update', $editando) : route('admin.recordatorios.store') }}"
              class="space-y-4">
            @csrf
            @if($editando) @method('PUT') @endif

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre interno</label>
                <input type="text" name="nombre" value="{{ old('nombre', $editando?->nombre) }}" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Mensaje</label>
                <p class="text-xs text-gray-400 mb-1">Variables: <code>{nombre}</code></p>
                <textarea name="mensaje" rows="4" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none"
                    placeholder="Ej: ¡Hola {nombre}! 🥩 Esta semana tenemos pollo con 10% de descuento. ¿Hacemos un pedido?">{{ old('mensaje', $editando?->mensaje) }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">
                    Template de WhatsApp
                    <span class="text-gray-400 font-normal">(recomendado — permite enviar fuera de la ventana de 24hs)</span>
                </label>
                <input type="text" name="template_nombre" value="{{ old('template_nombre', $editando?->template_nombre) }}"
                    placeholder="nombre_template o nombre_template|es_AR"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
                <p class="text-xs text-gray-400 mt-1">
                    El template debe tener un parámetro <code>{{1}}</code> en el cuerpo donde se insertará el mensaje.
                    Si no se configura, se envía como mensaje de texto (solo funciona dentro de 24hs).
                </p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">URL de imagen <span class="text-gray-400 font-normal">(opcional — se envía junto al mensaje)</span></label>
                <input type="url" name="imagen_url" value="{{ old('imagen_url', $editando?->imagen_url) }}"
                    placeholder="https://ejemplo.com/imagen.jpg"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
                <p class="text-xs text-gray-400 mt-1">La URL debe ser pública y accesible por WhatsApp. JPG o PNG recomendado.</p>
                @if(old('imagen_url', $editando?->imagen_url))
                    <img src="{{ old('imagen_url', $editando?->imagen_url) }}" alt=""
                         class="mt-2 h-20 rounded-lg border border-gray-200 object-cover">
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Hora de envío</label>
                    <input type="time" name="hora" value="{{ old('hora', $editando ? substr($editando->hora, 0, 5) : '09:00') }}" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Filtrar por localidad</label>
                    <select name="filtro_localidad"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
                        <option value="">Todas las localidades</option>
                        @foreach($localidades as $loc)
                            <option value="{{ $loc->nombre }}"
                                {{ old('filtro_localidad', $editando?->filtro_localidad) === $loc->nombre ? 'selected' : '' }}>
                                {{ $loc->nombre }}{{ $loc->provincia ? " ({$loc->provincia})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Filtrar por provincia</label>
                    <select name="filtro_provincia"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
                        <option value="">Todas las provincias</option>
                        @foreach($provincias as $prov)
                            <option value="{{ $prov }}"
                                {{ old('filtro_provincia', $editando?->filtro_provincia) === $prov ? 'selected' : '' }}>
                                {{ $prov }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-2">Días de envío (vacío = todos los días)</label>
                <div class="flex flex-wrap gap-2">
                    @foreach(\App\Models\Recordatorio::$DIAS_LABEL as $num => $label)
                        <label class="flex items-center gap-1 text-sm cursor-pointer">
                            <input type="checkbox" name="dias[]" value="{{ $num }}"
                                {{ in_array($num, old('dias', $editando?->dias ?? [])) ? 'checked' : '' }}
                                class="accent-red-600">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Pedido Express --}}
            <div class="border border-orange-200 bg-orange-50 rounded-lg p-4 space-y-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="toggle-express"
                        {{ !empty($editando?->productos_flash) ? 'checked' : '' }}
                        onchange="toggleExpressPanel(this.checked)"
                        class="accent-orange-500 w-4 h-4">
                    <span class="text-sm font-semibold text-orange-700">Pedido Express — restringir bot a lista de productos</span>
                </label>
                <div id="express-panel" class="{{ empty($editando?->productos_flash) ? 'hidden' : '' }} space-y-4">
                    <p class="text-xs text-gray-500">
                        Cuando se envíe este recordatorio, el bot entra en <strong>Modo Express</strong> para las localidades seleccionadas.<br>
                        <span class="text-orange-600 font-medium">Productos:</span>
                        si cargás una lista de productos, el bot se restringe a esa lista con los precios que configures.
                        Si lo dejás vacío, el bot usa automáticamente los productos del día según la configuración de la localidad
                        <span class="text-gray-400">(útil cuando la localidad reparte varios días con productos distintos)</span>.
                    </p>

                    {{-- Localidades destino --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">
                            Localidades destino
                            <span class="text-gray-400 font-normal">(las que reciben el mensaje y entran en modo express)</span>
                        </label>
                        <div class="flex flex-wrap gap-2" id="express-localidades-checks">
                            @foreach($localidades as $loc)
                            <label class="flex items-center gap-1 text-sm cursor-pointer">
                                <input type="checkbox" name="express_loc[]" value="{{ $loc->nombre }}"
                                    {{ in_array($loc->nombre, $editando?->flash_localidades ?? []) ? 'checked' : '' }}
                                    onchange="sincronizarFlashLocalidades()"
                                    class="accent-orange-500">
                                {{ $loc->nombre }}
                            </label>
                            @endforeach
                        </div>
                        <input type="hidden" name="flash_localidades" id="input-flash-localidades"
                            value="{{ $editando?->flash_localidades ? json_encode($editando->flash_localidades) : '' }}">
                    </div>

                    {{-- Duración + Seguimiento --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Válido por</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="flash_horas" min="1" max="72"
                                    value="{{ old('flash_horas', $editando?->flash_horas ?? 24) }}"
                                    class="w-20 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-orange-300 focus:outline-none">
                                <span class="text-xs text-gray-500">horas desde el envío</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                Recordatorio de cierre
                                <span class="text-gray-400 font-normal">(solo a quienes no pidieron)</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="seguimiento_horas_antes" min="1" max="23"
                                    value="{{ old('seguimiento_horas_antes', $editando?->seguimiento_horas_antes) }}"
                                    placeholder="—"
                                    class="w-20 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-orange-300 focus:outline-none">
                                <span class="text-xs text-gray-500">horas antes del cierre</span>
                            </div>
                        </div>
                    </div>
                    {{-- Mensaje del seguimiento --}}
                    <div id="seguimiento-msg-panel" class="{{ empty($editando?->seguimiento_horas_antes) ? 'hidden' : '' }}">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">
                            Mensaje del recordatorio de cierre
                        </label>
                        <textarea name="seguimiento_mensaje" rows="2"
                            placeholder="Ej: ¡Hola {nombre}! ⏰ Quedan pocas horas para cerrar el pedido express. ¿Querés pedir algo?"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-300 focus:outline-none">{{ old('seguimiento_mensaje', $editando?->seguimiento_mensaje) }}</textarea>
                        <p class="text-xs text-gray-400 mt-0.5">Variable disponible: <code>{nombre}</code></p>
                    </div>

                    {{-- Productos --}}
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs font-semibold text-gray-600">Productos</span>
                            <button type="button" onclick="cargarProductosExpress()"
                                class="text-xs bg-orange-100 hover:bg-orange-200 text-orange-800 font-medium px-3 py-1 rounded-lg border border-orange-200 transition">
                                Cargar desde localidad fuente
                            </button>
                            <span class="text-xs text-gray-400">(usa "Filtrar por localidad" como fuente de precios)</span>
                        </div>
                        <div id="lista-express" class="space-y-1">{{-- se llena vía JS --}}</div>
                        <input type="hidden" name="productos_flash" id="input-productos-flash"
                            value="{{ $editando?->productos_flash ? json_encode($editando->productos_flash) : '' }}">
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                    class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-6 py-2 rounded-lg">
                    {{ $editando ? 'Guardar cambios' : 'Crear recordatorio' }}
                </button>
                <a href="{{ route('admin.recordatorios') }}"
                    class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
            </div>
        </form>
    </div>

    {{-- Lista --}}
    <div class="space-y-3">
        @forelse($recordatorios as $rec)
        <div class="bg-white rounded-xl shadow px-5 py-4 flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0 space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-semibold text-gray-800">{{ $rec->nombre }}</span>
                    <span class="text-xs text-gray-400">🕐 {{ substr($rec->hora,0,5) }} · {{ $rec->diasTexto() }}</span>
                    @if(!empty($rec->flash_localidades))
                        <span class="text-xs text-orange-600">🚀 {{ implode(', ', $rec->flash_localidades) }}</span>
                    @elseif($rec->filtro_localidad)
                        <span class="text-xs text-purple-600">📍 {{ $rec->filtro_localidad }}</span>
                    @endif
                    @if($rec->filtro_provincia)
                        <span class="text-xs text-purple-600">🗺 {{ $rec->filtro_provincia }}</span>
                    @endif
                </div>
                <div class="flex items-start gap-2">
                    @if($rec->imagen_url)
                        <img src="{{ $rec->imagen_url }}" alt="" class="w-10 h-10 rounded object-cover border border-gray-200 shrink-0">
                    @endif
                    <p class="text-sm text-gray-500 truncate">{{ $rec->mensaje }}</p>
                </div>
                @if($rec->ultimo_envio_at)
                    <p class="text-xs text-gray-400">Último envío: {{ $rec->ultimo_envio_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 shrink-0">
                {{-- Toggle activo --}}
                <button onclick="toggleActivo({{ $rec->id }}, this)"
                    data-activo="{{ $rec->activo ? '1' : '0' }}"
                    class="text-xs px-3 py-1 rounded-full font-medium transition
                        {{ $rec->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $rec->activo ? 'Activo' : 'Pausado' }}
                </button>
                <button onclick="abrirProbar({{ $rec->id }}, '{{ addslashes($rec->nombre) }}')"
                    class="text-xs text-indigo-600 hover:underline">Probar</button>
                <a href="{{ route('admin.recordatorios.edit', $rec) }}"
                    class="text-xs text-blue-600 hover:underline">Editar</a>
                <form method="POST" action="{{ route('admin.recordatorios.destroy', $rec) }}"
                    onsubmit="return confirm('¿Eliminar este recordatorio?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-red-500 hover:underline">Eliminar</button>
                </form>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl shadow px-5 py-6 text-center text-gray-400 text-sm">
            No hay recordatorios. Creá el primero con el botón "+Nuevo".
        </div>
        @endforelse
    </div>
</div>

{{-- Modal probar recordatorio --}}
<div id="modal-probar" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-5 space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">Probar recordatorio</h3>
            <p id="modal-probar-nombre" class="text-xs text-gray-400 mt-0.5"></p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Número de WhatsApp</label>
            <input type="tel" id="probar-phone" placeholder="5491123456789"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <p class="text-xs text-gray-400 mt-1">Con código de país, sin el +. Ej: 5491123456789</p>
        </div>
        <div id="probar-preview" class="hidden bg-gray-50 rounded-lg px-3 py-2 text-xs text-gray-600 whitespace-pre-wrap border border-gray-200 max-h-36 overflow-y-auto"></div>
        <div class="flex gap-2 pt-1">
            <button id="probar-btn" onclick="enviarPrueba()"
                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg transition">
                Enviar prueba
            </button>
            <button onclick="cerrarProbar()"
                class="px-4 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                Cancelar
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const CSRF = document.querySelector('meta[name=csrf-token]').content;
let probarRecId = null;

// ── Pedido Express ──────────────────────────────────────────────────────────
let expressCatalogo = []; // [{cod,des,precio,tipo}] cargados de la localidad
let expressSeleccion = {}; // { cod: precio } seleccionados

function toggleExpressPanel(visible) {
    document.getElementById('express-panel').classList.toggle('hidden', !visible);
    if (!visible) {
        document.getElementById('input-productos-flash').value = '';
    }
}

// Mostrar/ocultar textarea de mensaje de seguimiento
document.addEventListener('DOMContentLoaded', function() {
    const horasInput = document.querySelector('input[name="seguimiento_horas_antes"]');
    if (horasInput) {
        horasInput.addEventListener('input', function() {
            document.getElementById('seguimiento-msg-panel')
                .classList.toggle('hidden', !this.value.trim());
        });
    }
});

async function cargarProductosExpress() {
    const localidad = document.querySelector('select[name=filtro_localidad]').value;
    if (!localidad) { showToast('Seleccioná una localidad primero.', 'warning'); return; }

    try {
        const res  = await fetch(`/admin/recordatorios/productos-localidad?localidad_nombre=${encodeURIComponent(localidad)}`, {
            headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) throw new Error('Error al cargar productos');
        expressCatalogo = await res.json();

        // Mantener precios editados si ya había selección
        expressCatalogo.forEach(p => {
            if (expressSeleccion[p.cod] === undefined) {
                expressSeleccion[p.cod] = { checked: false, precio: p.precio };
            }
        });

        renderExpressLista();
    } catch (e) {
        showToast(e.message, 'error');
    }
}

function renderExpressLista() {
    const cont = document.getElementById('lista-express');
    if (!expressCatalogo.length) {
        cont.innerHTML = '<p class="text-xs text-gray-400">No hay productos para esta localidad.</p>';
        return;
    }

    cont.innerHTML = expressCatalogo.map(p => {
        const sel   = expressSeleccion[p.cod] ?? { checked: false, precio: p.precio };
        const check = sel.checked ? 'checked' : '';
        return `<div class="flex items-center gap-2 py-1 border-b border-orange-100 last:border-0">
            <input type="checkbox" ${check} onchange="toggleExpressProd('${p.cod}', this.checked)"
                class="accent-orange-500 w-4 h-4 shrink-0">
            <span class="text-sm text-gray-700 flex-1 min-w-0 truncate" title="${p.des}">${p.des}</span>
            <span class="text-xs text-gray-400">${p.tipo === 'Peso' ? '/kg' : '/u'}</span>
            <input type="number" value="${sel.precio}" min="0" step="0.01"
                oninput="updateExpressPrecio('${p.cod}', this.value)"
                class="w-24 border border-gray-300 rounded px-2 py-0.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-orange-400">
        </div>`;
    }).join('');
}

function toggleExpressProd(cod, checked) {
    if (!expressSeleccion[cod]) {
        const p = expressCatalogo.find(x => x.cod == cod);
        expressSeleccion[cod] = { checked, precio: p?.precio ?? 0 };
    } else {
        expressSeleccion[cod].checked = checked;
    }
    sincronizarFlashInput();
}

function updateExpressPrecio(cod, valor) {
    if (!expressSeleccion[cod]) expressSeleccion[cod] = { checked: false, precio: 0 };
    expressSeleccion[cod].precio = parseFloat(valor) || 0;
    sincronizarFlashInput();
}

function sincronizarFlashLocalidades() {
    const checks = document.querySelectorAll('input[name="express_loc[]"]:checked');
    const nombres = Array.from(checks).map(c => c.value);
    document.getElementById('input-flash-localidades').value =
        nombres.length ? JSON.stringify(nombres) : '';
}

function sincronizarFlashInput() {
    const seleccionados = expressCatalogo
        .filter(p => expressSeleccion[p.cod]?.checked)
        .map(p => ({
            cod:    p.cod,
            des:    p.des,
            precio: expressSeleccion[p.cod]?.precio ?? p.precio,
            tipo:   p.tipo,
        }));
    document.getElementById('input-productos-flash').value =
        seleccionados.length ? JSON.stringify(seleccionados) : '';
}

// Al cargar la página: si hay productos_flash guardados, poblar expressSeleccion y renderizar
(function initExpressFromSaved() {
    const input = document.getElementById('input-productos-flash');
    if (!input || !input.value) return;
    try {
        const guardados = JSON.parse(input.value);
        if (!Array.isArray(guardados) || !guardados.length) return;
        expressCatalogo = guardados;
        guardados.forEach(p => {
            expressSeleccion[p.cod] = { checked: true, precio: p.precio };
        });
        renderExpressLista();
    } catch {}
})();

function abrirProbar(id, nombre) {
    probarRecId = id;
    document.getElementById('modal-probar-nombre').textContent = nombre;
    document.getElementById('probar-phone').value = '';
    document.getElementById('probar-preview').classList.add('hidden');
    document.getElementById('probar-preview').textContent = '';
    document.getElementById('modal-probar').classList.remove('hidden');
    setTimeout(() => document.getElementById('probar-phone').focus(), 50);
}

function cerrarProbar() {
    document.getElementById('modal-probar').classList.add('hidden');
    probarRecId = null;
}

async function enviarPrueba() {
    const phone = document.getElementById('probar-phone').value.trim().replace(/\D/g, '');
    if (!phone) { showToast('Ingresá un número de teléfono.', 'warning'); return; }

    const btn = document.getElementById('probar-btn');
    btn.disabled = true;
    btn.textContent = 'Enviando…';

    try {
        const res  = await fetch(`/admin/recordatorios/${probarRecId}/probar`, {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body   : JSON.stringify({ phone }),
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            showToast('Mensaje enviado correctamente.', 'success');
            const preview = document.getElementById('probar-preview');
            preview.textContent = data.mensaje;
            preview.classList.remove('hidden');
        } else {
            showToast(data.error ?? 'Error al enviar.', 'error');
        }
    } catch {
        showToast('Error de red.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Enviar prueba';
    }
}

// Cerrar modal al hacer click fuera
document.getElementById('modal-probar').addEventListener('click', function(e) {
    if (e.target === this) cerrarProbar();
});

function toggleActivo(id, btn) {
    fetch(`/admin/recordatorios/${id}/toggle`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    })
    .then(r => r.json())
    .then(data => {
        btn.dataset.activo = data.activo ? '1' : '0';
        btn.textContent    = data.activo ? 'Activo' : 'Pausado';
        btn.className = 'text-xs px-3 py-1 rounded-full font-medium transition ' +
            (data.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500');
    });
}
</script>
@endsection
