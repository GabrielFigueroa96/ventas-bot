<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Message;
use App\Services\BotService;

use Exception;

class WebhookController extends Controller
{
    public function index(Request $request)
    {
        try {
            $verifyToken = config('api.webhook.token');
            $query = $request->query();

            $mode = $query['hub_mode'] ?? null;
            $token = $query['hub_verify_token'] ?? null;
            $challenge = $query['hub_challenge'] ?? null;

            if ($mode && $token) {
                if ($mode === 'subscribe' && $token === $verifyToken) {
                    return response($challenge, 200)->header('Content-Type', 'text/plain');
                }
            }

            throw new Exception('Invalid request');
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {

        try {
            $bodyContent = json_decode($request->getContent(), true);
            $value       = $bodyContent['entry'][0]['changes'][0]['value'];

            if (empty($value['messages'])) {
                return response()->json(['status' => 'ignored']);
            }

            $msg   = $value['messages'][0];
            $phone = $msg['from'];
            $type  = $msg['type'];
            $wamid = $msg['id'];
            $bot   = app(BotService::class);

            // Ignorar si ya procesamos este mensaje (webhook duplicado)
            if (Message::where('wamid', $wamid)->exists()) {
                return response()->json(['status' => 'duplicate']);
            }

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
            } else {
                $client = Cliente::firstOrCreate(['phone' => $phone]);
                $bot->sendWhatsapp($phone, "Por ahora solo proceso texto, voz e imágenes. 😊");
                return response()->json(['status' => 'unsupported_type']);
            }

            $client = Cliente::firstOrCreate(['phone' => $phone]);

            Message::create([
                'cliente_id' => $client->id,
                'message'    => $image ? '[Imagen recibida]' . ($message ? ": {$message}" : '') : $message,
                'direction'  => 'incoming',
                'type'       => $type,
                'wamid'      => $wamid,
            ]);

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
            return response()->json(['error' => $ex->getMessage()], 500);
        }

    }
    
}
