<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Models\Message;
use App\Models\Pedido;
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
            ->take(80)
            ->get()
            ->reverse()
            ->map(fn($m) => ($m->direction === 'incoming' ? 'Cliente' : 'Bot') . ': ' . $m->message)
            ->implode("\n");

        if (empty(trim($mensajes))) return;

        $memoriaActual = $cliente->memoria_ia ?? '';

        // Contexto de pedidos históricos
        $codcli = $cliente->cuenta?->cod ?? $cliente->id;
        $pedidos = Pedido::where('codcli', $codcli)->get();
        $pedidoContexto = '';
        if ($pedidos->isNotEmpty()) {
            $totalPedidos  = $pedidos->groupBy('nro')->count();
            $primerPedido  = $pedidos->min('fecha');
            $ultimoPedido  = $pedidos->max('fecha');
            $pagos = Pedido::where('codcli', $codcli)
                ->join('ia_pedidos', 'tablapedidos.nro', '=', 'ia_pedidos.nro')
                ->selectRaw('ia_pedidos.forma_pago, COUNT(*) as veces')
                ->groupBy('ia_pedidos.forma_pago')
                ->orderByDesc('veces')
                ->value('forma_pago');

            $pedidoContexto = "Historial de pedidos: {$totalPedidos} pedidos totales"
                . ($primerPedido && $ultimoPedido ? ", desde {$primerPedido} hasta {$ultimoPedido}" : '')
                . ($pagos ? ". Forma de pago más usada: {$pagos}" : '') . ".";

            // Frecuencia aproximada
            if ($totalPedidos >= 2) {
                $dias = \Carbon\Carbon::parse($primerPedido)->diffInDays(\Carbon\Carbon::parse($ultimoPedido));
                $frecuencia = round($dias / ($totalPedidos - 1));
                if ($frecuencia > 0) {
                    $pedidoContexto .= " Frecuencia promedio: cada {$frecuencia} días.";
                }
            }
        }

        $systemPrompt = 'Sos un asistente que analiza conversaciones de clientes de una carnicería/distribuidora y extrae información útil para personalizar futuras interacciones. '
            . 'Devolvé ÚNICAMENTE una lista de puntos concisos (máx. 10), cada uno comenzando con "- ". '
            . 'Incluí datos concretos y útiles sobre EL CLIENTE: productos favoritos, cantidades habituales, frecuencia de pedido, forma de pago preferida, tipo de cliente (particular/revendedor), observaciones importantes. '
            . 'MUY IMPORTANTE: si el cliente rechazó productos, expresó que algo es caro, no le gustó algo o pidió alternativas, incluílo — esa información es igual de útil para personalizar. '
            . 'NO incluyas información del negocio (días de reparto, zonas de entrega, horarios, precios) — esos datos los maneja el sistema por separado. '
            . 'No incluyas datos de dirección ni nombre (ya están en el perfil). '
            . 'Si hay una memoria previa, actualizá o confirmá los puntos existentes con la nueva información. '
            . 'Si no hay información útil nueva, devolvé la memoria previa sin cambios.';

        $userPrompt = ($memoriaActual ? "Memoria actual del cliente:\n{$memoriaActual}\n\n" : '')
            . ($pedidoContexto ? "Contexto de pedidos:\n{$pedidoContexto}\n\n" : '')
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
                    'max_tokens'  => 400,
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
