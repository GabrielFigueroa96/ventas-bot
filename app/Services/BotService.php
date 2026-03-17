<?php

namespace App\Services;

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

        $lista = Producto::select('des', 'PRE')->get()
            ->map(fn($p) => "{$p->des} \${$p->PRE}")
            ->implode("\n");

        $codcli       = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
        $ultimoPedido = Pedido::where('codcli', $codcli)->latest('reg')->first();
        $ultimoPedidoTexto = $ultimoPedido
            ? "#{$ultimoPedido->nro} ({$ultimoPedido->fecha}): {$ultimoPedido->descrip} — {$ultimoPedido->estado_texto}"
            : 'ninguno';

        $history = Message::where('cliente_id', $cliente->id)
            ->latest()
            ->take(6)
            ->get()
            ->reverse();

        $messages = [];

        $messages[] = [
            'role'    => 'system',
            'content' => "Eres el asistente de una carnicería. Sos amable, breve y directo.

Productos disponibles:
{$lista}

Cliente: {$nombre}
Último pedido: {$ultimoPedidoTexto}

Reglas:
- Si el cliente quiere hacer un pedido, preguntá qué productos y cantidades necesita.
- Confirmá el pedido con el cliente ANTES de llamar a crear_pedido (ej: '¿Confirmás: 2kg asado y 1 pollo?').
- Solo llamá a crear_pedido cuando el cliente confirme explícitamente (sí, dale, confirmo, etc.).
- Usá ver_precios cuando pregunten por precios o lista de productos.
- Usá ver_pedidos cuando pregunten por el estado o historial de sus pedidos.
- Si recibís una imagen, describí lo que ves e intentá relacionarlo con un pedido o consulta.
- Respondé siempre en español, de forma corta y amigable.",
        ];

        foreach ($history as $msg) {
            $messages[] = [
                'role'    => $msg->direction === 'incoming' ? 'user' : 'assistant',
                'content' => $msg->message,
            ];
        }

        // Si viene con imagen, el mensaje del usuario es multimodal (texto + imagen)
        if ($image) {
            $userContent = [];

            if ($message) {
                $userContent[] = ['type' => 'text', 'text' => $message];
            }

            $userContent[] = [
                'type'      => 'image_url',
                'image_url' => [
                    'url'    => "data:{$image['mime']};base64,{$image['base64']}",
                    'detail' => 'auto',
                ],
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
                                            'description' => 'Nombre del producto con unidad (ej: "asado kg", "pollo unidad").',
                                        ],
                                        'kilos' => [
                                            'type'        => 'number',
                                            'description' => 'Cantidad (kilos o unidades según corresponda).',
                                        ],
                                    ],
                                    'required' => ['descrip', 'kilos'],
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
        ];
    }

    // -------------------------------------------------------------------------
    // Ejecución de la función elegida por ChatGPT
    // -------------------------------------------------------------------------

    private function handleToolCalls(array $choice, array $messages, $cliente): string
    {
        $toolCall = $choice['message']['tool_calls'][0];
        $funcName = $toolCall['function']['name'];
        $args     = json_decode($toolCall['function']['arguments'], true) ?? [];

        $result = match ($funcName) {
            'crear_pedido' => $this->createOrder($cliente, $args['items'] ?? []),
            'ver_pedidos'  => $this->orderStatus($cliente),
            'ver_precios'  => $this->priceList(),
            default        => 'Función desconocida.',
        };

        // Agregamos el mensaje del asistente (con el tool_call) y el resultado
        $messages[] = $choice['message'];
        $messages[] = [
            'role'         => 'tool',
            'tool_call_id' => $toolCall['id'],
            'content'      => $result,
        ];

        // Segunda llamada: ChatGPT transforma el resultado en lenguaje natural
        $response = $this->callOpenAI($messages);

        return $response['choices'][0]['message']['content'];
    }

    // -------------------------------------------------------------------------
    // Acciones del negocio
    // -------------------------------------------------------------------------

    private function createOrder($client, array $items): string
    {
        if (empty($items)) {
            return 'Sin artículos para registrar.';
        }

        $nro    = (Pedido::max('nro') ?? 0) + 1;
        $fecha  = now()->format('Y-m-d');
        $codcli = $client->cuenta ? $client->cuenta->cod : $client->id;
        $nomcli = $client->cuenta ? $client->cuenta->nom : $client->name;

        foreach ($items as $item) {
            Pedido::create([
                'fecha'   => $fecha,
                'nro'     => $nro,
                'nomcli'  => $nomcli,
                'codcli'  => $codcli,
                'descrip' => $item['descrip'],
                'kilos'   => $item['kilos'],
                'cant'    => 1,
                'estado'  => Pedido::ESTADO_PENDIENTE,
                'venta'   => 0,
            ]);
        }

        $resumen = implode(', ', array_map(fn($i) => "{$i['kilos']} {$i['descrip']}", $items));

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

    private function priceList(): string
    {
        $productos = Producto::where('PRE', '>', 0)->get();

        if ($productos->isEmpty()) {
            return 'No hay productos disponibles en este momento.';
        }

        return $productos->map(fn($p) => "{$p->des} — \${$p->PRE}")->implode("\n");
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
