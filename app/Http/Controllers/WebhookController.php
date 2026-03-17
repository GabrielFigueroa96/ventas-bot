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
            $value = $bodyContent['entry'][0]['changes'][0]['value'];

            if (!empty($value['messages'])) {
                if ($value['messages'][0]['type'] == 'text') {
                    $message = $value['messages'][0]['text']['body'];
                    $phone = $value['messages'][0]['from'];
                }
            }

            $client = Cliente::firstOrCreate(
                ['phone' => $phone]
            );

            Message::create([
                'cliente_id' => $client->id,
                'message' => $message,
                'direction' => 'incoming'
            ]);

            $reply = app(BotService::class)->process($client, $message);

            Message::create([
                'cliente_id' => $client->id,
                'message' => $reply,
                'direction' => 'outgoing'
            ]);

            return $reply;

        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }
}
