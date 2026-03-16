<?php

namespace App\Services;

// use App\Models\Order;
// use App\Models\Product;
use App\Models\Producto;
use Illuminate\Support\Facades\Http;


class BotService
{
    public function process($client, $message)
    {
        $message = strtolower($message);

        if (str_contains($message, 'precio')) {
            return $this->priceList();
        }

        if (str_contains($message, 'pedido')) {
            return $this->createOrder($client, $message);
        }

        return $this->askClaude($message);
    }

    private function priceList()
    {
        $products = Producto::where("pre",">","10000")->get();

        $text = "Lista de precios:\n";

        foreach ($products as $p) {
            $text .= $p->des . " - $ " . $p->PRE . "\n";
        }

        return $text;
    }

    private function createOrder($client, $message)
    {
        // Order::create([
        //     'client_id' => $client->id,
        //     'order_text' => $message,
        //     'status' => 'pending'
        // ]);

        return "Pedido recibido. En breve lo confirmamos.";
    }

    public function askClaude($message)
    {
        $response = Http::withHeaders([
            'x-api-key' => env('CLAUDE_API_KEY'),
            'anthropic-version' => '2023-06-01'
        ])->post('https://api.anthropic.com/v1/messages', [
            "model" => "claude-3-haiku-20240307",
            "max_tokens" => 200,
            "messages" => [
                [
                    "role" => "user",
                    "content" => "Eres el asistente de una carnicería.
                    Funciones:
                    - tomar pedidos
                    - informar precios
                    - responder consultas
                    Reglas:
                    - respuestas cortas
                    - si el cliente quiere comprar, pedir cantidad
                    - tono amable
                    :" . $message
                ]
            ]
        ]);

        return $response['content'][0]['text'];
    }

   
}
