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
     */
    public function direct(Request $request)
    {
        return $this->process($request->json()->all());
    }

    /**
     * POST /api/handle — El gateway reenvía el payload aquí.
     * Autentica con GATEWAY_SECRET y activa el tenant correcto.
     */
    public function handle(Request $request)
    {
        $secret = config('api.webhook.secret');

        if ($secret && $request->bearerToken() !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->process($request->json()->all());
    }

    /**
     * Lógica compartida: identifica el tenant, procesa el mensaje.
     */
    private function process(array $body)
    {
        try {
            $value         = data_get($body, 'entry.0.changes.0.value');
            $phoneNumberId = data_get($value, 'metadata.phone_number_id');

            // Activar el tenant según el número de WhatsApp que recibió el mensaje
            $manager = app(TenantManager::class);
            if (!$phoneNumberId || !$manager->loadByPhoneNumberId($phoneNumberId)) {
                Log::warning("Webhook: tenant no encontrado para phone_number_id [{$phoneNumberId}]");
                return response()->json(['status' => 'ignored']);
            }
            $tenantId = $manager->get()->id;

            if (empty($value['messages'])) {
                return response()->json(['status' => 'ignored']);
            }

            $bot   = app(BotService::class);
            $msg   = $value['messages'][0];
            $phone = $msg['from'];
            $type  = $msg['type'];
            $wamid = $msg['id'];

            $image   = null;
            $message = '';

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
                    'media_path' => $imgPath ?? null,
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

            Message::create([
                'cliente_id' => $client->id,
                'message'    => $reply,
                'direction'  => 'outgoing',
            ]);

            MessageLog::on('mysql')->create([
                'tenant_id'     => $tenantId,
                'phone'         => $phone,
                'type'          => $type,
                'message'       => $message,
                'reply'         => $reply,
                'enviado'       => true,
            ]);

            return response()->json(['status' => 'ok']);

        } catch (\Throwable $ex) {
            Log::error('WebhookController error: ' . $ex->getMessage(), ['exception' => $ex]);
            if (!empty($phone)) {
                app(BotService::class)->sendWhatsapp(
                    $phone,
                    'Ocurrió un error procesando tu mensaje. Por favor intentá de nuevo en unos minutos.'
                );
            }
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }
}
