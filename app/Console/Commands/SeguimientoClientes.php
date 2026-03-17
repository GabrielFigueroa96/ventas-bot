<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Seguimiento;
use App\Services\BotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SeguimientoClientes extends Command
{
    protected $signature   = 'clientes:seguimiento';
    protected $description = 'Envía WhatsApp a clientes que consultaron pero no pidieron, o que están inactivos';

    public function handle(BotService $bot): void
    {
        $this->procesarSinPedido($bot);
        $this->procesarInactivos($bot);
    }

    // Clientes que tuvieron conversación en los últimos 3 días pero no hicieron pedido
    private function procesarSinPedido(BotService $bot): void
    {
        $clientes = Cliente::where('estado', 'activo')
            ->whereHas('messages', fn($q) =>
                $q->where('direction', 'incoming')
                  ->where('created_at', '>=', now()->subDays(3))
            )
            ->with('cuenta')
            ->get();

        foreach ($clientes as $cliente) {
            // Ya recibió seguimiento en los últimos 5 días
            $yaEnviado = Seguimiento::where('cliente_id', $cliente->id)
                ->where('enviado_at', '>=', now()->subDays(5))
                ->exists();
            if ($yaEnviado) continue;

            // Verificar si hizo pedido en esos mismos 3 días
            $codcli = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
            $hizoPedido = Pedido::where('codcli', $codcli)
                ->where('fecha', '>=', now()->subDays(3)->format('Y-m-d'))
                ->exists();
            if ($hizoPedido) continue;

            $mensaje = $this->mensajeSinPedido($cliente);
            $this->enviar($bot, $cliente, 'sin_pedido', $mensaje);
        }
    }

    // Clientes que no escriben hace 7+ días
    private function procesarInactivos(BotService $bot): void
    {
        $clientes = Cliente::where('estado', 'activo')
            ->whereHas('messages', fn($q) =>
                $q->where('direction', 'incoming')
                  ->where('created_at', '<', now()->subDays(7))
                  ->where('created_at', '>=', now()->subDays(30)) // activos en el último mes
            )
            ->whereDoesntHave('messages', fn($q) =>
                $q->where('direction', 'incoming')
                  ->where('created_at', '>=', now()->subDays(7))
            )
            ->with('cuenta')
            ->get();

        foreach ($clientes as $cliente) {
            $yaEnviado = Seguimiento::where('cliente_id', $cliente->id)
                ->where('tipo', 'inactivo')
                ->where('enviado_at', '>=', now()->subDays(14))
                ->exists();
            if ($yaEnviado) continue;

            $mensaje = $this->mensajeInactivo($cliente);
            $this->enviar($bot, $cliente, 'inactivo', $mensaje);
        }
    }

    private function mensajeSinPedido(Cliente $cliente): string
    {
        $nombre = $cliente->name ?? 'cliente';

        // Top producto que más pide este cliente
        $codcli  = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
        $favorito = Pedido::where('codcli', $codcli)
            ->selectRaw('descrip, COUNT(*) as veces')
            ->groupBy('descrip')
            ->orderByDesc('veces')
            ->value('descrip');

        if ($favorito) {
            return "¡Hola {$nombre}! 👋 ¿Te quedó alguna duda sobre nuestros productos? "
                 . "Sabemos que solés pedir *{$favorito}* — si querés hacemos el pedido ahora mismo. 😊";
        }

        return "¡Hola {$nombre}! 👋 Vimos que estuviste consultando. ¿Puedo ayudarte con algo? "
             . "Estamos para lo que necesites. 🥩";
    }

    private function mensajeInactivo(Cliente $cliente): string
    {
        $nombre  = $cliente->name ?? 'cliente';
        $codcli  = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
        $favorito = Pedido::where('codcli', $codcli)
            ->selectRaw('descrip, COUNT(*) as veces')
            ->groupBy('descrip')
            ->orderByDesc('veces')
            ->value('descrip');

        if ($favorito) {
            return "¡Hola {$nombre}! Hace un tiempo que no te vemos 😊 "
                 . "¿Se te terminó el *{$favorito}*? Avisanos y preparamos el pedido. 🥩";
        }

        return "¡Hola {$nombre}! Hace un tiempo que no sabemos de vos. "
             . "¿Necesitás algo esta semana? Tenemos stock disponible. 🥩";
    }

    private function enviar(BotService $bot, Cliente $cliente, string $tipo, string $mensaje): void
    {
        try {
            $bot->sendWhatsapp($cliente->phone, $mensaje);

            Seguimiento::create([
                'cliente_id'     => $cliente->id,
                'tipo'           => $tipo,
                'mensaje_enviado' => $mensaje,
                'respondio'      => false,
                'enviado_at'     => now(),
            ]);

            $this->line("✓ [{$tipo}] {$cliente->name} ({$cliente->phone})");
        } catch (\Throwable $e) {
            Log::error("SeguimientoClientes error cliente {$cliente->id}: {$e->getMessage()}");
        }
    }
}
