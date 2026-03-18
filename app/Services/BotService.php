<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        // Registro de nombre (flujo multi-turno simple)
        if (empty($client->name)) {
            if ($client->estado !== 'esperando_nombre') {
                $client->update(['estado' => 'esperando_nombre']);
                $response = "¡Hola! Soy el asistente de la carnicería. ¿Cuál es tu nombre?";
            } else {
                $nombre = ucfirst(strtolower(trim($message)));
                $client->update(['name' => $nombre, 'estado' => 'activo']);
                $response = "¡Gracias, {$nombre}! ¿En qué puedo ayudarte hoy?";
            }
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

        // Primera llamada: ChatGPT decide si responde o llama una función
        $response = $this->callOpenAI($messages, $this->tools());
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
        $nombre = $cliente->name ?? 'cliente';
        $codcli = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
        $fecha  = now()->locale('es')->isoFormat('dddd D [de] MMMM YYYY');

        // Lista cacheada 5 minutos — ahorra tokens y DB queries
        $lista = Cache::remember('productos_bot_lista', 300, function () {
            return Producto::where('PRE', '>', 0)
                ->select('des', 'PRE', 'tipo', 'descripcion')
                ->get()
                ->map(function ($p) {
                    $precio = number_format($p->PRE, 2, ',', '.');
                    $unidad = $p->tipo === 'Unidad' ? "por unidad" : "por peso";
                    $linea  = "{$p->des} {$precio} \$/{$unidad}";
                    if (!empty($p->descripcion) && $p->descripcion !== 'sinimagen.webp') {
                        $linea .= " — {$p->descripcion}";
                    }
                    return $linea;
                })
                ->implode("\n");
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
        $cuentaTexto = $cliente->cuenta
            ? "\nCuenta: {$cliente->cuenta->nom} | {$cliente->cuenta->dom}, {$cliente->cuenta->loca}"
            : '';

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

        $messages[] = [
            'role'    => 'system',
            'content' => "Sos el asistente de una carnicería. Amable, breve y directo. Hoy es {$fecha}.
Cliente: {$nombre}{$cuentaTexto}
Último pedido: {$ultimoPedidoTexto}
{$favoritosTexto}

Productos disponibles:
{$lista}

Reglas:
- Solo respondés preguntas relacionadas a la carnicería (pedidos, precios, productos). Si te preguntan otra cosa, decí amablemente que solo podés ayudar con eso.
- Para pedir: preguntá qué y cuánto. Antes de llamar a crear_pedido, preguntá siempre: 1) ¿Para cuándo lo necesitás? 2) ¿Alguna observación? (si responde que no, dejá obs vacío). Convertí la fecha a Y-m-d (hoy es {$fecha}). Luego confirmá el resumen completo y llamá a crear_pedido.
- Si el cliente no especifica qué quiere, sugerile sus productos favoritos o los más populares.
- Cuando alguien pide carne para asar, ofrecé también chorizos/morcillas si están disponibles (una sola vez, sin insistir).
- ver_precios → consultas de precios o lista de productos.
- ver_pedidos → estado e historial de pedidos.
- cancelar_pedido → si el cliente quiere cancelar un pedido pendiente.
- calcular_total → SIEMPRE usalo cuando recomendés productos (asado, parrillada, etc.) para mostrar el costo estimado real.
- Si recibís imagen, describila e intentá relacionarla con un pedido.
- Respondé siempre en español argentino.

Porciones estándar por persona:
- Por peso: asado de tira/vacío/costilla 0.500kg | entraña/colita 0.300kg | pollo 0.300kg | cerdo 0.300kg
- Por unidad: chorizo 1u | morcilla 1u | hamburguesa 2u

IMPORTANTE — productos, precios y unidades:
- NUNCA menciones un producto que no esté en la lista de productos disponibles.
- NUNCA inventes ni estimes un precio. Los únicos precios válidos son los de la lista.
- Si un producto típico de una ocasión no está en la lista, no lo mencionés.
- Cada producto indica si es por peso o por unidad. Respetá siempre esa unidad al pedir y al calcular.
- Si el cliente pide en UNIDADES un producto que se vende POR PESO: convertí a kg y aclaráselo.
  Pesos de referencia: chorizo 0.15kg, morcilla 0.20kg, bife 0.25kg, milanesa 0.15kg, hamburguesa 0.12kg, pechuga 0.35kg, muslo 0.25kg.
  Si no está en esa lista, preguntale el peso aproximado por unidad.
  Ejemplo: 6 chorizos → Son aprox. 0.9kg de chorizo, ¿te parece bien?
- Si el cliente pide en KG un producto que se vende POR UNIDAD: convertí a unidades y aclaráselo.
  Ejemplo: 1kg de hamburguesas → Son aprox. 8 hamburguesas, ¿confirmás?
- Siempre confirmá con la unidad correcta del sistema antes de llamar a crear_pedido.

Sugerencias por ocasión (filtrá contra la lista de productos disponibles):
- Parrillada/asado: asado de tira, vacío, costillas, entraña, chorizos, morcilla, achuras. Tip: empezá con achuras y chorizos, después las carnes.
- Pollo al horno: pollo entero o en presas. Tip: 180°C, 45 min por kg.
- Disco de arado: cortes para guisar (paleta, roast beef, osobuco), chorizos. Tip: dorar la carne primero.
- Guiso/estofado: osobuco, paleta, roast beef, chorizos. Tip: fuego lento mínimo 1.5h.
- Milanesas: nalga, peceto, bola de lomo. Tip: 1kg rinde aprox 6-8 milanesas.

Cuando alguien pide sugerencia para una ocasión:
1. Revisá la lista de productos disponibles y seleccioná SOLO los que aplican.
2. Si ningún producto de esa ocasión está disponible, decilo claramente.
3. Calculá cantidades con calcular_total (precios reales).
4. Agregá un tip breve y preguntá si hace el pedido.
- En crear_pedido y calcular_total: para artículos por peso usá kg, para artículos por unidad usá cantidad de unidades.",
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

    private function tools(): array
    {
        return [
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'crear_pedido',
                    'description' => 'Guarda el pedido en el sistema cuando el cliente ya confirmó qué quiere.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'items' => [
                                'type'        => 'array',
                                'description' => 'Lista de artículos del pedido.',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'descrip' => [
                                            'type'        => 'string',
                                            'description' => 'Nombre del producto.',
                                        ],
                                        'cantidad' => [
                                            'type'        => 'number',
                                            'description' => 'Kg si el producto es por peso (ej: 2.5). Unidades si es por unidad (ej: 6).',
                                        ],
                                    ],
                                    'required' => ['descrip', 'cantidad'],
                                ],
                            ],
                            'fecha_entrega' => [
                                'type'        => 'string',
                                'description' => 'Fecha en que el cliente necesita el pedido, formato Y-m-d (ej: 2026-03-20).',
                            ],
                            'obs' => [
                                'type'        => 'string',
                                'description' => 'Observaciones opcionales del cliente (instrucciones de entrega, corte especial, etc.).',
                            ],
                        ],
                        'required' => ['items', 'fecha_entrega'],
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
                    'name'        => 'calcular_total',
                    'description' => 'Calcula el precio total de una lista de productos con sus cantidades usando los precios reales del sistema. Usalo siempre al recomendar productos.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'items' => [
                                'type'  => 'array',
                                'items' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'descrip'   => ['type' => 'string', 'description' => 'Nombre del producto'],
                                        'cantidad'  => ['type' => 'number', 'description' => 'Kg si es por peso, unidades si es por unidad'],
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
                    'name'        => 'cancelar_pedido',
                    'description' => 'Cancela un pedido pendiente del cliente.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'nro' => [
                                'type'        => 'integer',
                                'description' => 'Número de pedido a cancelar.',
                            ],
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
                'crear_pedido'    => $this->createOrder($cliente, $args['items'] ?? [], $args['fecha_entrega'] ?? now()->addDay()->format('Y-m-d'), $args['obs'] ?? ''),
                'ver_pedidos'     => $this->orderStatus($cliente),
                'ver_precios'     => $this->priceList(),
                'calcular_total'  => $this->calcularTotal($args['items'] ?? []),
                'cancelar_pedido' => $this->cancelOrder($cliente, (int) ($args['nro'] ?? 0)),
                default           => 'Función desconocida.',
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

    private function createOrder($client, array $items, string $fechaEntrega = '', string $obs = ''): string
    {
        if (empty($items)) {
            return 'Sin artículos para registrar.';
        }

        $nro      = (Pedido::max('nro') ?? 0) + 1;
        $fecha    = $fechaEntrega ?: now()->format('Y-m-d');
        $codcli   = $client->cuenta ? $client->cuenta->cod : $client->id;
        $nomcli   = $client->cuenta ? $client->cuenta->nom : $client->name;

        // Cache de productos con tipo para no hacer N queries
        $productos = Cache::remember(
            'productos_bot_precios',
            300,
            fn() =>
            Producto::where('PRE', '>', 0)->get(['des', 'PRE', 'tipo', 'imagen'])
        );

        foreach ($items as $item) {
            $cantidad = (float) ($item['cantidad'] ?? $item['kilos'] ?? 0);

            // Buscar tipo del producto
            $producto = $productos->first(
                fn($p) =>
                stripos($p->des, $item['descrip']) !== false ||
                    stripos($item['descrip'], $p->des) !== false
            );

            $esPeso = !$producto || $producto->tipo !== 'Unidad';
            $precio = $producto ? (float) $producto->PRE : 0;
            $neto   = round($precio * $cantidad, 2);

            Pedido::create([
                'fecha'   => $fecha,
                'nro'     => $nro,
                'nomcli'  => $nomcli,
                'codcli'  => $codcli,
                'descrip' => $item['descrip'],
                'kilos'   => $esPeso ? $cantidad : 0,
                'cant'    => $esPeso ? 1           : (int) $cantidad,
                'precio'  => $precio,
                'neto'    => $neto,
                'estado'    => Pedido::ESTADO_PENDIENTE,
                'obs'       => $obs,
                'pedido_at' => now(),
                'venta'     => 0,
            ]);
        }

        // Enviar imagen de cada producto que tenga una
        $this->enviarImagenesProductos($client, $productos, $items);

        $resumen = implode(', ', array_map(fn($i) => "{$i['cantidad']} {$i['descrip']}", $items));

        return "Pedido #{$nro} registrado: {$resumen}.";
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

        return $pedidos->map(
            fn($p) => "#{$p->nro} ({$p->fecha}): {$p->descrip} {$p->kilos}kg — {$p->estado_texto}"
        )->implode("\n");
    }

    private function calcularTotal(array $items): string
    {
        if (empty($items)) {
            return 'No hay productos para calcular.';
        }

        $productos = Cache::remember(
            'productos_bot_precios',
            300,
            fn() =>
            Producto::where('PRE', '>', 0)->get(['des', 'PRE', 'tipo', 'imagen'])
        );

        $lineas = [];
        $total  = 0;

        foreach ($items as $item) {
            $descrip  = $item['descrip'];
            $cantidad = (float) ($item['cantidad'] ?? $item['kilos'] ?? 0);

            $match = $productos->first(
                fn($p) =>
                stripos($p->des, $descrip) !== false ||
                    stripos($descrip, $p->des) !== false
            );

            if ($match) {
                $esPeso   = $match->tipo !== 'unidad';
                $unidad   = $esPeso ? 'kg' : 'u';
                $subtotal = round($match->PRE * $cantidad, 2);
                $total   += $subtotal;
                $precioFmt    = number_format($match->PRE, 2, ',', '.');
                $subtotalFmt  = number_format($subtotal, 2, ',', '.');
                $lineas[] = "{$descrip} {$cantidad}{$unidad} × {$precioFmt} $ = {$subtotalFmt} $";
            } else {
                $lineas[] = "{$descrip} {$cantidad} × precio no disponible";
            }
        }

        $lineas[] = "TOTAL ESTIMADO: " . number_format($total, 2, ',', '.') . " $";

        return implode("\n", $lineas);
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

    private function enviarImagenesProductos($client, $productos, array $items): void
    {
        foreach ($items as $item) {
            $producto = $productos->first(
                fn($p) => stripos($p->des, $item['descrip']) !== false ||
                          stripos($item['descrip'], $p->des) !== false
            );

            if (!$producto || empty($producto->imagen) || $producto->imagen === 'sinimagen.webp') {
                continue;
            }

            $path = public_path($producto->imagen);

            if (!file_exists($path)) {
                continue;
            }

            try {
                $mime    = mime_content_type($path) ?: 'image/jpeg';
                $mediaId = $this->uploadMediaFromPath($path, $mime);
                $this->sendWhatsappMedia($client->phone, $mediaId, 'image', $producto->des);
            } catch (\Throwable $e) {
                Log::error("enviarImagenesProductos {$producto->des}: {$e->getMessage()}");
            }
        }
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
                ? "{$p->des} — " . number_format($p->PRE, 2, ',', '.') . " \$/u"
                : "{$p->des} — " . number_format($p->PRE, 2, ',', '.') . " \$/kg"
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