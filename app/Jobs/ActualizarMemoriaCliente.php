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
            $pagos = Pedido::where('pedidos.codcli', $codcli)
                ->join('ia_pedidos', 'pedidos.nro', '=', 'ia_pedidos.nro')
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

        $systemPrompt = 'Sos un asistente que analiza conversaciones y pedidos de un cliente de carnicería/distribuidora. '
            . 'Devolvé ÚNICAMENTE el resumen en este formato estructurado (omitir secciones sin datos concretos):'
            . "\n- Productos habituales: [nombre cantidad unidad, ...] (máx 5, con cantidades típicas si las hay)"
            . "\n- Día preferido: [día de la semana o 'sin preferencia detectada']"
            . "\n- Forma de pago habitual: [forma o 'no identificada']"
            . "\n- Tipo de cliente: [particular / revendedor / sin datos]"
            . "\n- Rechazos y preferencias negativas: [productos caros, rechazados, alternativas pedidas]"
            . "\n- Observaciones: [máx 2 líneas — comportamiento, frecuencia, particularidades]"
            . "\n\nReglas: NO incluyas días de reparto ni zonas (lo maneja el sistema). NO incluyas nombre ni dirección. "
            . "Usá datos concretos y cuantificados cuando existan. Si hay memoria previa, actualizá o confirmá cada sección con la nueva información. "
            . "Si no hay información nueva relevante, devolvé la memoria previa sin cambios.";

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
