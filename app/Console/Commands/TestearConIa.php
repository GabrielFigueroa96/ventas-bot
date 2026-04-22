<?php

namespace App\Console\Commands;

use App\Models\Carrito;
use App\Models\Cliente;
use App\Services\BotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Prueba el bot usando una IA que hace de cliente.
 *
 * Uso:
 *   php artisan bot:test-ia                              # objetivo por defecto: hacer un pedido
 *   php artisan bot:test-ia --objetivo="consultar estado de mi pedido"
 *   php artisan bot:test-ia --escenario=lo_mismo
 *   php artisan bot:test-ia --turnos=20 --limpiar
 */
class TestearConIa extends Command
{
    protected $signature = 'bot:test-ia
                            {--escenario=pedido : pedido, lo_mismo, consulta, registro}
                            {--objetivo= : Descripción libre del objetivo del cliente IA}
                            {--phone=5490000000099 : Teléfono del cliente de prueba}
                            {--turnos=20 : Máximo de intercambios}
                            {--limpiar : Borrar estado antes de empezar}
                            {--localidad= : ID de localidad a asignar al cliente}';

    protected $description = 'Prueba el bot con una IA jugando el rol de cliente';

    private const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';

    private array $escenarios = [
        'pedido' => [
            'objetivo'   => 'Hacer un pedido completo: elegir fecha, pedir uno o dos productos y confirmar.',
            'contexto'   => 'Sos un cliente habitual. Pedís normalmente carne o fiambres. Cuando el bot te muestre una lista de fechas elegí la primera que puedas. Cuando te muestre productos, pedí el primero de la lista en cantidad razonable (1 o 2 unidades, o 1 kg). Cuando el bot te pida confirmación, confirmá con "sí". Una vez que recibas la confirmación del pedido, finalizá la conversación.',
        ],
        'lo_mismo' => [
            'objetivo'   => 'Pedir "lo mismo de siempre" y confirmar.',
            'contexto'   => 'Sos un cliente habitual que quiere repetir su último pedido. Empezá diciendo "lo mismo de siempre". Cuando el bot te pida confirmación, confirmá. Si el bot no tiene historial, insistí de otra forma o pedí algo genérico.',
        ],
        'consulta' => [
            'objetivo'   => 'Consultar el estado de tu pedido.',
            'contexto'   => 'Sos un cliente que hizo un pedido ayer y quiere saber si ya fue enviado. Preguntá por el estado del pedido. Si el bot te pide datos para identificarte, dáselos o explicá que no los tenés.',
        ],
        'registro' => [
            'objetivo'   => 'Registrarte como cliente nuevo.',
            'contexto'   => 'Sos un cliente nuevo que nunca compró. Tu nombre es "Carlos Pérez", tu calle es "Belgrano 1234". Respondé las preguntas del bot para completar el registro.',
        ],
    ];

    public function handle(BotService $bot): int
    {
        $escNombre  = $this->option('escenario');
        $phone      = $this->option('phone');
        $maxTurnos  = (int) $this->option('turnos');
        $localidad  = $this->option('localidad');
        $objetivoOp = $this->option('objetivo');

        $esc = $this->escenarios[$escNombre] ?? $this->escenarios['pedido'];

        $objetivo = $objetivoOp ?: $esc['objetivo'];
        $contexto = $esc['contexto'];

        // Obtener o crear cliente de prueba
        $cliente = Cliente::firstOrCreate(
            ['phone' => $phone],
            ['name' => '🤖 Cliente IA Test', 'estado' => 'activo', 'modo' => 'bot']
        );

        if ($localidad) {
            $loc = \App\Models\Localidad::find($localidad);
            if ($loc) {
                $cliente->update(['localidad_id' => $loc->id, 'localidad' => $loc->nombre]);
                $cliente->refresh();
            }
        }

        if ($this->option('limpiar')) {
            $this->limpiarCliente($cliente);
            $this->line('<fg=yellow>⟳ Estado del cliente limpiado.</>');
        }

        $this->newLine();
        $this->line('<fg=cyan>══════════════════════════════════════════════════════</>');
        $this->line("<fg=cyan>  Bot Test IA — Escenario: {$escNombre}</>");
        $this->line("<fg=cyan>  Objetivo: {$objetivo}</>");
        $this->line("<fg=cyan>  Cliente: {$cliente->name} ({$cliente->phone})</>");
        if ($cliente->localidad) {
            $this->line("<fg=cyan>  Localidad: {$cliente->localidad}</>");
        }
        $this->line('<fg=cyan>══════════════════════════════════════════════════════</>');
        $this->newLine();

        // Historial de la conversación para el cliente IA
        $historialIa = [];

        // Mensaje inicial del cliente IA
        $primerMensaje = $this->clienteIaResponde($historialIa, null, $objetivo, $contexto);
        if (!$primerMensaje) {
            $this->error('Error al generar el primer mensaje del cliente IA.');
            return 1;
        }

        $turno = 0;
        $mensajeCliente = $primerMensaje;

        while ($turno < $maxTurnos) {
            $turno++;
            $this->line("<fg=green>👤 Cliente IA [{$turno}]:</> {$mensajeCliente}");

            // Enviar al bot
            try {
                $respuestaBot = $bot->process($cliente, $mensajeCliente);
                $cliente->refresh();
            } catch (\Throwable $e) {
                $this->error("❌ Error en el bot: " . $e->getMessage());
                Log::error("bot:test-ia error: " . $e->getMessage());
                break;
            }

            if ($respuestaBot === '') {
                $this->line('<fg=gray>🤖 Bot:      (mensaje interactivo / sin texto)</>');
            } else {
                $lineas = explode("\n", $respuestaBot);
                foreach ($lineas as $i => $linea) {
                    $prefijo = $i === 0 ? '<fg=blue>🤖 Bot:</>      ' : '             ';
                    $this->line($prefijo . $linea);
                }
            }

            if ($cliente->estado && $cliente->estado !== 'activo') {
                $this->line("<fg=yellow>             [estado bot: {$cliente->estado}]</>");
            }
            $this->newLine();

            // Agregar al historial del cliente IA
            $historialIa[] = ['role' => 'user',      'content' => "Bot: {$respuestaBot}"];

            // Verificar si la IA dice que terminó
            if ($this->conversacionTerminada($respuestaBot)) {
                $this->line('<fg=green>✓ El bot indicó que el flujo finalizó.</>');
                break;
            }

            // El cliente IA decide qué responder
            $mensajeCliente = $this->clienteIaResponde($historialIa, $respuestaBot, $objetivo, $contexto);

            if (!$mensajeCliente) {
                $this->error('Error al generar respuesta del cliente IA.');
                break;
            }

            // Si el cliente IA quiere terminar
            if (str_starts_with(strtolower($mensajeCliente), '[fin]')) {
                $this->line('<fg=green>✓ El cliente IA dio por terminada la conversación.</>');
                break;
            }

            $historialIa[] = ['role' => 'assistant', 'content' => $mensajeCliente];
        }

        if ($turno >= $maxTurnos) {
            $this->warn("⚠ Se alcanzó el límite de {$maxTurnos} turnos.");
        }

        $this->mostrarEstadoFinal($cliente);

        return 0;
    }

    private function clienteIaResponde(array $historial, ?string $ultimoMensajeBot, string $objetivo, string $contexto): ?string
    {
        $systemPrompt = <<<PROMPT
Sos un cliente argentino de una distribuidora de alimentos que chatea por WhatsApp.

OBJETIVO: {$objetivo}

INSTRUCCIONES DE COMPORTAMIENTO:
{$contexto}

REGLAS:
- Respondé siempre en español rioplatense, de forma breve y natural (como un mensaje de WhatsApp real).
- Cuando el bot te muestre una lista numerada (fechas, productos, medios de pago), elegí el número 1 a menos que el contexto indique otra cosa.
- Cuando el bot pida confirmación (sí/no), respondé "sí" si el objetivo está siendo cumplido.
- Si completaste el objetivo o el bot confirmó el pedido/acción, respondé exactamente: [FIN] gracias!
- Si el bot te hace una pregunta, respondé solo lo que te pide, sin agregar nada extra.
- Máximo 2 oraciones por mensaje.
PROMPT;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        if (empty($historial) && $ultimoMensajeBot === null) {
            // Primera vez: el cliente inicia la conversación
            $messages[] = ['role' => 'user', 'content' => 'Iniciá la conversación con el bot para cumplir tu objetivo. Escribí solo el mensaje, nada más.'];
        } else {
            foreach ($historial as $h) {
                $messages[] = $h;
            }
            if ($ultimoMensajeBot !== null && empty($historial)) {
                $messages[] = ['role' => 'user', 'content' => "Bot dijo: {$ultimoMensajeBot}\n\n¿Qué respondés?"];
            }
        }

        try {
            $response = Http::withToken(config('api.openai.key'))
                ->timeout(30)
                ->post(self::OPENAI_URL, [
                    'model'       => 'gpt-4.1-mini',
                    'messages'    => $messages,
                    'max_tokens'  => 100,
                    'temperature' => 0.4,
                ])
                ->json();

            return trim($response['choices'][0]['message']['content'] ?? '');
        } catch (\Throwable $e) {
            $this->error('Error llamando a OpenAI para el cliente IA: ' . $e->getMessage());
            return null;
        }
    }

    private function conversacionTerminada(string $respuestaBot): bool
    {
        $lower = mb_strtolower($respuestaBot);
        $frases = [
            'pedido confirmado',
            'pedido registrado',
            'tu pedido fue',
            'quedó registrado',
            'te llegará',
            'nos vemos',
        ];
        foreach ($frases as $f) {
            if (str_contains($lower, $f)) return true;
        }
        return false;
    }

    private function limpiarCliente(Cliente $cliente): void
    {
        \App\Models\Carrito::where('cliente_id', $cliente->id)->delete();
        \App\Models\Message::where('cliente_id', $cliente->id)->delete();
        Cache::forget('fecha_reparto_elegida_' . $cliente->id);
        Cache::forget('proxima_fecha_entrega_' . $cliente->id);
        Cache::forget('pedido_conf_' . $cliente->id);
        Cache::forget('reparto_opciones_' . $cliente->id);
        Cache::forget('reg_nombre_' . $cliente->id);
        Cache::forget('reg_localidad_' . $cliente->id);
        Cache::forget('reg_calle_' . $cliente->id);
        $cliente->update(['estado' => 'activo', 'modo' => 'bot', 'memoria_ia' => null]);
        $cliente->refresh();
    }

    private function mostrarEstadoFinal(Cliente $cliente): void
    {
        $cliente->refresh();
        $carrito = Carrito::where('cliente_id', $cliente->id)->latest()->first();

        $this->newLine();
        $this->line('<fg=cyan>── Estado final ──────────────────────────────────────</>');
        $this->line('Estado cliente : ' . ($cliente->estado ?? 'activo'));
        $this->line('Fecha elegida  : ' . (Cache::get('fecha_reparto_elegida_' . $cliente->id) ?? '(ninguna)'));

        if ($carrito && !empty($carrito->items)) {
            $this->line('Carrito        :');
            foreach ($carrito->items as $item) {
                $cant = ($item['tipo'] !== 'Unidad' && ($item['kilos'] ?? 0) > 0)
                    ? number_format($item['kilos'], 3, ',', '.') . ' kg'
                    : ($item['cant'] ?? 0) . ' u';
                $this->line("  • {$item['des']}: {$cant} — \$" . number_format($item['neto'], 2, ',', '.'));
            }
        } else {
            $this->line('Carrito        : (vacío)');
        }
        $this->line('<fg=cyan>──────────────────────────────────────────────────────</>');
    }
}
