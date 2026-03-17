<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Producto;
use App\Models\Message;


class BotService
{
    public function process($client, $message)
    {

        $nombre = $client->name ?? "";
        $message = strtolower($message);
        $response = "";

        if (str_contains($message, 'precio')) {
            $response = $this->priceList();
        }

        if (str_contains($message, 'pedido') && $response == "") {
            $response = $this->createOrder($client, $message);
        }

        if($response == ""){
            $response = $this->askChatGPT($message, $client);
        }
       
        return $this->sendWhatsapp($client->phone,$response);

    }

    private function priceList()
    {
        $products = Producto::where("pre", ">", "10000")->get();

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

    public function askChatGPT($message, $cliente)
    {

        $productos = Producto::select('des', 'PRE')->get();
        $lista = "";

        foreach ($productos as $p) {
            $lista .= $p->des . " $ " . $p->PRE . "\n";
        }

        // $cliente = Cliente::where('phone',$phone)->first();
        $nombre = $cliente->name ?? "cliente";

        $history = Message::where('cliente_id', $cliente->id)
            ->latest()
            ->take(5)
            ->get()
            ->reverse();

        $ultimoPedido = null;

        $clienteContexto = "
        Cliente: {$nombre}
        
        Último pedido:
        " . ($ultimoPedido ? $ultimoPedido->notes : "ninguno") . "
        ";

        $messages = [];

        $messages[] = [
            "role" => "system",
            "content" => "
            Eres el asistente de una carnicería.
            
            Productos disponibles:
            {$lista}
            
            Información del cliente:
            {$clienteContexto}
            
            Funciones:
                - tomar pedidos
                - informar precios
                - responder consultas

            Reglas:
            - responde corto
            - ayuda a hacer pedidos
            - usa los precios reales
            "
        ];

        foreach ($history as $msg) {

            $messages[] = [
                "role" => $msg->direction == "incoming" ? "user" : "assistant",
                "content" => $msg->message
            ];
        }

        $messages[] = [
            "role" => "user",
            "content" => $message
        ];

        $response = Http::withToken(config('api.openai.key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                "model" => "gpt-4o-mini",
                "messages" => $messages,
                "max_tokens" => 200
            ]);

        return $response['choices'][0]['message']['content'];
    }

    public function sendWhatsapp($phone, $message)
    {

        try {
            // URL a la que deseas enviar la solicitud

            $url = 'https://graph.facebook.com/v19.0/295131097015095/messages';

            // Datos que deseas enviar en la solicitud (si los hay)

            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $phone, //$request->numero
                'type' => 'text',
                "text" => [
                    "body" => $message
                ]
            ];

            // Convertir el array de datos a formato JSON
            $jsonData = json_encode($data);

            // Token de acceso para la autenticación
            $accessToken = config('api.whatsapp.key');

            // Inicializar cURL
            $ch = curl_init();

            // Establecer opciones de cURL
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Agregar encabezado de autorización con el token de acceso
            // Agregar encabezado de autorización con el token de acceso
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json', // Especificar el tipo de contenido como JSON
                'Content-Length: ' . strlen($jsonData), // Especificar la longitud del JSON
            ]);

            // Ejecutar la solicitud y obtener la respuesta
            $response = curl_exec($ch);

            // Verificar si ocurrió algún error
            if ($response === false) {
                $error = curl_error($ch);
                // Manejar el error
                return response()->json(['error' => $error], 500);
            }

            // Cerrar la sesión de cURL
            curl_close($ch);

            // Devolver la respuesta
            //return response()->json($response);
        } catch (\Throwable $th) {
            // return $th;
        }
    }
}
