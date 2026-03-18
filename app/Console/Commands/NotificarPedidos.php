<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PedidoNotificacion;
use App\Models\Pedidosia;
use App\Services\BotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotificarPedidos extends Command
{
    protected $signature   = 'pedidos:notificar';
    protected $description = 'Envía WhatsApp a clientes cuando su pedido cambia de estado en VB.NET';

    private const MSG_ENVIO  = "¡Hola {nombre}! 🎉 Tu pedido #{nro} ya está listo y en camino a tu domicilio. ¡Gracias!";
    private const MSG_RETIRO = "¡Hola {nombre}! 🎉 Tu pedido #{nro} ya está listo. Podés pasar a retirarlo cuando quieras. ¡Gracias!";

    public function handle(BotService $bot): void
    {
        $this->procesarEstado($bot, Pedido::ESTADO_FINALIZADO);
    }

    private function procesarEstado(BotService $bot, int $estado): void
    {
        // Todos los nro+pv finalizados (un row por grupo)
        $pedidos = Pedido::where('estado', $estado)
            ->selectRaw('MIN(reg) as reg, nro, pv, MIN(codcli) as codcli, MIN(nomcli) as nomcli')
            ->groupBy('nro', 'pv')
            ->get();

        foreach ($pedidos as $pedido) {
            // Ya notificado? La constraint UNIQUE lo previene también a nivel DB
            $yaNotificado = PedidoNotificacion::where('nro', $pedido->nro)
                ->where('pv', $pedido->pv)
                ->where('estado_notificado', $estado)
                ->exists();

            if ($yaNotificado) {
                continue;
            }

            // Buscar el cliente por cuenta vinculada o por id interno
            $cliente = Cliente::where('cuenta_cod', $pedido->codcli)->first()
                    ?? Cliente::find($pedido->codcli);

            if (!$cliente) {
                Log::warning("NotificarPedidos: sin cliente para codcli={$pedido->codcli}, nro={$pedido->nro}");
                continue;
            }

            $sia      = Pedidosia::where('nro', $pedido->nro)->first();
            $plantilla = ($sia?->tipo_entrega === 'envio') ? self::MSG_ENVIO : self::MSG_RETIRO;

            $mensaje = str_replace(
                ['{nombre}', '{nro}'],
                [$cliente->name ?? $pedido->nomcli, $pedido->nro],
                $plantilla
            );

            try {
                $bot->sendWhatsapp($cliente->phone, $mensaje);

                PedidoNotificacion::create([
                    'nro'              => $pedido->nro,
                    'pv'               => $pedido->pv,
                    'estado_notificado' => $estado,
                    'phone'            => $cliente->phone,
                    'enviado_at'       => now(),
                ]);

                $this->line("✓ Notificado: pedido #{$pedido->nro} → {$cliente->phone}");
            } catch (\Throwable $e) {
                Log::error("NotificarPedidos error nro={$pedido->nro}: {$e->getMessage()}");
            }
        }
    }
}
