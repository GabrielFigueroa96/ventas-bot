<?php

namespace App\Console\Commands;

use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\IaEmpresa;
use App\Models\Message;
use App\Models\Pedido;
use App\Models\Seguimiento;
use App\Services\BotService;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeguimientoClientes extends Command
{
    protected $signature   = 'clientes:seguimiento';
    protected $description = 'Envía WhatsApp a clientes según su actividad (carrito abandonado, sin pedido, inactivos)';

    public function handle(): void
    {
        $tenants = DB::connection('mysql')->table('ia_tenants')->where('activo', true)->get();

        foreach ($tenants as $tenant) {
            app(TenantManager::class)->loadById($tenant->id);
            $bot    = app(BotService::class);
            $config = IaEmpresa::first();

            if ($config->seguimiento_carrito_activo ?? true) {
                $this->procesarCarritoAbandonado($bot, (int) ($config->seguimiento_carrito_horas ?? 2));
            }
            if ($config->seguimiento_sin_pedido_activo ?? true) {
                $this->procesarSinPedido($bot, (int) ($config->seguimiento_sin_pedido_dias ?? 3));
            }
            if ($config->seguimiento_inactivo_activo ?? true) {
                $this->procesarInactivos($bot, (int) ($config->seguimiento_inactivo_dias ?? 7));
            }
        }
    }

    // Clientes con carrito activo que no confirmaron en X horas
    private function procesarCarritoAbandonado(BotService $bot, int $horas): void
    {
        $carritos = Carrito::where('updated_at', '<=', now()->subHours($horas))
            ->where('updated_at', '>', now()->subDays(7))
            ->whereRaw("JSON_LENGTH(items) > 0")
            ->with('cliente')
            ->get();

        foreach ($carritos as $carrito) {
            $cliente = $carrito->cliente;
            if (!$cliente || $cliente->estado === 'humano' || $cliente->modo === 'humano') continue;

            $yaEnviado = Seguimiento::where('cliente_id', $cliente->id)
                ->where('tipo', 'carrito_abandonado')
                ->where('enviado_at', '>=', now()->subHours(max($horas * 2, 12)))
                ->exists();
            if ($yaEnviado) continue;

            $alertas = $bot->verificarCarritoAbandonado($carrito, $cliente);
            $mensaje = $this->mensajeCarritoAbandonado($cliente, $carrito, $alertas);
            $this->enviar($bot, $cliente, 'carrito_abandonado', $mensaje);
        }
    }

    // Clientes que tuvieron conversación en los últimos X días pero no hicieron pedido
    private function procesarSinPedido(BotService $bot, int $dias): void
    {
        $clientes = Cliente::where('estado', 'activo')
            ->where('modo', '!=', 'humano')
            ->whereHas('messages', fn($q) =>
                $q->where('direction', 'incoming')
                  ->where('created_at', '>=', now()->subDays($dias))
            )
            ->with('cuenta')
            ->get();

        foreach ($clientes as $cliente) {
            $yaEnviado = Seguimiento::where('cliente_id', $cliente->id)
                ->where('enviado_at', '>=', now()->subDays($dias * 2))
                ->exists();
            if ($yaEnviado) continue;

            $codcli     = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
            $hizoPedido = Pedido::where('codcli', $codcli)
                ->where('fecha', '>=', now()->subDays($dias)->format('Y-m-d'))
                ->exists();
            if ($hizoPedido) continue;

            $mensaje = $this->mensajeSinPedido($cliente);
            $this->enviar($bot, $cliente, 'sin_pedido', $mensaje);
        }
    }

    // Clientes que no escriben hace X+ días
    private function procesarInactivos(BotService $bot, int $dias): void
    {
        $clientes = Cliente::where('estado', 'activo')
            ->where('modo', '!=', 'humano')
            ->whereHas('messages', fn($q) =>
                $q->where('direction', 'incoming')
                  ->where('created_at', '<', now()->subDays($dias))
                  ->where('created_at', '>=', now()->subDays(90))
            )
            ->whereDoesntHave('messages', fn($q) =>
                $q->where('direction', 'incoming')
                  ->where('created_at', '>=', now()->subDays($dias))
            )
            ->with('cuenta')
            ->get();

        foreach ($clientes as $cliente) {
            $yaEnviado = Seguimiento::where('cliente_id', $cliente->id)
                ->where('tipo', 'inactivo')
                ->where('enviado_at', '>=', now()->subDays($dias * 2))
                ->exists();
            if ($yaEnviado) continue;

            $mensaje = $this->mensajeInactivo($cliente);
            $this->enviar($bot, $cliente, 'inactivo', $mensaje);
        }
    }

    private function mensajeCarritoAbandonado(Cliente $cliente, Carrito $carrito, array $alertas = []): string
    {
        $nombre = $cliente->name ?? 'cliente';
        $items  = collect($carrito->items ?? []);

        if ($items->isEmpty()) {
            return "¡Hola {$nombre}! 🛒 Tenés productos guardados en el carrito. ¿Querés confirmar el pedido?";
        }

        $lista = $items->take(3)->map(fn($i) => "• {$i['des']}")->implode("\n");
        if ($items->count() > 3) {
            $lista .= "\n• ...y " . ($items->count() - 3) . " más";
        }

        $mensaje = "¡Hola {$nombre}! 🛒 Dejaste estos productos en el carrito:\n{$lista}\n\n¿Querés que confirmemos el pedido?";

        if (!empty($alertas)) {
            $mensaje .= "\n\n_Nota: algunos productos cambiaron:_\n" . implode("\n", $alertas);
        }

        return $mensaje;
    }

    private function mensajeSinPedido(Cliente $cliente): string
    {
        $nombre   = $cliente->name ?? 'cliente';
        $codcli   = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
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
        $nombre   = $cliente->name ?? 'cliente';
        $codcli   = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
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
        } catch (\Throwable $e) {
            Log::error("SeguimientoClientes envío WA cliente {$cliente->id}: {$e->getMessage()}");
            return;
        }

        try {
            Message::create([
                'cliente_id' => $cliente->id,
                'message'    => $mensaje,
                'direction'  => 'outgoing',
                'type'       => 'text',
            ]);
        } catch (\Throwable $e) {
            Log::error("SeguimientoClientes Message::create cliente {$cliente->id}: {$e->getMessage()}");
        }

        try {
            Seguimiento::create([
                'cliente_id'      => $cliente->id,
                'tipo'            => $tipo,
                'mensaje_enviado' => $mensaje,
                'respondio'       => false,
                'enviado_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("SeguimientoClientes Seguimiento::create cliente {$cliente->id}: {$e->getMessage()}");
        }

        $this->line("✓ [{$tipo}] {$cliente->name} ({$cliente->phone})");
    }
}
