@extends('admin.layout')
@section('title', 'Flujo del Bot')

@push('styles')
<style>
.mermaid { background: transparent; }
#zoom-container { transform-origin: top left; transition: transform 0.2s; }
</style>
@endpush

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-lg font-bold text-gray-800">Flujo del Bot</h1>
        <div class="flex gap-2">
            <button onclick="zoom(-0.1)" class="text-sm border border-gray-200 px-3 py-1 rounded-lg hover:bg-gray-50">−</button>
            <button onclick="zoom(0)" class="text-sm border border-gray-200 px-3 py-1 rounded-lg hover:bg-gray-50">100%</button>
            <button onclick="zoom(0.1)" class="text-sm border border-gray-200 px-3 py-1 rounded-lg hover:bg-gray-50">+</button>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-4 overflow-auto" style="min-height:600px">
        <div id="zoom-container">

<div class="mermaid">
flowchart TD
    MSG([📱 Mensaje entrante\nWhatsApp / IG / FB])
    MSG --> MODO{Modo\ncliente?}

    MODO -->|humano| HUMAN[✋ Reenvía al admin\nsin procesar]
    MODO -->|confirmando_*| CONFIRM[⏳ Esperando\nbotones interactivos]
    MODO -->|bot| BUILD

    BUILD[🔧 buildMessages\nArmar contexto]
    BUILD --> LOC{¿Tiene\nlocalidad?}

    LOC -->|sí| PRODS_LOC[Cargar productos\nde la localidad]
    LOC -->|no| PRODS_ALL[Mostrar todos\nlos productos]

    PRODS_LOC --> FECHA_CHECK{¿Fecha de\nreparto elegida?}
    PRODS_ALL --> FECHA_CHECK

    FECHA_CHECK -->|sí| FILTER_DIA[Filtrar productos\npor día elegido]
    FECHA_CHECK -->|no + 1 sola fecha| AUTO_FECHA[Auto-seleccionar\nfecha única]
    FECHA_CHECK -->|no + múltiples| SHOW_ALL_PRODS[Mostrar todos los\nproductos de la localidad\n+ anotar días restringidos]

    AUTO_FECHA --> FILTER_DIA
    FILTER_DIA --> GPT
    SHOW_ALL_PRODS --> GPT

    GPT[🤖 OpenAI GPT\nProcesar mensaje]
    GPT --> DECISION{finish_reason}

    DECISION -->|message| REPLY[💬 Responder\ndirectamente]
    DECISION -->|tool_calls| TOOLS

    TOOLS[🛠️ Ejecutar herramientas]

    TOOLS --> T1["elegir_reparto\n→ muestra botones\ncon fechas disponibles"]
    TOOLS --> T2["agregar_al_carrito\n→ agrega / modifica ítems"]
    TOOLS --> T3["ver_carrito\n→ resumen con totales"]
    TOOLS --> T4["vaciar_carrito\n→ limpia el carrito"]
    TOOLS --> T5["crear_pedido\n→ inicia confirmación\ncon botones"]
    TOOLS --> T6["ver_pedidos\n→ historial y estado"]
    TOOLS --> T7["ver_precios\n→ lista de precios\nfiltrada por localidad"]
    TOOLS --> T8["ver_producto\n→ detalle + imagen"]
    TOOLS --> T9["cancelar_pedido\n→ cancela pendiente"]
    TOOLS --> T10["editar_pedido\n→ carga pedido\nal carrito"]
    TOOLS --> T11["consultar_saldo\n→ deuda de cuenta"]

    T1 --> TOOL_RESULT
    T2 --> TOOL_RESULT
    T3 --> TOOL_RESULT
    T4 --> TOOL_RESULT
    T5 --> CONFIRM_FLOW
    T6 --> TOOL_RESULT
    T7 --> TOOL_RESULT
    T8 --> TOOL_RESULT
    T9 --> TOOL_RESULT
    T10 --> TOOL_RESULT
    T11 --> TOOL_RESULT

    CONFIRM_FLOW[Estado → confirmando_*\nEnvía botones interactivos\nEntrega / Pago]
    CONFIRM_FLOW --> END_SILENT[🔇 Sin texto adicional\ncliente ve los botones]

    TOOL_RESULT[Resultado → GPT\n2da llamada]
    TOOL_RESULT --> GPT2[🤖 GPT genera\nrespuesta final]
    GPT2 --> REPLY

    REPLY --> MEMORY[⏱️ Programar\nActualizarMemoriaCliente\n+5 min, 1x por hora]
    MEMORY --> SEND([📤 Enviar respuesta])

    %% Flujo de botones interactivos
    CONFIRM -->|botón Confirmar| PEDIDO_CONFIRM[✅ Crear pedido\nen base de datos]
    CONFIRM -->|botón Cancelar| PEDIDO_CANCEL[❌ Cancelar\nconfirmación]
    PEDIDO_CONFIRM --> NOTIFY[📲 Notificar\nnúmero de pedido]
    PEDIDO_CANCEL --> ESTADO_BOT[Estado → activo]

    %% Estilos
    classDef start fill:#25d366,color:#fff,stroke:none
    classDef end_node fill:#25d366,color:#fff,stroke:none
    classDef gpt fill:#7c3aed,color:#fff,stroke:none
    classDef tool fill:#2563eb,color:#fff,stroke:none
    classDef decision fill:#f59e0b,color:#fff,stroke:none
    classDef action fill:#f0f9ff,color:#1e40af,stroke:#bfdbfe
    classDef warning fill:#fef3c7,color:#92400e,stroke:#fde68a
    classDef success fill:#d1fae5,color:#065f46,stroke:#a7f3d0

    class MSG,SEND start
    class GPT,GPT2 gpt
    class TOOLS,T1,T2,T3,T4,T5,T6,T7,T8,T9,T10,T11 tool
    class MODO,DECISION,LOC,FECHA_CHECK decision
    class BUILD,PRODS_LOC,PRODS_ALL,FILTER_DIA,AUTO_FECHA,SHOW_ALL_PRODS,TOOL_RESULT,MEMORY action
    class HUMAN,CONFIRM,END_SILENT warning
    class REPLY,PEDIDO_CONFIRM,NOTIFY,CONFIRM_FLOW success
</div>

        </div>
    </div>

    {{-- Leyenda --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
        <div class="flex items-center gap-2 bg-white border border-gray-100 rounded-lg px-3 py-2">
            <span class="w-3 h-3 rounded-full shrink-0" style="background:#25d366"></span>
            <span class="text-gray-600">Entrada / Salida</span>
        </div>
        <div class="flex items-center gap-2 bg-white border border-gray-100 rounded-lg px-3 py-2">
            <span class="w-3 h-3 rounded-full shrink-0" style="background:#7c3aed"></span>
            <span class="text-gray-600">OpenAI GPT</span>
        </div>
        <div class="flex items-center gap-2 bg-white border border-gray-100 rounded-lg px-3 py-2">
            <span class="w-3 h-3 rounded-full shrink-0" style="background:#2563eb"></span>
            <span class="text-gray-600">Herramientas (tools)</span>
        </div>
        <div class="flex items-center gap-2 bg-white border border-gray-100 rounded-lg px-3 py-2">
            <span class="w-3 h-3 rounded-full shrink-0" style="background:#f59e0b"></span>
            <span class="text-gray-600">Decisiones</span>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script type="module">
import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';
mermaid.initialize({ startOnLoad: true, theme: 'default', flowchart: { curve: 'basis', padding: 20 } });
</script>
<script>
let scale = 1;
function zoom(delta) {
    if (delta === 0) { scale = 1; }
    else { scale = Math.min(3, Math.max(0.3, scale + delta)); }
    document.getElementById('zoom-container').style.transform = `scale(${scale})`;
}
</script>
@endsection
