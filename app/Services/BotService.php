<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Carrito;
use App\Models\Empresa;
use App\Models\Localidad;
use App\Models\Pedidosia;
use App\Models\Producto;
use App\Models\Message;
use App\Models\Pedido;

class BotService
{
    private const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';
    private const OPENAI_MODEL = 'gpt-4o-mini';

    // -------------------------------------------------------------------------
    // Punto de entrada principal
    // -------------------------------------------------------------------------

    public function process($client, $message, ?array $image = null): string
    {
        // Registro inicial: nombre → localidad → provincia
        if (empty($client->name) || $client->estado === 'esperando_nombre') {
            if ($client->estado !== 'esperando_nombre') {
                $client->update(['estado' => 'esperando_nombre']);
                $response = "¡Hola! Soy el asistente de la carnicería. ¿Cuál es tu nombre?";
            } else {
                $nombre = ucfirst(strtolower(trim($message)));
                $client->update(['name' => $nombre, 'estado' => 'esperando_localidad']);
                $localidades = Localidad::where('activo', true)->pluck('nombre')->implode(', ');
                $response = "¡Hola, {$nombre}! ¿En qué localidad estás?"
                    . ($localidades ? " (Repartimos en: {$localidades})" : '');
            }
            $this->sendWhatsapp($client->phone, $response);
            return $response;
        }

        if ($client->estado === 'esperando_localidad') {
            $input       = trim($message);
            $localidades = Localidad::where('activo', true)->get();

            $match = $localidades->first(
                fn($l) => stripos($l->nombre, $input) !== false || stripos($input, $l->nombre) !== false
            );

            if ($match) {
                $client->update([
                    'localidad'    => $match->nombre,
                    'localidad_id' => $match->id,
                    'estado'       => 'esperando_calle',
                ]);
                $diasLabel = Empresa::DIAS_LABEL;
                $dias      = $match->dias_reparto ?? [];
                $diasTexto = !empty($dias)
                    ? 'Repartimos en tu zona los: ' . implode(', ', array_map(fn($d) => $diasLabel[$d], $dias)) . '. '
                    : '';
                $response = "{$diasTexto}¿Cuál es tu calle y número de entrega? (ej: Italia 1234)";
            } else {
                $client->update([
                    'localidad'    => ucwords(strtolower($input)),
                    'localidad_id' => null,
                    'estado'       => 'activo',
                ]);
                $lista    = $localidades->pluck('nombre')->implode(', ');
                $response = "Anotado, {$client->name}. Por el momento no tenemos reparto a tu zona."
                    . ($lista ? " Repartimos en: {$lista}." : '')
                    . " Podés pasar a retirar al local. ¿En qué puedo ayudarte?";
            }

            $this->sendWhatsapp($client->phone, $response);
            return $response;
        }

        if ($client->estado === 'esperando_calle') {
            // Intenta separar "Calle 1234" → calle=Calle, numero=1234
            $input = trim($message);
            if (preg_match('/^(.+?)\s+(\d[\w-]*)$/', $input, $m)) {
                $client->update(['calle' => trim($m[1]), 'numero' => $m[2], 'estado' => 'esperando_dato_extra']);
            } else {
                $client->update(['calle' => $input, 'numero' => null, 'estado' => 'esperando_dato_extra']);
            }
            $response = "¿Tenés algún dato extra? (piso, depto, referencia) — respondé *no* para omitir.";
            $this->sendWhatsapp($client->phone, $response);
            return $response;
        }

        if ($client->estado === 'esperando_dato_extra') {
            $input = trim($message);
            $datoExtra = preg_match('/^no$/i', $input) ? null : $input;
            $client->update(['dato_extra' => $datoExtra, 'estado' => 'activo']);
            $dir      = trim("{$client->calle} {$client->numero}");
            $response = "¡Todo listo, {$client->name}! Dirección guardada: {$dir}"
                . ($datoExtra ? " ({$datoExtra})" : '') . ". ¿En qué puedo ayudarte?";
            $this->sendWhatsapp($client->phone, $response);
            return $response;
        }

        $response = $this->askChatGPT($message, $client, $image);
        $this->sendWhatsapp($client->phone, $response);
        return $response;
    }

    // -------------------------------------------------------------------------
    // ChatGPT con Function Calling
    // -------------------------------------------------------------------------

    public function askChatGPT(string $message, $cliente, ?array $image = null): string
    {
        $messages = $this->buildMessages($message, $cliente, $image);
        $empresa  = Cache::remember('bot_empresa_config', 300, fn() => Empresa::first());

        // Primera llamada: ChatGPT decide si responde o llama una función
        $response = $this->callOpenAI($messages, $this->tools($empresa));
        $choice   = $response['choices'][0];

        if ($choice['finish_reason'] === 'tool_calls') {
            return $this->handleToolCalls($choice, $messages, $cliente);
        }

        return $choice['message']['content'];
    }

    // -------------------------------------------------------------------------
    // Construcción del contexto para ChatGPT
    // -------------------------------------------------------------------------

    private function buildMessages(string $message, $cliente, ?array $image = null): array
    {
        $nombre  = $cliente->name ?? 'cliente';
        $codcli  = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
        $fecha   = now()->locale('es')->isoFormat('dddd D [de] MMMM YYYY');
        $empresa = Cache::remember('bot_empresa_config', 300, fn() => Empresa::first());

        // Lista cacheada 5 minutos — ahorra tokens y DB queries
        $lista = Cache::remember('productos_bot_lista', 300, function () {
            $productos = Producto::where('PRE', '>', 0)
                ->select('des', 'PRE', 'tipo', 'grupo', 'desgrupo', 'descripcion')
                ->get();

            $formatear = function ($p) {
                $precio = (int) $p->PRE;
                $unidad = $p->tipo === 'Unidad' ? '/u' : '/kg';
                $linea  = "{$p->des} \${$precio}{$unidad}";
                if (!empty($p->descripcion) && $p->descripcion !== 'sinimagen.webp') {
                    $linea .= " ({$p->descripcion})";
                }
                return $linea;
            };

            $bloques = [];

            foreach (['Unidad', 'Peso'] as $tipo) {
                $tipoLabel = $tipo === 'Unidad' ? 'POR UNIDAD' : 'POR KILO';
                $grupo = $productos->where('tipo', $tipo)
                    ->groupBy(fn($p) => $p->desgrupo ?: 'Sin grupo');

                if ($grupo->isEmpty()) continue;

                $bloque = "[{$tipoLabel}]";
                foreach ($grupo as $nombreGrupo => $items) {
                    $bloque .= "\n  [{$nombreGrupo}]\n";
                    $bloque .= $items->map(fn($p) => "  " . $formatear($p))->implode("\n");
                }
                $bloques[] = $bloque;
            }

            return implode("\n\n", $bloques);
        });

        $ultimoPedido = Pedido::where('codcli', $codcli)->latest('reg')->first();
        $ultimoPedidoTexto = $ultimoPedido
            ? "#{$ultimoPedido->nro} ({$ultimoPedido->fecha}): {$ultimoPedido->descrip} — {$ultimoPedido->estado_texto}"
            : 'ninguno';

        // Top 3 productos más pedidos por este cliente (personalización)
        $topProductos = Pedido::where('codcli', $codcli)
            ->selectRaw('descrip, COUNT(*) as veces')
            ->groupBy('descrip')
            ->orderByDesc('veces')
            ->take(3)
            ->pluck('descrip')
            ->implode(', ');

        // Contexto de cuenta comercial si está vinculada
        $localidad   = $cliente->cuenta?->loca ?? $cliente->localidad ?? null;
        $provincia   = $cliente->cuenta?->prov ?? $cliente->provincia ?? null;
        $cuentaTexto = $cliente->cuenta
            ? "\nCuenta: {$cliente->cuenta->nom} | {$cliente->cuenta->dom}, {$cliente->cuenta->loca}"
            : ($localidad ? "\nLocalidad: {$localidad}" . ($provincia ? ", {$provincia}" : '') : '');

        // Dirección preferida: primero la del perfil del cliente, si no la última usada en pedido
        if ($cliente->calle) {
            $ultimaDirTexto = "Dirección guardada del cliente: {$cliente->calle} {$cliente->numero}"
                . ($cliente->dato_extra ? " ({$cliente->dato_extra})" : '')
                . ($cliente->localidad  ? ", {$cliente->localidad}"   : '');
        } else {
            $ultimaDir = Pedidosia::where('idcliente', $cliente->id)
                ->where('tipo_entrega', 'envio')
                ->whereNotNull('calle')
                ->latest()
                ->first();
            $ultimaDirTexto = $ultimaDir
                ? "Última dirección de envío usada: {$ultimaDir->calle} {$ultimaDir->numero}"
                  . ($ultimaDir->localidad  ? ", {$ultimaDir->localidad}"  : '')
                  . ($ultimaDir->dato_extra ? " ({$ultimaDir->dato_extra})" : '')
                : '';
        }

        // Historial de últimos 10 mensajes para mantener contexto del pedido
        $history = Message::where('cliente_id', $cliente->id)
            ->latest()
            ->take(10)
            ->get()
            ->reverse();

        $favoritosTexto = $topProductos
            ? "Lo que más pide: {$topProductos}."
            : '';

        $messages = [];

        // --- Configuración operativa del negocio ---
        $infoNegocio   = trim($empresa?->bot_info ?? '');
        $instrucciones = trim($empresa?->bot_instrucciones ?? '');

        // Días de reparto: usa la localidad del cliente si tiene una configurada, si no la global
        $diasLabel     = Empresa::DIAS_LABEL;
        $localidadObj  = $cliente->localidad_id ? Localidad::find($cliente->localidad_id) : null;
        $diasReparto   = $localidadObj?->dias_reparto ?? $empresa?->bot_dias_reparto ?? [];
        if (!empty($diasReparto)) {
            $diasNombres = implode(', ', array_map(fn($d) => $diasLabel[$d] ?? $d, $diasReparto));
            $diasTexto   = $localidad
                ? "Días de reparto para {$localidad}: {$diasNombres}."
                : "Días de reparto: {$diasNombres}.";
        } else {
            $diasTexto = $localidadObj === null && $cliente->localidad
                ? "No hay reparto configurado para {$cliente->localidad}. Solo retiro en local."
                : '';
        }

        // Tipos de entrega habilitados
        $permiteEnvio  = $empresa?->bot_permite_envio  ?? true;
        $permiteRetiro = $empresa?->bot_permite_retiro ?? true;
        $entregasTexto = 'Tipos de entrega disponibles: ' . implode(' y ', array_filter([
            $permiteEnvio  ? 'envío' : null,
            $permiteRetiro ? 'retiro en local'   : null,
        ])) . '.';
        if (!$permiteEnvio && !$permiteRetiro) {
            $entregasTexto = 'Por el momento no se aceptan pedidos (entrega deshabilitada).';
        }

        // Medios de pago habilitados
        $mediosHabilitados = $empresa?->bot_medios_pago ?? array_keys(Empresa::MEDIOS_PAGO);
        $mediosLabel       = Empresa::MEDIOS_PAGO;
        $mediosTexto       = 'Medios de pago aceptados: ' . implode(', ', array_map(fn($m) => $mediosLabel[$m] ?? $m, $mediosHabilitados)) . '.';

        // Próximo día de reparto disponible para este cliente
        $proximoRepartoTexto = '';
        if (!empty($diasReparto)) {
            for ($i = 1; $i <= 7; $i++) {
                $candidato = now()->addDays($i);
                if (in_array((int) $candidato->format('w'), $diasReparto)) {
                    $proximoRepartoTexto = $candidato->locale('es')->isoFormat('dddd D [de] MMMM');
                    break;
                }
            }
        }

        // Texto dinámico para el paso 3 del flujo de pedido
        $entregasOpciones = array_filter([
            $permiteEnvio  ? 'envío' : null,
            $permiteRetiro ? 'retiro en local'   : null,
        ]);
        $mediosOpciones = array_map(fn($m) => $mediosLabel[$m] ?? $m, $mediosHabilitados);

        $paso3Fecha = $proximoRepartoTexto
            ? "¿Lo querés para el próximo {$proximoRepartoTexto}?" . (count($diasReparto) > 1 ? ' (o indicá otra fecha de reparto disponible)' : '')
            : '¿Para cuándo?';
        $paso3Entrega = count($entregasOpciones) === 1
            ? '¿Te lo ' . (in_array('envío', $entregasOpciones) ? 'enviamos' : 'pasás a buscar') . '?'
            : '¿' . implode(' o ', array_map('ucfirst', $entregasOpciones)) . '?';
        $paso3Pago = '¿Cómo abonás? (' . implode(', ', $mediosOpciones) . ')';

        $configNegocio = "\n{$entregasTexto}\n{$mediosTexto}";
        if ($diasTexto)      $configNegocio .= "\n{$diasTexto}";
        if ($infoNegocio)    $configNegocio .= "\n\nInformación del negocio:\n{$infoNegocio}";
        if ($instrucciones)  $configNegocio .= "\n\nInstrucciones especiales:\n{$instrucciones}";

        $messages[] = [
            'role'    => 'system',
            'content' => "Sos el asistente de una carnicería. Amable, breve y directo. Respondé siempre en español argentino.
Respondés consultas sobre: pedidos, precios, productos, horarios, dirección, formas de pago, días de reparto y cualquier información del negocio que tengas disponible.
Para cualquier otra consulta ajena al negocio, decí amablemente que no podés ayudar con eso.
Formato de precios: NUNCA uses separador de miles. Usá coma para decimales solo si hay centavos. Ejemplos correctos: \$1500 | \$36000 | \$2800,50. Nunca: \$1.500,00 ni \$36,000 ni \$21000,00.
Hoy es {$fecha}.
Cliente: {$nombre}{$cuentaTexto}
Último pedido: {$ultimoPedidoTexto}
{$favoritosTexto}
{$ultimaDirTexto}{$configNegocio}

Productos disponibles:
{$lista}

════════════════════════════════
FLUJO 1 — SUGERIR
════════════════════════════════
Activar cuando: el cliente saluda, no sabe qué quiere, pide recomendación o menciona una ocasión (asado, cumpleaños, etc.).
Pasos:
1. Si menciona una ocasión, calculá cantidades según las porciones estándar y mostrá solo productos de la lista disponible.
2. Si no menciona ocasión, sugerí sus favoritos o los más populares.
3. Ofrecé achuras cuando sea pertinente (una sola vez, sin insistir).
4. Preguntá si agrega al carrito.

Porciones estándar por persona:
- Por peso: asado de tira/vacío/costilla 0.500kg | entraña/colita 0.300kg | pollo 0.300kg | cerdo 0.300kg
- Por unidad: chorizo 1u (peso aprox en descripción) | morcilla 1u | hamburguesa 2u
  Cálculo kg desde unidades: personas × unidades_por_persona × peso_unitario (indicado en descripción del producto).

Sugerencias por ocasión — usá SOLO productos que estén en la lista:
- Parrillada: asado de tira, vacío, costillas, entraña, chorizos, morcilla, achuras.
- Pollo al horno: pollo entero o en presas. Tip: 180°C, 45 min/kg.
- Disco de arado: paleta, roast beef, osobuco, chorizos.
- Guiso/estofado: osobuco, paleta, roast beef, chorizos.
- Milanesas: nalga, peceto, bola de lomo. (1 kg ≈ 6-8 milanesas)

════════════════════════════════
FLUJO 2 — TOMAR PEDIDO
════════════════════════════════
Activar cuando: el cliente quiere agregar productos o ya tiene algo en mente.
Pasos:
1. Agregá cada producto con agregar_al_carrito (el sistema calcula kg, precio y neto). Para quitar un ítem, pasá cantidad 0.
2. Mostrá el resumen con ver_carrito. Si hay alertas de precio (⚠️) o producto no disponible (❌), ofrecé actualizar o eliminar el ítem antes de continuar.
3. Preguntá en un solo mensaje: {$paso3Fecha} | {$paso3Entrega} | {$paso3Pago}
4. Si eligió ENVÍO:
   - Si hay última dirección registrada, ofrecésela para confirmar o cambiar.
   - Si no hay, pedí calle, número y localidad (dato extra como piso/depto es opcional).
   - Confirmale la dirección antes de proceder.
5. Cuando tenés todos los datos (fecha, tipo_entrega, forma_pago, dirección si aplica), llamá DIRECTAMENTE a crear_pedido sin enviar ningún mensaje previo.
   - Fecha en obs si el cliente dice horario o turno (no en fecha_entrega).
6. Una vez creado el pedido (ves \'Pedido #X registrado\' en la respuesta del sistema), confirmáselo al cliente y no vuelvas a pedir confirmación. Si pregunta por el total o detalle, usá ver_pedidos.

Reglas de cantidad para agregar_al_carrito:
- Producto POR PESO: pasá siempre kg. \'3 vacío\' → 3 kg. \'medio kilo\' → 0.5.
- Producto POR UNIDAD: pasá cantidad entera de unidades.
- Producto POR PESO pedido en unidades: convertí solo si la descripción del producto indica el peso unitario (ej: \'aprox. 0.15kg c/u\'). Calculá kg = unidades × peso e informale. Si no tiene ese dato, pedile que indique en kg.
- Producto POR UNIDAD pedido en kg: no convertís, indicale que se pide por unidad.
- El total es siempre aproximado para productos por peso. Recordáselo.
- Formato numérico argentino: el cliente puede escribir con punto para miles y coma para decimales. Interpretá correctamente: \'1.500\' = 1500 | \'2,5\' = 2.5 | \'0,750\' = 0.75 | \'1.200,50\' = 1200.5

NUNCA menciones un producto fuera de la lista. NUNCA inventes ni estimes precios.

════════════════════════════════
FLUJO 3 — INFORMAR ESTADO
════════════════════════════════
Activar cuando: el cliente pregunta por su pedido, estado, demora o si ya está listo.
Pasos:
1. Usá ver_pedidos para obtener el historial y estado actual.
2. Informá el estado de forma clara: pendiente (en preparación), finalizado (listo).
3. Si el pedido está pendiente, decile que le avisaremos por WhatsApp cuando esté listo.
4. Si quiere cancelar un pedido pendiente, usá cancelar_pedido.

Herramientas disponibles:
- agregar_al_carrito → agregar/modificar ítems del carrito
- ver_carrito → resumen con totales, tiempo restante y validación de precios
- vaciar_carrito → limpiar el carrito
- crear_pedido → confirmar y registrar el pedido
- ver_pedidos → historial y estado de pedidos
- cancelar_pedido → cancelar un pedido pendiente
- ver_precios → lista de precios actualizada
- ver_producto → detalle e imagen de un producto específico
- Si recibís una imagen, describila e intentá relacionarla con un pedido.",
        ];

        foreach ($history as $msg) {
            $messages[] = [
                'role'    => $msg->direction === 'incoming' ? 'user' : 'assistant',
                'content' => $msg->message ?: '(imagen)',
            ];
        }

        if ($image) {
            $userContent = [];
            if ($message) {
                $userContent[] = ['type' => 'text', 'text' => $message];
            }
            $userContent[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => "data:{$image['mime']};base64,{$image['base64']}", 'detail' => 'auto'],
            ];
            $messages[] = ['role' => 'user', 'content' => $userContent];
        } else {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        return $messages;
    }

    // Descarga un archivo de WhatsApp y lo devuelve como base64
    public function downloadWhatsappMedia(string $mediaId): array
    {
        $waToken = config('api.whatsapp.key');

        $meta = Http::withToken($waToken)
            ->get("https://graph.facebook.com/v19.0/{$mediaId}")
            ->json();

        $url  = $meta['url']  ?? null;
        $mime = $meta['mime_type'] ?? 'image/jpeg';

        if (!$url) {
            throw new \RuntimeException('No se pudo obtener la URL de la imagen.');
        }

        $content = Http::withToken($waToken)->get($url)->body();

        return [
            'base64' => base64_encode($content),
            'mime'   => $mime,
        ];
    }

    // -------------------------------------------------------------------------
    // Definición de herramientas (functions)
    // -------------------------------------------------------------------------

    private function tools($empresa = null): array
    {
        // Enums dinámicos según configuración
        $tiposEntrega = array_values(array_filter([
            ($empresa?->bot_permite_envio  ?? true) ? 'envio'  : null,
            ($empresa?->bot_permite_retiro ?? true) ? 'retiro' : null,
        ]));
        if (empty($tiposEntrega)) $tiposEntrega = ['retiro'];

        $mediosPago = $empresa?->bot_medios_pago ?? ['efectivo', 'transferencia', 'cuenta_corriente', 'otro'];

        return [
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'agregar_al_carrito',
                    'description' => 'Agrega o actualiza productos en el carrito del cliente. El sistema calcula automáticamente kg, precio y neto. Usalo siempre que el cliente quiera productos o cuando sugerís una recomendación. Para quitar un producto, pasá cantidad 0.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'items' => [
                                'type'  => 'array',
                                'items' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'descrip'  => ['type' => 'string',  'description' => 'Nombre del producto tal como aparece en la lista.'],
                                        'cantidad' => ['type' => 'number',  'description' => 'Unidades si es por unidad (ej: 6). Kg si es por peso (ej: 2.5). Para peso vendido en unidades (chorizo, morcilla) pasá las unidades y el sistema convierte.'],
                                    ],
                                    'required' => ['descrip', 'cantidad'],
                                ],
                            ],
                        ],
                        'required' => ['items'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'ver_carrito',
                    'description' => 'Muestra el contenido actual del carrito con precios y total calculados por el sistema.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'vaciar_carrito',
                    'description' => 'Vacía completamente el carrito del cliente.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'crear_pedido',
                    'description' => 'Guarda en el sistema el pedido con los productos que están en el carrito. Llamalo solo cuando el cliente confirmó el carrito y respondió cómo recibe el pedido y cómo abona.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'fecha_entrega' => [
                                'type'        => 'string',
                                'description' => 'Fecha de entrega en formato Y-m-d (ej: 2026-03-20). Solo la fecha, sin hora.',
                            ],
                            'tipo_entrega' => [
                                'type'        => 'string',
                                'enum'        => $tiposEntrega,
                                'description' => 'envio: se lleva al cliente (domicilio, local comercial, etc.). retiro: el cliente pasa a buscar.',
                            ],
                            'forma_pago' => [
                                'type'        => 'string',
                                'enum'        => $mediosPago,
                                'description' => 'Cómo abona el cliente.',
                            ],
                            'calle' => [
                                'type'        => 'string',
                                'description' => 'Calle del domicilio de entrega. Solo si tipo_entrega es envio.',
                            ],
                            'numero' => [
                                'type'        => 'string',
                                'description' => 'Número de la calle del domicilio. Solo si tipo_entrega es envio.',
                            ],
                            'localidad' => [
                                'type'        => 'string',
                                'description' => 'Localidad del domicilio de entrega. Solo si tipo_entrega es envio.',
                            ],
                            'dato_extra' => [
                                'type'        => 'string',
                                'description' => 'Dato adicional del domicilio: piso, depto, referencia, etc. Opcional.',
                            ],
                            'obs' => [
                                'type'        => 'string',
                                'description' => 'Observaciones adicionales: horario, turno, corte especial, etc.',
                            ],
                        ],
                        'required' => ['fecha_entrega', 'tipo_entrega', 'forma_pago'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'ver_pedidos',
                    'description' => 'Muestra el historial y estado de los últimos pedidos del cliente.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'ver_precios',
                    'description' => 'Muestra la lista de precios de productos disponibles.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'ver_producto',
                    'description' => 'Muestra los detalles y la imagen de un producto cuando el cliente pregunta por él específicamente.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'nombre' => ['type' => 'string', 'description' => 'Nombre del producto tal como aparece en la lista.'],
                        ],
                        'required' => ['nombre'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'cancelar_pedido',
                    'description' => 'Cancela un pedido pendiente del cliente.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'nro' => ['type' => 'integer', 'description' => 'Número de pedido a cancelar.'],
                        ],
                        'required' => ['nro'],
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Ejecución de la función elegida por ChatGPT
    // -------------------------------------------------------------------------

    private function handleToolCalls(array $choice, array $messages, $cliente): string
    {
        // Agregar el mensaje del asistente con todos sus tool_calls
        $messages[] = $choice['message'];

        // Responder a cada tool_call (OpenAI exige respuesta para cada ID)
        foreach ($choice['message']['tool_calls'] as $toolCall) {
            $funcName = $toolCall['function']['name'];
            $args     = json_decode($toolCall['function']['arguments'], true) ?? [];

            $result = match ($funcName) {
                'agregar_al_carrito' => $this->agregarAlCarrito($cliente, $args['items'] ?? []),
                'ver_carrito'        => $this->verCarrito($cliente),
                'vaciar_carrito'     => $this->vaciarCarrito($cliente),
                'crear_pedido'       => $this->createOrder($cliente, $args['fecha_entrega'] ?? now()->addDay()->format('Y-m-d'), $args['tipo_entrega'] ?? 'retiro', $args['forma_pago'] ?? 'efectivo', $args['calle'] ?? '', $args['numero'] ?? '', $args['localidad'] ?? '', $args['dato_extra'] ?? '', $args['obs'] ?? ''),
                'ver_pedidos'        => $this->orderStatus($cliente),
                'ver_precios'        => $this->priceList(),
                'ver_producto'       => $this->verProducto($cliente, $args['nombre'] ?? ''),
                'cancelar_pedido'    => $this->cancelOrder($cliente, (int) $this->parsearNumero($args['nro'] ?? 0)),
                default              => 'Función desconocida.',
            };

            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $toolCall['id'],
                'content'      => $result,
            ];
        }

        // Segunda llamada: ChatGPT transforma los resultados en lenguaje natural
        $response = $this->callOpenAI($messages);

        return $response['choices'][0]['message']['content'];
    }

    // -------------------------------------------------------------------------
    // Acciones del negocio
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Carrito
    // -------------------------------------------------------------------------

    private function productosCache()
    {
        return Cache::remember(
            'productos_bot_precios',
            300,
            fn() => Producto::where('PRE', '>', 0)->get(['cod', 'des', 'PRE', 'tipo', 'imagen', 'descripcion'])
        );
    }

    private function getCarrito($client): ?Carrito
    {
        return Carrito::where('cliente_id', $client->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    private function agregarAlCarrito($client, array $items): string
    {
        $registro   = $this->getCarrito($client);
        $carrito    = $registro ? $registro->items : [];
        $productos  = $this->productosCache();
        $costoExtra = 0.0;

        if ($client->localidad_id) {
            $loc        = Localidad::find($client->localidad_id);
            $costoExtra = (float) ($loc?->costo_extra ?? 0);
        }

        foreach ($items as $item) {
            $descrip  = trim($item['descrip'] ?? '');
            $cantidad = $this->parsearNumero($item['cantidad'] ?? 0);

            if ($descrip === '') continue;

            $match = $productos->first(
                fn($p) => stripos($p->des, $descrip) !== false || stripos($descrip, $p->des) !== false
            );

            if (!$match) continue;

            $esPeso = $match->tipo !== 'Unidad';
            $precio = (float) $match->PRE + $costoExtra;

            $cant  = 0;
            $kilos = 0;

            if ($esPeso) {
                // Si la descripción tiene peso por unidad (ej: "aprox. 0.15kg c/u")
                // y la cantidad es un entero, PHP convierte unidades → kg
                $pesoUnitario = $this->parsePesoUnitario($match->descripcion ?? '');
                if ($pesoUnitario && $cantidad >= 1 && floor($cantidad) === $cantidad) {
                    $cant  = (int) $cantidad;
                    $kilos = round($cantidad * $pesoUnitario, 3);
                } else {
                    $kilos = $cantidad;
                }
            } else {
                $cant = (int) $cantidad;
            }

            $base = $esPeso ? $kilos : $cant;
            $neto = round($precio * $base, 2);

            $key = mb_strtolower($match->des);

            if ($cantidad <= 0) {
                unset($carrito[$key]);
            } else {
                $carrito[$key] = [
                    'cod'    => $match->cod,
                    'des'    => $match->des,
                    'cant'   => $cant,
                    'kilos'  => $kilos,
                    'precio' => $precio,
                    'neto'   => $neto,
                    'tipo'   => $match->tipo,
                ];
            }
        }

        if ($registro) {
            $registro->update([
                'items'      => $carrito,
                'expires_at' => now()->addMinutes(15),
            ]);
        } else {
            $registro = Carrito::create([
                'cliente_id' => $client->id,
                'items'      => $carrito,
                'expires_at' => now()->addMinutes(15),
            ]);
        }

        return $this->formatCarrito($carrito);
    }

    private function verCarrito($client): string
    {
        $registro = $this->getCarrito($client);

        if (!$registro || empty($registro->items)) {
            return 'El carrito está vacío.';
        }

        $costoExtra = 0.0;
        if ($client->localidad_id) {
            $loc        = Localidad::find($client->localidad_id);
            $costoExtra = (float) ($loc?->costo_extra ?? 0);
        }

        $resultado = $this->formatCarrito($registro->items, $costoExtra);

        // Tiempo restante
        $minutos = max(0, (int) now()->diffInMinutes($registro->expires_at, false));
        if ($minutos <= 0) {
            $resultado .= "\n\n⏱ El carrito está por vencer. Confirmá tu pedido ahora.";
        } elseif ($minutos <= 5) {
            $resultado .= "\n\n⏱ El carrito vence en {$minutos} min. Confirmá pronto.";
        } else {
            $resultado .= "\n\n⏱ El carrito vence en {$minutos} min.";
        }

        // Validar existencia y precios actuales
        $alertas = $this->validarCarrito($registro->items);
        if (!empty($alertas)) {
            $resultado .= "\n\n" . implode("\n", $alertas);
            $resultado .= "\n\nPodés actualizar los precios o eliminar los productos con problema antes de confirmar.";
        }

        return $resultado;
    }

    private function vaciarCarrito($client): string
    {
        Carrito::where('cliente_id', $client->id)->delete();
        return 'Carrito vaciado.';
    }

    private function formatCarrito(array $carrito, float $costoExtra = 0): string
    {
        if (empty($carrito)) {
            return 'El carrito está vacío.';
        }

        $lineas = [];
        $total  = 0;

        foreach ($carrito as $item) {
            $esPeso = $item['tipo'] !== 'Unidad';
            if ($esPeso) {
                $cant = $item['cant'] > 0
                    ? "{$item['cant']}u ({$item['kilos']}kg)"
                    : "{$item['kilos']}kg";
            } else {
                $cant = "{$item['cant']}u";
            }
            $precioFmt = $this->fmt($item['precio']);
            $netoFmt   = $this->fmt($item['neto']);
            $lineas[]  = "{$item['des']} {$cant} × {$precioFmt} \$ = {$netoFmt} \$";
            $total    += $item['neto'];
        }

        $lineas[] = 'TOTAL aprox.: $' . $this->fmt($total) . ' _(puede variar según el peso final)_';

        if ($costoExtra > 0) {
            $lineas[] = "_(Incluye recargo por zona: \${$this->fmt($costoExtra)}/u·kg)_";
        }

        return implode("\n", $lineas);
    }

    private function validarCarrito(array $carrito): array
    {
        if (empty($carrito)) {
            return [];
        }

        $codsEnCarrito = array_filter(array_column($carrito, 'cod'));
        $productosActuales = Producto::whereIn('cod', $codsEnCarrito)
            ->get(['cod', 'des', 'PRE'])
            ->keyBy('cod');

        $alertas = [];
        foreach ($carrito as $item) {
            if (!isset($item['cod']) || !$productosActuales->has($item['cod'])) {
                $alertas[] = "❌ {$item['des']}: producto no disponible actualmente";
                continue;
            }

            $preActual = (float) $productosActuales[$item['cod']]->PRE;
            if (abs($preActual - $item['precio']) > 0.01) {
                $precioViejo = $this->fmt($item['precio']);
                $precioNuevo = $this->fmt($preActual);
                $alertas[] = "⚠️ {$item['des']}: precio cambió de \${$precioViejo} a \${$precioNuevo}/u";
            }
        }

        return $alertas;
    }

    // Formatea precio sin separador de miles; omite decimales si son ,00
    private function fmt(float $val): string
    {
        return $val == (int) $val
            ? number_format($val, 0, ',', '')
            : number_format($val, 2, ',', '');
    }

    // Convierte número en formato argentino a float (ej: "1.500,75" → 1500.75, "2,5" → 2.5)
    private function parsearNumero($valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }
        $str = trim((string) $valor);
        // Si tiene punto Y coma: el punto es miles, la coma es decimal → "1.500,75"
        if (str_contains($str, '.') && str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        // Si solo tiene coma: puede ser decimal argentino → "2,5" = 2.5 o "1.500" (solo punto = miles)
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }
        // Si solo tiene punto: puede ser miles ("1.500") o decimal inglés ("1.5")
        // Si hay exactamente 3 dígitos después del punto, es miles
        elseif (preg_match('/^\d+\.(\d{3})$/', $str)) {
            $str = str_replace('.', '', $str);
        }
        return (float) $str;
    }

    // Extrae el peso por unidad de la descripción del producto (ej: "aprox. 0.15kg c/u" → 0.15)
    private function parsePesoUnitario(string $descripcion): ?float
    {
        if (preg_match('/aprox[.\s]*(\d+(?:[.,]\d+)?)\s*kg\s*c\//i', $descripcion, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        return null;
    }

    private function createOrder($client, string $fechaEntrega = '', string $tipoEntrega = 'retiro', string $formaPago = 'efectivo', string $calle = '', string $numero = '', string $localidad = '', string $datoExtra = '', string $obs = ''): string
    {
        $registro = $this->getCarrito($client);
        $carrito  = $registro ? $registro->items : [];

        if (empty($carrito)) {
            return 'El carrito está vacío. Agregá productos antes de confirmar el pedido.';
        }

        $nro    = (Pedido::max('nro') ?? 0) + 1;
        $fecha  = $fechaEntrega ?: now()->format('Y-m-d');
        $codcli = $client->cuenta ? $client->cuenta->cod : $client->id;
        $nomcli = $client->cuenta ? $client->cuenta->nom : $client->name;

        $precioActual = Producto::whereIn('cod', array_column($carrito, 'cod'))
            ->get(['cod', 'PRE'])
            ->keyBy('cod');

        $alertas  = [];
        $omitidos = [];

        foreach ($carrito as $item) {
            $precio = $item['precio'];
            $neto   = $item['neto'];

            // Producto no encontrado en BD → omitir del pedido
            if (!isset($item['cod']) || !$precioActual->has($item['cod'])) {
                $omitidos[] = $item['des'];
                continue;
            }

            // Verificar si el precio cambió desde que se armó el carrito
            $preActual = (float) $precioActual[$item['cod']]->PRE;
            if (abs($preActual - $precio) > 0.01) {
                $base   = $item['tipo'] !== 'Unidad' ? $item['kilos'] : $item['cant'];
                $neto   = round($preActual * $base, 2);
                $precio = $preActual;
                $alertas[] = "{$item['des']} (precio actualizado a $" . $this->fmt($preActual) . ')';
            }

            Pedido::create([
                'fecha'     => $fecha,
                'nro'       => $nro,
                'nomcli'    => $nomcli,
                'codcli'    => $codcli,
                'descrip'   => $item['des'],
                'kilos'     => $item['kilos'],
                'cant'      => $item['cant'],
                'precio'    => $precio,
                'neto'      => $neto,
                'estado'    => Pedido::ESTADO_PENDIENTE,
                'obs'       => $obs,
                'pedido_at' => now(),
                'venta'     => 0,
            ]);
        }

        $extras = [];
        if (!empty($omitidos)) {
            $extras[] = '❌ No disponibles (no se incluyeron): ' . implode(', ', $omitidos);
        }
        if (!empty($alertas)) {
            $extras[] = '⚠️ Precios actualizados: ' . implode(', ', $alertas);
        }

        $resumen = implode(', ', array_map(
            fn($item) => $item['cant'] > 0 ? "{$item['cant']}u {$item['des']}" : "{$item['kilos']}kg {$item['des']}",
            $carrito
        ));

        $total = array_sum(array_column($carrito, 'neto'));

        Pedidosia::create([
            'nro'          => $nro,
            'codcli'       => $codcli,
            'idcliente'    => $client->id,
            'nomcli'       => $nomcli,
            'fecha'        => $fecha,
            'tipo_entrega' => $tipoEntrega,
            'calle'        => $calle ?: null,
            'numero'       => $numero ?: null,
            'localidad'    => $localidad ?: null,
            'dato_extra'   => $datoExtra ?: null,
            'forma_pago'   => $formaPago,
            'total'        => $total,
            'obs'          => $obs ?: null,
            'estado'       => Pedidosia::ESTADO_PENDIENTE,
            'pedido_at'    => now(),
        ]);

        $registro?->delete();

        $msg = "Pedido #{$nro} registrado: {$resumen}.";
        if (!empty($extras)) {
            $msg .= "\n" . implode("\n", $extras);
        }

        return $msg;
    }

    private function orderStatus($client): string
    {
        $codcli  = $client->cuenta ? $client->cuenta->cod : $client->id;
        $pedidos = Pedido::where('codcli', $codcli)
            ->orderByDesc('reg')
            ->take(5)
            ->get();

        if ($pedidos->isEmpty()) {
            return 'El cliente no tiene pedidos registrados.';
        }

        return $pedidos->map(function ($p) {
            $cantidad = $p->cant > 1 ? "{$p->cant}u" : "{$p->kilos}kg";
            return "#{$p->nro} ({$p->fecha}): {$p->descrip} {$cantidad} — {$p->estado_texto}";
        })->implode("\n");
    }



    private function cancelOrder($client, int $nro): string
    {
        if ($nro <= 0) {
            return 'Número de pedido inválido.';
        }

        $codcli  = $client->cuenta ? $client->cuenta->cod : $client->id;
        $pedidos = Pedido::where('codcli', $codcli)
            ->where('nro', $nro)
            ->where('estado', Pedido::ESTADO_PENDIENTE)
            ->get();

        if ($pedidos->isEmpty()) {
            return "No encontré el pedido #{$nro} pendiente para este cliente. Puede que ya esté procesado o no exista.";
        }

        $pedidos->each->delete();

        return "Pedido #{$nro} cancelado correctamente.";
    }

    private function verProducto($client, string $nombre): string
    {
        $productos = Cache::remember(
            'productos_bot_precios',
            300,
            fn() => Producto::where('PRE', '>', 0)->get(['des', 'PRE', 'tipo', 'imagen'])
        );

        $producto = $productos->first(
            fn($p) => stripos($p->des, $nombre) !== false || stripos($nombre, $p->des) !== false
        );

        if (!$producto) {
            return "Producto '{$nombre}' no encontrado en la lista.";
        }

        // Enviar imagen si tiene
        if (!empty($producto->imagen) && $producto->imagen !== 'sinimagen.webp') {
            $path = public_path($producto->imagen);
            if (file_exists($path)) {
                try {
                    $mime    = mime_content_type($path) ?: 'image/jpeg';
                    $mediaId = $this->uploadMediaFromPath($path, $mime);
                    $this->sendWhatsappMedia($client->phone, $mediaId, 'image', $producto->des);
                } catch (\Throwable $e) {
                    Log::error("verProducto imagen {$producto->des}: {$e->getMessage()}");
                }
            }
        }

        $precio = $this->fmt((float) $producto->PRE);
        $unidad = $producto->tipo === 'Unidad' ? 'por unidad' : 'por kg';

        return "Producto: {$producto->des} — {$precio} $ ({$unidad}).";
    }

    private function uploadMediaFromPath(string $path, string $mime = 'image/jpeg'): string
    {
        $response = Http::withToken(config('api.whatsapp.key'))
            ->attach('file', file_get_contents($path), basename($path), ['Content-Type' => $mime])
            ->attach('messaging_product', 'whatsapp')
            ->attach('type', $mime)
            ->post('https://graph.facebook.com/v19.0/295131097015095/media');

        $mediaId = $response->json('id');

        if (!$mediaId) {
            throw new \RuntimeException('Error al subir imagen: ' . $response->body());
        }

        return $mediaId;
    }

    private function priceList(): string
    {
        Cache::forget('productos_bot_lista');
        Cache::forget('productos_bot_precios');

        $productos = Producto::where('PRE', '>', 0)->get(['des', 'PRE', 'tipo', 'imagen']);

        if ($productos->isEmpty()) {
            return 'No hay productos disponibles en este momento.';
        }

        return $productos->map(
            fn($p) => $p->tipo === 'Unidad'
                ? "{$p->des} — $" . $this->fmt((float) $p->PRE) . "/u"
                : "{$p->des} — $" . $this->fmt((float) $p->PRE) . "/kg"
        )->implode("\n");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function callOpenAI(array $messages, array $tools = []): array
    {
        $payload = [
            'model'      => self::OPENAI_MODEL,
            'messages'   => $messages,
            'max_tokens' => 300,
        ];

        if (!empty($tools)) {
            $payload['tools']       = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken(config('api.openai.key'))
            ->post(self::OPENAI_URL, $payload)
            ->json();

        if (!isset($response['choices'])) {
            $error = $response['error']['message'] ?? json_encode($response);
            Log::error("OpenAI error: {$error}");
            throw new \RuntimeException("Error al contactar OpenAI: {$error}");
        }

        // Guardar uso de tokens
        if (isset($response['usage'])) {
            DB::table('token_usos')->insert([
                'modelo'            => $response['model'] ?? self::OPENAI_MODEL,
                'prompt_tokens'     => $response['usage']['prompt_tokens'],
                'completion_tokens' => $response['usage']['completion_tokens'],
                'total_tokens'      => $response['usage']['total_tokens'],
                'created_at'        => now(),
            ]);
        }

        return $response;
    }

    public function transcribeAudio(string $mediaId): string
    {
        $waToken = config('api.whatsapp.key');

        // 1. Obtener la URL de descarga del audio
        $meta = Http::withToken($waToken)
            ->get("https://graph.facebook.com/v19.0/{$mediaId}")
            ->json();

        $audioUrl = $meta['url'] ?? null;

        if (!$audioUrl) {
            throw new \RuntimeException('No se pudo obtener la URL del audio.');
        }

        // 2. Descargar el archivo de audio
        $audioContent = Http::withToken($waToken)
            ->get($audioUrl)
            ->body();

        // 3. Guardar temporalmente
        $tmpPath = sys_get_temp_dir() . '/' . $mediaId . '.ogg';
        file_put_contents($tmpPath, $audioContent);

        // 4. Enviar a Whisper para transcribir
        $response = Http::withToken(config('api.openai.key'))
            ->attach('file', file_get_contents($tmpPath), basename($tmpPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model'    => 'whisper-1',
                'language' => 'es',
            ]);

        unlink($tmpPath);

        return $response->json('text') ?? '';
    }

    public function uploadMedia(\Illuminate\Http\UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        $response = Http::withToken(config('api.whatsapp.key'))
            ->attach(
                'file',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName(),
                ['Content-Type' => $mime]          // WhatsApp necesita el MIME en el attachment
            )
            ->attach('messaging_product', 'whatsapp')
            ->attach('type', $mime)
            ->post('https://graph.facebook.com/v19.0/295131097015095/media');

        $mediaId = $response->json('id');

        if (!$mediaId) {
            $error = $response->json('error.message') ?? $response->body();
            Log::error("WhatsApp uploadMedia error: {$error}");
            throw new \RuntimeException("Error al subir el archivo: {$error}");
        }

        return $mediaId;
    }

    public function sendWhatsappMedia(string $phone, string $mediaId, string $type, string $caption = ''): void
    {
        $body = ['id' => $mediaId];

        if ($caption) {
            $body['caption'] = $caption;
        }

        try {
            Http::withToken(config('api.whatsapp.key'))
                ->post('https://graph.facebook.com/v19.0/295131097015095/messages', [
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => $type,
                    $type               => $body,
                ]);
        } catch (\Throwable) {
            // silencioso
        }
    }

    public function sendWhatsapp(string $phone, string $message): void
    {
        try {
            Http::withToken(config('api.whatsapp.key'))
                ->post('https://graph.facebook.com/v19.0/295131097015095/messages', [
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'text',
                    'text'              => ['body' => $message],
                ]);
        } catch (\Throwable) {
            // silencioso para no romper el flujo
        }
    }
}