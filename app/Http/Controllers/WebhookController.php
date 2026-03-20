<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Message;
use App\Models\MessageLog;
use App\Models\Seguimiento;
use App\Services\BotService;
use App\Services\TenantManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * GET — Verificación del webhook (directo sin gateway, útil en dev).
     * Identifica el negocio por el webhook_token que envía Meta.
     */
    public function verify(Request $request)
    {
        $token     = $request->query('hub_verify_token');
        $mode      = $request->query('hub_mode');
        $challenge = $request->query('hub_challenge');

        if ($mode !== 'subscribe' || !$token) {
            return response()->json(['error' => 'Invalid request'], 403);
        }

        if (!app(TenantManager::class)->loadByWebhookToken($token)) {
            return response()->json(['error' => 'Unknown token'], 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * POST /api/webhook — Meta apunta directo aquí (dev / sin gateway).
     * Detecta el canal por el campo "object" del payload.
     */
    public function direct(Request $request)
    {
        $body   = $request->json()->all();
        $object = $body['object'] ?? null;

        $canal = match ($object) {
            'page'      => 'messenger',
            'instagram' => 'instagram',
            default     => 'whatsapp',
        };

        return $this->process($body, $canal);
    }

    /**
     * POST /api/handle — El gateway reenvía el payload aquí.
     * Autentica con GATEWAY_SECRET y activa el tenant correcto.
     * El gateway envía el canal en el header X-Canal.
     */
    public function handle(Request $request)
    {
        $secret = config('api.webhook.secret');

        if ($secret && $request->bearerToken() !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $canal = $request->header('X-Canal', 'whatsapp');

        return $this->process($request->json()->all(), $canal);
    }

    /**
     * Lógica compartida: identifica el tenant, parsea según canal, procesa el mensaje.
     */
    private function process(array $body, string $canal = 'whatsapp')
    {
        try {
            $manager = app(TenantManager::class);

            if ($canal === 'messenger' || $canal === 'instagram') {
                return $this->processMessenger($body, $canal, $manager);
            }

            return $this->processWhatsapp($body, $manager);

        } catch (\Throwable $ex) {
            Log::error('WebhookController error: ' . $ex->getMessage(), ['exception' => $ex]);
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    // ── WhatsApp ─────────────────────────────────────────────────────────────

    private function processWhatsapp(array $body, TenantManager $manager)
    {
        $value         = data_get($body, 'entry.0.changes.0.value');
        $phoneNumberId = data_get($value, 'metadata.phone_number_id');

        if (!$phoneNumberId || !$manager->loadByPhoneNumberId($phoneNumberId)) {
            Log::warning("Webhook: tenant no encontrado para phone_number_id [{$phoneNumberId}]");
            return response()->json(['status' => 'ignored']);
        }

        $tenantId = $manager->get()->id;

        // Solo status updates (sin mensaje entrante)
        if (empty($value['messages'])) {
            if (!empty($value['statuses'])) {
                foreach ($value['statuses'] as $status) {
                    $wamidStatus = $status['id']     ?? null;
                    $newStatus   = $status['status'] ?? null;
                    if ($wamidStatus && $newStatus) {
                        Message::where('wamid', $wamidStatus)->update(['status' => $newStatus]);
                    }
                }
            }
            return response()->json(['status' => 'ignored']);
        }

        $bot   = app(BotService::class);
        $msg   = $value['messages'][0];
        $phone = $msg['from'];
        $type  = $msg['type'];
        $wamid = $msg['id'];

        $image   = null;
        $message = '';
        $imgPath = null;

        if ($type === 'text') {
            $message = $msg['text']['body'];
        } elseif ($type === 'audio') {
            $message = $bot->transcribeAudio($msg['audio']['id']);
            if (empty($message)) {
                return response()->json(['status' => 'empty_transcription']);
            }
        } elseif ($type === 'image') {
            $image   = $bot->downloadWhatsappMedia($msg['image']['id']);
            $message = $msg['image']['caption'] ?? '';
            $imgPath = "chat-images/{$tenantId}/" . md5($wamid) . '.jpg';
            if (!is_dir(public_path("chat-images/{$tenantId}"))) {
                mkdir(public_path("chat-images/{$tenantId}"), 0755, true);
            }
            file_put_contents(public_path($imgPath), base64_decode($image['base64']));
        } else {
            $client = Cliente::firstOrCreate(['phone' => $phone]);
            $bot->sendWhatsapp($phone, "Por ahora solo proceso texto, voz e imágenes. 😊");
            return response()->json(['status' => 'unsupported_type']);
        }

        $client = Cliente::firstOrCreate(['phone' => $phone]);

        try {
            Message::create([
                'cliente_id' => $client->id,
                'message'    => $image ? ($message ?: '') : $message,
                'direction'  => 'incoming',
                'type'       => $type,
                'wamid'      => $wamid,
                'media_path' => $imgPath,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate']);
        }

        Seguimiento::where('cliente_id', $client->id)
            ->where('respondio', false)
            ->update(['respondio' => true]);

        if ($client->modo === 'humano') {
            return response()->json(['status' => 'human_mode']);
        }

        $reply = $bot->process($client, $message, $image);

        $this->saveOutgoingMessage($client->id, $reply, $bot->lastOutgoingWamid);

        MessageLog::on('mysql')->create([
            'tenant_id' => $tenantId,
            'phone'     => $phone,
            'type'      => $type,
            'message'   => $message,
            'reply'     => $reply,
            'enviado'   => true,
        ]);

        // Registrar saliente en el gateway
        $gatewayLogUrl = config('api.webhook.log_url');
        if ($gatewayLogUrl && $bot->lastOutgoingWamid) {
            try {
                Http::withToken(config('api.webhook.secret'))
                    ->timeout(3)
                    ->post($gatewayLogUrl, [
                        'phone_number_id' => config('api.whatsapp.phone_number_id'),
                        'to'              => $phone,
                        'wamid'           => $bot->lastOutgoingWamid,
                        'message'         => $reply,
                    ]);
            } catch (\Throwable $e) {
                Log::debug('Gateway log-outgoing falló: ' . $e->getMessage());
            }
        }

        return response()->json(['status' => 'ok']);
    }

    // ── Messenger / Instagram ─────────────────────────────────────────────────

    private function processMessenger(array $body, string $canal, TenantManager $manager)
    {
        $pageId = data_get($body, 'entry.0.id');

        if (!$pageId || !$manager->loadByPageId($pageId)) {
            Log::warning("Webhook: tenant no encontrado para page_id [{$pageId}] (canal: {$canal})");
            return response()->json(['status' => 'ignored']);
        }

        $tenantId = $manager->get()->id;
        $bot      = app(BotService::class);

        $messaging = data_get($body, 'entry.0.messaging.0');
        if (!$messaging) {
            return response()->json(['status' => 'ignored']);
        }

        // Ignorar delivery/read confirmations (no tienen 'message')
        if (empty($messaging['message'])) {
            return response()->json(['status' => 'ignored']);
        }

        $senderId = $messaging['sender']['id'] ?? null;
        $mid      = $messaging['message']['mid'] ?? null;
        $msgText  = $messaging['message']['text'] ?? '';

        if (!$senderId) {
            return response()->json(['status' => 'ignored']);
        }

        // Prefijo para diferenciar canal en el campo phone
        $prefix = $canal === 'instagram' ? 'ig_' : 'fb_';
        $phone  = $prefix . $senderId;

        $client = Cliente::firstOrCreate(['phone' => $phone]);

        try {
            Message::create([
                'cliente_id' => $client->id,
                'message'    => $msgText,
                'direction'  => 'incoming',
                'type'       => 'text',
                'wamid'      => $mid,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate']);
        }

        Seguimiento::where('cliente_id', $client->id)
            ->where('respondio', false)
            ->update(['respondio' => true]);

        if ($client->modo === 'humano') {
            return response()->json(['status' => 'human_mode']);
        }

        $reply = $bot->process($client, $msgText, null);

        $this->saveOutgoingMessage($client->id, $reply, null);

        MessageLog::on('mysql')->create([
            'tenant_id' => $tenantId,
            'phone'     => $phone,
            'type'      => 'text',
            'message'   => $msgText,
            'reply'     => $reply,
            'enviado'   => true,
        ]);

        return response()->json(['status' => 'ok']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function saveOutgoingMessage(int $clienteId, string $reply, ?string $wamid): void
    {
        try {
            Message::create([
                'cliente_id' => $clienteId,
                'message'    => $reply,
                'direction'  => 'outgoing',
                'wamid'      => $wamid,
                'status'     => $wamid ? 'sent' : null,
            ]);
        } catch (\Throwable $msgEx) {
            Message::create([
                'cliente_id' => $clienteId,
                'message'    => $reply,
                'direction'  => 'outgoing',
            ]);
            Log::warning('Message::create fallback (migración pendiente?): ' . $msgEx->getMessage());
        }
    }
}
