<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Models\Cliente;
use App\Models\Message;
use App\Models\Seguimiento;
use App\Services\BotService;
use App\Services\TenantManager;

use Exception;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Verificación GET que Meta envía al registrar el webhook.
     * Identifica el tenant por el webhook_token para validar que existe.
     */
    public function index(Request $request)
    {
        try {
            $token     = $request->query('hub_verify_token');
            $mode      = $request->query('hub_mode');
            $challenge = $request->query('hub_challenge');

            if ($mode !== 'subscribe' || !$token) {
                throw new Exception('Invalid request');
            }

            // Buscar el tenant por su webhook_token
            $manager = app(TenantManager::class);
            if (!$manager->loadByWebhookToken($token)) {
                throw new Exception('Unknown token');
            }

            return response($challenge, 200)->header('Content-Type', 'text/plain');

        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }

    /**
     * Recibe los mensajes entrantes de WhatsApp.
     * El phone_number_id del payload identifica a qué carnicería pertenece.
     */
    public function store(Request $request)
    {
        try {
            $bodyContent   = json_decode($request->getContent(), true);
            $value         = $bodyContent['entry'][0]['changes'][0]['value'];
            $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

            // Identificar y activar el tenant
            $manager = app(TenantManager::class);
            if (!$phoneNumberId || !$manager->loadByPhoneNumberId($phoneNumberId)) {
                Log::warning('Webhook: tenant no encontrado', ['phone_number_id' => $phoneNumberId]);
                return response()->json(['status' => 'ignored']);
            }

            if (empty($value['messages'])) {
                return response()->json(['status' => 'ignored']);
            }

            $tenant = $manager->get();
            $bot    = new BotService($tenant->whatsapp_api_key, $tenant->openai_api_key, $tenant->phone_number_id);

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
                $imgPath = 'chat-images/' . md5($wamid) . '.jpg';
                if (!is_dir(public_path('chat-images'))) {
                    mkdir(public_path('chat-images'), 0755, true);
                }
                file_put_contents(public_path($imgPath), base64_decode($image['base64']));
            } else {
                $client = Cliente::firstOrCreate(['phone' => $phone]);
                $bot->sendWhatsapp($phone, "Por ahora solo proceso texto, voz e imágenes. 😊");
                return response()->json(['status' => 'unsupported_type']);
            }

            $client = Cliente::firstOrCreate(['phone' => $phone]);

            // Guardado atómico: la constraint UNIQUE en wamid rechaza duplicados a nivel DB.
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

            // Si el cliente responde, marcar seguimientos pendientes como respondidos
            Seguimiento::where('cliente_id', $client->id)
                ->where('respondio', false)
                ->update(['respondio' => true]);

            // Si el admin tomó control, no responde el bot
            if ($client->modo === 'humano') {
                return response()->json(['status' => 'human_mode']);
            }

            $reply = $bot->process($client, $message, $image);

            Message::create([
                'cliente_id' => $client->id,
                'message'    => $reply,
                'direction'  => 'outgoing',
            ]);

            return response()->json(['status' => 'ok']);

        } catch (Exception $ex) {
            Log::error('Webhook error: ' . $ex->getMessage(), [
                'exception' => $ex,
                'phone'     => $phone ?? null,
            ]);
            if (!empty($phone) && isset($bot)) {
                $bot->sendWhatsapp(
                    $phone,
                    'Ocurrió un error procesando tu mensaje. Por favor intentá de nuevo en unos minutos.'
                );
            }
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }
}
