<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Carrito;
use App\Services\BotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Simula una conversación completa con el bot usando OpenAI real.
 *
 * Uso:
 *   php artisan bot:simular                          # modo interactivo
 *   php artisan bot:simular --escenario=pedido       # escenario predefinido
 *   php artisan bot:simular --phone=5491112345678    # cliente existente
 *   php artisan bot:simular --escenario=pedido --dry # sin guardar en BD
 */
class SimularConversacion extends Command
{
    protected $signature = 'bot:simular
                            {--escenario= : Escenario predefinido (pedido, fecha, pago, lo_mismo)}
                            {--phone=5490000000001 : Número de teléfono del cliente de prueba}
                            {--limpiar : Borrar carrito y estado del cliente antes de empezar}';

    protected $description = 'Simula una conversación con el bot de WhatsApp';

    private array $escenarios = [
        'pedido' => [
            'descripcion' => 'Flujo completo: elegir fecha → agregar producto → confirmar',
            'mensajes'    => [
                'hola, quiero pedir',
                // El bot pedirá elegir fecha (si hay múltiples)
                '1',
                // El bot mostrará catálogo
                'quiero 2 kg de vacío',
                // El bot agregará al carrito y pedirá confirmar
                'sí',
            ],
        ],
        'fecha' => [
            'descripcion' => 'Selección de fecha de reparto por texto',
            'mensajes'    => [
                'quiero hacer un pedido para el viernes',
                'bondiola 3 kg',
                'sí',
            ],
        ],
        'pago' => [
            'descripcion' => 'Flujo de pago cuando hay múltiples medios',
            'mensajes'    => [
                'quiero 1 kg de asado',
                '1',     // si pide elegir fecha
                'sí',    // confirmar
                '2',     // elegir medio de pago (transferencia)
                'sí',    // confirmar pedido
            ],
        ],
        'lo_mismo' => [
            'descripcion' => 'Shortcut "lo mismo de siempre"',
            'mensajes'    => [
                'lo mismo de siempre',
                'sí',
            ],
        ],
        'interactivo' => [
            'descripcion' => 'Modo interactivo — escribís los mensajes vos',
            'mensajes'    => [],
        ],
    ];

    public function handle(BotService $bot): int
    {
        $phone     = $this->option('phone');
        $escNombre = $this->option('escenario') ?? 'interactivo';

        if (!isset($this->escenarios[$escNombre])) {
            $this->error("Escenario '{$escNombre}' no existe. Opciones: " . implode(', ', array_keys($this->escenarios)));
            return 1;
        }

        $escenario = $this->escenarios[$escNombre];

        // Obtener o crear cliente de prueba
        $cliente = Cliente::firstOrCreate(
            ['phone' => $phone],
            ['name' => 'Test Bot', 'estado' => 'activo']
        );

        if ($this->option('limpiar')) {
            Carrito::where('cliente_id', $cliente->id)->delete();
            Cache::forget('fecha_reparto_elegida_' . $cliente->id);
            Cache::forget('pedido_conf_' . $cliente->id);
            Cache::forget('reparto_opciones_' . $cliente->id);
            $cliente->update(['estado' => 'activo']);
            $this->line('<fg=yellow>⟳ Estado del cliente limpiado.</>');
        }

        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════</>");
        $this->line("<fg=cyan>  Bot Simulator — {$escenario['descripcion']}</>");
        $this->line("<fg=cyan>  Cliente: {$cliente->name} ({$cliente->phone})</>");
        $this->line("<fg=cyan>══════════════════════════════════════════</>");
        $this->newLine();

        $mensajes = $escenario['mensajes'];

        if ($escNombre === 'interactivo' || empty($mensajes)) {
            $this->line('<fg=yellow>Modo interactivo. Escribí "salir" para terminar.</>');
            $this->newLine();
            $this->runInteractivo($bot, $cliente);
        } else {
            $this->runEscenario($bot, $cliente, $mensajes);
        }

        return 0;
    }

    private function runEscenario(BotService $bot, Cliente $cliente, array $mensajes): void
    {
        foreach ($mensajes as $mensaje) {
            $this->mostrarMensajeCliente($mensaje);

            try {
                $respuesta = $bot->process($cliente, $mensaje);
                $cliente->refresh();
                $this->mostrarRespuestaBot($respuesta, $cliente->estado);
            } catch (\Throwable $e) {
                $this->error("  ❌ Error: " . $e->getMessage());
                break;
            }

            // Pausa visual entre mensajes
            usleep(300_000);
        }

        $this->newLine();
        $this->line('<fg=green>✓ Escenario completado.</>');
        $this->mostrarEstadoFinal($cliente);
    }

    private function runInteractivo(BotService $bot, Cliente $cliente): void
    {
        while (true) {
            $mensaje = $this->ask('<fg=green>Vos</>');
            if (in_array(strtolower(trim($mensaje ?? '')), ['salir', 'exit', 'quit', ''])) {
                break;
            }

            $this->mostrarMensajeCliente($mensaje);

            try {
                $respuesta = $bot->process($cliente, $mensaje);
                $cliente->refresh();
                $this->mostrarRespuestaBot($respuesta, $cliente->estado);
            } catch (\Throwable $e) {
                $this->error("  ❌ Error: " . $e->getMessage());
            }
        }

        $this->mostrarEstadoFinal($cliente);
    }

    private function mostrarMensajeCliente(string $mensaje): void
    {
        $this->line("<fg=green>👤 Cliente:</>  {$mensaje}");
    }

    private function mostrarRespuestaBot(string $respuesta, ?string $estado): void
    {
        if ($respuesta === '') {
            $this->line('<fg=gray>🤖 Bot:      (sin respuesta de texto — mensaje interactivo enviado)</>');
        } else {
            $lineas = explode("\n", $respuesta);
            foreach ($lineas as $i => $linea) {
                $prefijo = $i === 0 ? '<fg=blue>🤖 Bot:</>     ' : '            ';
                $this->line($prefijo . $linea);
            }
        }
        if ($estado && $estado !== 'activo') {
            $this->line("<fg=yellow>             [estado: {$estado}]</>");
        }
        $this->newLine();
    }

    private function mostrarEstadoFinal(Cliente $cliente): void
    {
        $cliente->refresh();
        $carrito = \App\Models\Carrito::where('cliente_id', $cliente->id)->latest()->first();

        $this->newLine();
        $this->line('<fg=cyan>── Estado final ──────────────────────────────</>');
        $this->line("Estado cliente : " . ($cliente->estado ?? 'activo'));
        $this->line("Fecha elegida  : " . (Cache::get('fecha_reparto_elegida_' . $cliente->id) ?? '(ninguna)'));

        if ($carrito && !empty($carrito->items)) {
            $this->line("Carrito        :");
            foreach ($carrito->items as $item) {
                $cant = $item['tipo'] !== 'Unidad' && $item['kilos'] > 0
                    ? number_format($item['kilos'], 3, ',', '.') . ' kg'
                    : $item['cant'] . ' u';
                $this->line("  • {$item['des']}: {$cant} — \$" . number_format($item['neto'], 2, ',', '.'));
            }
        } else {
            $this->line("Carrito        : (vacío)");
        }
        $this->line('<fg=cyan>──────────────────────────────────────────────</>');
    }
}
