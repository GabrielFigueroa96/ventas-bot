@extends('admin.layout')
@section('title', $flujo ? 'Editar flujo' : 'Nuevo flujo')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.css">
<style>
/* Layout full-height */
main { max-width:none !important; padding:0 !important; display:flex !important; flex-direction:column !important; overflow:hidden !important; }
#editor-wrapper { flex:1; min-height:0; display:flex; flex-direction:column; }

/* Drawflow canvas */
#drawflow { flex:1; background:#f8fafc; background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size:20px 20px; }
.drawflow-node { border-radius:10px !important; box-shadow:0 2px 8px rgba(0,0,0,.12) !important; border:none !important; padding:0 !important; min-width:170px; }
.drawflow-node.selected { box-shadow:0 0 0 2px #3b82f6, 0 2px 8px rgba(0,0,0,.2) !important; }
.drawflow-node .inputs, .drawflow-node .outputs { top:50% !important; transform:translateY(-50%) !important; }
.drawflow-node .input, .drawflow-node .output { background:#fff !important; border:2px solid #94a3b8 !important; width:12px !important; height:12px !important; border-radius:50% !important; top:0 !important; }
.drawflow-node .output:hover, .drawflow-node .input:hover { background:#3b82f6 !important; border-color:#3b82f6 !important; }
.drawflow-delete { background:#ef4444 !important; border-radius:50% !important; font-size:14px !important; width:22px !important; height:22px !important; line-height:22px !important; }

/* Node content */
.df-node { border-radius:10px; overflow:hidden; cursor:pointer; }
.df-head { padding:7px 12px; font-size:12px; font-weight:700; color:#fff; display:flex; align-items:center; gap:6px; }
.df-body { padding:8px 12px; font-size:11px; color:#475569; background:#fff; min-height:30px; border-radius:0 0 10px 10px; }
.df-preview { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:150px; }
.df-badge { display:inline-block; background:#f1f5f9; border-radius:4px; padding:1px 5px; font-size:10px; color:#64748b; margin:1px; }
.df-output-label { font-size:10px; color:#94a3b8; text-align:right; margin-top:4px; }

/* Palette */
.palette-item { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; cursor:grab; user-select:none; border:1px solid #e2e8f0; background:#fff; transition:.15s; font-size:12px; font-weight:600; }
.palette-item:hover { border-color:#94a3b8; box-shadow:0 1px 4px rgba(0,0,0,.1); }
.palette-dot { width:10px; height:10px; border-radius:50%; shrink:0; }

/* Properties panel */
#prop-panel { width:260px; shrink:0; background:#fff; border-left:1px solid #e2e8f0; overflow-y:auto; transition:.2s; }
#prop-panel.hidden { width:0; overflow:hidden; }
.prop-field label { display:block; font-size:11px; font-weight:600; color:#64748b; margin-bottom:3px; }
.prop-field input, .prop-field textarea, .prop-field select { width:100%; border:1px solid #e2e8f0; border-radius:6px; padding:6px 8px; font-size:12px; outline:none; }
.prop-field input:focus, .prop-field textarea:focus, .prop-field select:focus { border-color:#3b82f6; box-shadow:0 0 0 2px #dbeafe; }
</style>
@endpush

@section('content')
<div id="editor-wrapper">
    {{-- Toolbar --}}
    <div class="flex items-center gap-3 px-4 py-2 bg-white border-b border-gray-200 shrink-0">
        <a href="{{ route('admin.flujos') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <input id="flujo-nombre" type="text" value="{{ $flujo?->nombre ?? 'Nuevo flujo' }}"
            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-blue-300 w-48">
        <div class="flex-1"></div>
        <button onclick="clearCanvas()" class="text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition">Limpiar</button>
        <button onclick="zoomOut()" class="text-xs border border-gray-200 px-2 py-1.5 rounded-lg hover:bg-gray-50">−</button>
        <button onclick="zoomIn()" class="text-xs border border-gray-200 px-2 py-1.5 rounded-lg hover:bg-gray-50">+</button>
        <button onclick="fitView()" class="text-xs border border-gray-200 px-2 py-1.5 rounded-lg hover:bg-gray-50">⊡</button>
        <button id="btn-save" onclick="saveFlow()"
            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-1.5 rounded-lg transition">
            Guardar
        </button>
    </div>

    {{-- Main area --}}
    <div class="flex flex-1 min-h-0">

        {{-- Palette --}}
        <div class="w-48 shrink-0 bg-white border-r border-gray-200 overflow-y-auto p-3 space-y-2">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3">Nodos</p>

            @php
            $nodos = [
                ['tipo'=>'inicio',      'label'=>'Inicio',      'icon'=>'🟢', 'color'=>'#22c55e', 'desc'=>'Entrada del flujo'],
                ['tipo'=>'mensaje',     'label'=>'Mensaje',     'icon'=>'💬', 'color'=>'#3b82f6', 'desc'=>'Enviar texto fijo'],
                ['tipo'=>'pregunta',    'label'=>'Pregunta',    'icon'=>'❓', 'color'=>'#8b5cf6', 'desc'=>'Botones de opción'],
                ['tipo'=>'condicion',   'label'=>'Condición',   'icon'=>'🔀', 'color'=>'#f59e0b', 'desc'=>'Sí / No'],
                ['tipo'=>'ia',          'label'=>'IA',          'icon'=>'🤖', 'color'=>'#7c3aed', 'desc'=>'Respuesta con IA'],
                ['tipo'=>'herramienta', 'label'=>'Herramienta', 'icon'=>'🛠️', 'color'=>'#f97316', 'desc'=>'Ejecutar acción'],
                ['tipo'=>'fin',         'label'=>'Fin',         'icon'=>'🔴', 'color'=>'#ef4444', 'desc'=>'Terminar turno'],
            ];
            @endphp

            @foreach($nodos as $n)
            <div class="palette-item" draggable="true"
                ondragstart="drag(event)" data-node="{{ $n['tipo'] }}"
                onclick="addNodeCenter('{{ $n['tipo'] }}')">
                <span class="palette-dot" style="background:{{ $n['color'] }}"></span>
                <div>
                    <div>{{ $n['icon'] }} {{ $n['label'] }}</div>
                    <div class="text-gray-400 font-normal text-[10px]">{{ $n['desc'] }}</div>
                </div>
            </div>
            @endforeach

            <div class="border-t border-gray-100 pt-3 mt-3">
                <p class="text-[10px] text-gray-400 leading-relaxed">
                    Arrastrá los nodos al canvas o hacé clic para agregarlos al centro.<br><br>
                    Conectá los puertos de salida (derecha) con los de entrada (izquierda).
                </p>
            </div>
        </div>

        {{-- Canvas --}}
        <div id="drawflow" class="flex-1 min-w-0"
            ondrop="drop(event)" ondragover="allowDrop(event)"></div>

        {{-- Properties panel --}}
        <div id="prop-panel" class="hidden">
            <div class="p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 id="prop-title" class="text-sm font-bold text-gray-800">Propiedades</h3>
                    <button onclick="closePropPanel()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                </div>
                <div id="prop-fields" class="space-y-3 text-sm"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.js"></script>
<script>
// ── Config ──────────────────────────────────────────────────────────────────
const CSRF      = '{{ csrf_token() }}';
const FLUJO_ID  = {{ $flujo?->id ?? 'null' }};
const SAVE_URL  = FLUJO_ID
    ? `/admin/flujos/${FLUJO_ID}`
    : '/admin/flujos';
const DEF_INIT  = @json($flujo?->definicion);

// ── Node definitions ─────────────────────────────────────────────────────────
const NODE_DEF = {
    inicio:      { label:'Inicio',      icon:'🟢', color:'#22c55e', inputs:0, outputs:1,
                   defaults:{ trigger:'siempre', keywords:'' } },
    mensaje:     { label:'Mensaje',     icon:'💬', color:'#3b82f6', inputs:1, outputs:1,
                   defaults:{ texto:'' } },
    pregunta:    { label:'Pregunta',    icon:'❓', color:'#8b5cf6', inputs:1, outputs:3,
                   defaults:{ texto:'', opciones:'Opción 1\nOpción 2\nOpción 3' } },
    condicion:   { label:'Condición',   icon:'🔀', color:'#f59e0b', inputs:1, outputs:2,
                   defaults:{ campo:'tiene_localidad', etiq_si:'Sí', etiq_no:'No' } },
    ia:          { label:'IA',          icon:'🤖', color:'#7c3aed', inputs:1, outputs:1,
                   defaults:{ instruccion:'' } },
    herramienta: { label:'Herramienta', icon:'🛠️', color:'#f97316', inputs:1, outputs:1,
                   defaults:{ tool:'elegir_reparto' } },
    fin:         { label:'Fin',         icon:'🔴', color:'#ef4444', inputs:1, outputs:0,
                   defaults:{ } },
};

// ── Build node HTML ──────────────────────────────────────────────────────────
function buildHtml(tipo, data) {
    const def = NODE_DEF[tipo];
    let body = '';

    switch(tipo) {
        case 'inicio':
            body = `<span class="df-badge">${data.trigger === 'siempre' ? 'Siempre' : 'Keyword: ' + (data.keywords||'...')}</span>`;
            break;
        case 'mensaje':
            body = `<div class="df-preview">${escHtml(data.texto || 'Sin texto...')}</div>`;
            break;
        case 'pregunta':
            const opts = (data.opciones||'').split('\n').filter(Boolean);
            body = `<div class="df-preview mb-1">${escHtml(data.texto||'Sin pregunta')}</div>`
                 + opts.slice(0,3).map(o => `<span class="df-badge">${escHtml(o)}</span>`).join('');
            break;
        case 'condicion':
            body = `<span class="df-badge">${data.campo||'condición'}</span>
                    <div class="df-output-label">${data.etiq_si||'Sí'} / ${data.etiq_no||'No'}</div>`;
            break;
        case 'ia':
            body = data.instruccion
                ? `<div class="df-preview">${escHtml(data.instruccion)}</div>`
                : `<span class="df-badge">Respuesta libre de IA</span>`;
            break;
        case 'herramienta':
            body = `<span class="df-badge">${data.tool||'...'}</span>`;
            break;
        case 'fin':
            body = `<span class="df-badge text-gray-400">Fin del turno</span>`;
            break;
    }

    return `<div class="df-node">
        <div class="df-head" style="background:${def.color}">
            <span>${def.icon}</span><span>${def.label}</span>
        </div>
        <div class="df-body">${body}</div>
    </div>`;
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Init Drawflow ────────────────────────────────────────────────────────────
const container = document.getElementById('drawflow');
const editor    = new Drawflow(container);
editor.reroute = true;
editor.reroute_fix_curvature = true;
editor.start();

if (DEF_INIT) {
    editor.import(DEF_INIT);
}

// ── Add node ─────────────────────────────────────────────────────────────────
function addNode(tipo, x, y) {
    const def  = NODE_DEF[tipo];
    const data = { tipo, ...def.defaults };
    const html = buildHtml(tipo, data);
    editor.addNode(tipo, def.inputs, def.outputs, x, y, tipo, data, html);
}

function addNodeCenter(tipo) {
    const rect = container.getBoundingClientRect();
    const cx   = rect.width  / 2;
    const cy   = rect.height / 2;
    const x = cx * (1 / editor.zoom) - (editor.canvas_x * (1 / editor.zoom));
    const y = cy * (1 / editor.zoom) - (editor.canvas_y * (1 / editor.zoom));
    addNode(tipo, x - 85, y - 40);
}

// ── Drag & Drop ───────────────────────────────────────────────────────────────
function drag(ev) {
    ev.dataTransfer.setData('node', ev.currentTarget.getAttribute('data-node'));
}

function allowDrop(ev) { ev.preventDefault(); }

function drop(ev) {
    ev.preventDefault();
    const tipo = ev.dataTransfer.getData('node');
    if (!tipo) return;
    const rect = container.getBoundingClientRect();
    let x = (ev.clientX - rect.left) * (1 / editor.zoom) - (editor.canvas_x * (1 / editor.zoom));
    let y = (ev.clientY - rect.top)  * (1 / editor.zoom) - (editor.canvas_y * (1 / editor.zoom));
    addNode(tipo, x - 85, y - 40);
}

// ── Node selection → properties ──────────────────────────────────────────────
let selectedNodeId = null;

editor.on('nodeSelected', function(id) {
    selectedNodeId = id;
    showProps(id);
});

editor.on('nodeUnselect', function() {
    // Keep panel open so user can finish editing
});

editor.on('nodeRemoved', function() {
    closePropPanel();
});

function showProps(id) {
    const node = editor.getNodeFromId(id);
    if (!node) return;
    const tipo = node.class;
    const data = node.data || {};
    const def  = NODE_DEF[tipo];
    if (!def) return;

    document.getElementById('prop-title').textContent = def.icon + ' ' + def.label;
    document.getElementById('prop-panel').classList.remove('hidden');

    const fields = buildPropFields(tipo, data, id);
    document.getElementById('prop-fields').innerHTML = fields;
}

function closePropPanel() {
    document.getElementById('prop-panel').classList.add('hidden');
    selectedNodeId = null;
}

function buildPropFields(tipo, data, nodeId) {
    let html = '';

    const field = (key, label, input) =>
        `<div class="prop-field"><label>${label}</label>${input}</div>`;

    const text = (key, label, placeholder='') =>
        field(key, label, `<input type="text" value="${escHtml(data[key]||'')}" placeholder="${placeholder}"
            onchange="updateProp(${nodeId},'${key}',this.value)">`);

    const textarea = (key, label, rows=3, placeholder='') =>
        field(key, label, `<textarea rows="${rows}" placeholder="${placeholder}"
            onchange="updateProp(${nodeId},'${key}',this.value)">${escHtml(data[key]||'')}</textarea>`);

    const select = (key, label, options) => {
        const opts = options.map(([v,l]) =>
            `<option value="${v}" ${data[key]===v?'selected':''}>${l}</option>`).join('');
        return field(key, label, `<select onchange="updateProp(${nodeId},'${key}',this.value)">${opts}</select>`);
    };

    switch(tipo) {
        case 'inicio':
            html += select('trigger', 'Disparador', [
                ['siempre',  'Siempre (primera interacción)'],
                ['contiene', 'Mensaje contiene palabra clave'],
                ['igual',    'Mensaje exacto'],
            ]);
            html += text('keywords', 'Palabras clave (separadas por coma)', 'hola, hi, buenos días');
            break;

        case 'mensaje':
            html += textarea('texto', 'Texto a enviar', 4, 'Escribí el mensaje...');
            break;

        case 'pregunta':
            html += textarea('texto', 'Pregunta', 2, '¿Qué querés hacer?');
            html += textarea('opciones', 'Opciones (una por línea, máx. 3)', 4, 'Ver precios\nHacer pedido\nMi pedido');
            html += `<p class="text-[10px] text-gray-400">Cada opción conecta con una salida numerada del nodo.</p>`;
            break;

        case 'condicion':
            html += select('campo', 'Condición', [
                ['tiene_localidad', 'Cliente tiene localidad'],
                ['tiene_carrito',   'Tiene productos en carrito'],
                ['horario_abierto', 'Está en horario de pedidos'],
                ['es_cliente_nuevo','Es cliente nuevo'],
                ['texto_contiene',  'Mensaje contiene...'],
            ]);
            html += text('valor', 'Valor (si aplica)', 'texto a buscar');
            html += text('etiq_si', 'Etiqueta salida SÍ', 'Sí');
            html += text('etiq_no', 'Etiqueta salida NO', 'No');
            break;

        case 'ia':
            html += textarea('instruccion', 'Instrucción extra para la IA', 3,
                'Ej: Ofrecé el pack de parrilla si el cliente menciona asado');
            html += `<p class="text-[10px] text-gray-400 mt-1">La IA usará su flujo normal más esta instrucción adicional.</p>`;
            break;

        case 'herramienta':
            html += select('tool', 'Herramienta a ejecutar', [
                ['elegir_reparto',    '📅 Elegir fecha de reparto'],
                ['ver_precios',       '💲 Ver lista de precios'],
                ['ver_carrito',       '🛒 Ver carrito'],
                ['vaciar_carrito',    '🗑️ Vaciar carrito'],
                ['ver_pedidos',       '📦 Ver mis pedidos'],
                ['consultar_saldo',   '💳 Consultar saldo'],
            ]);
            break;

        case 'fin':
            html += `<p class="text-xs text-gray-400">Termina el turno. El bot esperará el próximo mensaje del cliente.</p>`;
            break;
    }

    return html;
}

function updateProp(nodeId, key, value) {
    // Update internal data
    const node = editor.getNodeFromId(nodeId);
    if (!node) return;
    node.data[key] = value;
    editor.drawflow.drawflow.Home.data[nodeId].data[key] = value;

    // Refresh node HTML
    const newHtml = buildHtml(node.class, node.data);
    const el = document.querySelector(`#node-${nodeId} .drawflow_content_node`);
    if (el) el.innerHTML = newHtml;
}

// ── Zoom / fit ────────────────────────────────────────────────────────────────
function zoomIn()  { editor.zoom_in(); }
function zoomOut() { editor.zoom_out(); }
function fitView() { editor.zoom_reset(); }
function clearCanvas() {
    if (!confirm('¿Limpiar todo el canvas?')) return;
    editor.clear();
    closePropPanel();
}

// ── Save ──────────────────────────────────────────────────────────────────────
async function saveFlow() {
    const btn    = document.getElementById('btn-save');
    const nombre = document.getElementById('flujo-nombre').value.trim();
    if (!nombre) { alert('Ingresá un nombre para el flujo.'); return; }

    btn.textContent = 'Guardando...';
    btn.disabled = true;

    const def    = JSON.stringify(editor.export());
    const method = FLUJO_ID ? 'PUT' : 'POST';

    try {
        const res = await fetch(SAVE_URL, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ nombre, definicion: def }),
        });
        const data = await res.json();
        if (data.ok) {
            btn.textContent = '✓ Guardado';
            btn.classList.replace('bg-blue-600','bg-green-600');
            btn.classList.replace('hover:bg-blue-700','hover:bg-green-700');
            if (!FLUJO_ID && data.id) {
                history.replaceState(null, '', `/admin/flujos/${data.id}/editar`);
            }
            setTimeout(() => {
                btn.textContent = 'Guardar';
                btn.classList.replace('bg-green-600','bg-blue-600');
                btn.classList.replace('hover:bg-green-700','hover:bg-blue-700');
                btn.disabled = false;
            }, 2000);
        }
    } catch(e) {
        btn.textContent = 'Error';
        btn.disabled = false;
    }
}

// Ctrl+S to save
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); saveFlow(); }
});
</script>
@endsection
