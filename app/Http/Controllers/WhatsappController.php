<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Message;
use App\Services\BotService;
use Exception;

class WhatsappController extends Controller
{
    public function webhook(Request $request)
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

            return $this->sendWhatsapp($phone, $reply);
        } catch (Exception $ex) {

            return $ex->getMessage();
        }
    }

    private function sendWhatsapp($phone, $message)
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
            $accessToken = config(env('WHATSAPP_KEY'));

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
