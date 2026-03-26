<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActualizarMemoriaCliente implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $clienteId) {}

    public function handle(): void
    {
        $cliente = Cliente::find($this->clienteId);
        if (!$cliente) return;

        $mensajes = Message::where('cliente_id', $cliente->id)
            ->latest()
            ->take(40)
            ->get()
            ->reverse()
            ->map(fn($m) => ($m->direction === 'incoming' ? 'Cliente' : 'Bot') . ': ' . $m->message)
            ->implode("\n");

        if (empty(trim($mensajes))) return;

        $memoriaActual = $cliente->memoria_ia ?? '';

        $systemPrompt = 'Sos un asistente que analiza conversaciones de clientes de una carnicería/distribuidora y extrae información útil para personalizar futuras interacciones. '
            . 'Devolvé ÚNICAMENTE una lista de puntos concisos (máx. 8), cada uno comenzando con "- ". '
            . 'Incluí solo datos concretos y útiles: productos favoritos, cantidades habituales, días/frecuencia de pedido, forma de pago preferida, tipo de cliente (particular/revendedor), observaciones importantes. '
            . 'No incluyas datos de dirección ni nombre (ya están en el perfil). '
            . 'Si hay una memoria previa, actualizá o confirmá los puntos existentes con la nueva información. '
            . 'Si no hay información útil nueva, devolvé la memoria previa sin cambios.';

        $userPrompt = ($memoriaActual ? "Memoria actual del cliente:\n{$memoriaActual}\n\n" : '')
            . "Conversación reciente:\n{$mensajes}";

        try {
            $apiKey = config('api.openai.key');
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => 'gpt-4.1-mini',
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userPrompt],
                    ],
                    'max_tokens'  => 300,
                    'temperature' => 0.2,
                ])
                ->json();

            $memoria = trim($response['choices'][0]['message']['content'] ?? '');
            if ($memoria) {
                $cliente->update(['memoria_ia' => $memoria]);
                Log::info("ActualizarMemoriaCliente #{$cliente->id}: memoria actualizada.");
            }
        } catch (\Throwable $e) {
            Log::warning("ActualizarMemoriaCliente #{$cliente->id} error: " . $e->getMessage());
        }
    }
}
