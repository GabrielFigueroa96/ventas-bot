<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Recordatorio;
use App\Services\BotService;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnviarRecordatorios extends Command
{
    protected $signature   = 'recordatorios:enviar';
    protected $description = 'Envía recordatorios programados por WhatsApp a clientes';

    public function handle(): void
    {
        $tenants = DB::connection('mysql')->table('ia_tenants')->where('activo', true)->get();
        foreach ($tenants as $tenant) {
            app(TenantManager::class)->loadById($tenant->id);
            $this->procesarTenant(app(BotService::class));
        }
    }

    private function procesarTenant(BotService $bot): void
    {
        $recordatorios = Recordatorio::where('activo', true)->get()->filter->deberia();

        foreach ($recordatorios as $recordatorio) {
            $clientes = $this->obtenerClientes($recordatorio);
            $enviados = 0;

            foreach ($clientes as $cliente) {
                try {
                    $mensaje = $this->construirMensaje($recordatorio, $cliente);
                    $bot->sendWhatsapp($cliente->phone, $mensaje);
                    $enviados++;
                } catch (\Throwable $e) {
                    Log::error("Recordatorio #{$recordatorio->id} cliente #{$cliente->id}: {$e->getMessage()}");
                }
            }

            $recordatorio->update(['ultimo_envio_at' => now()]);
            $this->line("✓ Recordatorio '{$recordatorio->nombre}': {$enviados} mensajes enviados.");
        }
    }

    private function obtenerClientes(Recordatorio $rec)
    {
        $query = Cliente::whereNotNull('phone')->where('phone', '!=', '');

        if ($rec->filtro_localidad || $rec->filtro_provincia) {
            $query->where(function ($q) use ($rec) {
                // Clientes con localidad_id vinculada a la tabla localidades
                $q->whereHas('localidadObj', function ($q2) use ($rec) {
                    if ($rec->filtro_localidad) $q2->where('nombre', $rec->filtro_localidad);
                    if ($rec->filtro_provincia) $q2->where('provincia', $rec->filtro_provincia);
                });
                // O coincide por cuenta vinculada
                $q->orWhereHas('cuenta', function ($q2) use ($rec) {
                    if ($rec->filtro_localidad) $q2->where('loca', 'like', "%{$rec->filtro_localidad}%");
                    if ($rec->filtro_provincia) $q2->where('prov', 'like', "%{$rec->filtro_provincia}%");
                });
            });
        }

        return $query->with(['cuenta'])->get();
    }

    private function construirMensaje(Recordatorio $rec, Cliente $cliente): string
    {
        $nombre = $cliente->name ?? 'cliente';
        $codcli = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;

        // Variables base
        $mensaje = str_replace('{nombre}', $nombre, $rec->mensaje);

        // Tipo: repetir_pedido — adjunta resumen del último pedido
        if ($rec->tipo === 'repetir_pedido') {
            $ultimoPedido = Pedido::where('codcli', $codcli)
                ->orderByDesc('reg')
                ->get()
                ->groupBy('nro')
                ->first();

            if ($ultimoPedido) {
                $nro     = $ultimoPedido->first()->nro;
                $items   = $ultimoPedido->map(fn($p) => $p->kilos > 0 ? "{$p->kilos}kg {$p->descrip}" : "{$p->cant}u {$p->descrip}")->implode(', ');
                $resumen = "Último pedido #$nro: $items";
            } else {
                $resumen = '';
            }
            $mensaje = str_replace('{ultimo_pedido}', $resumen, $mensaje);
        }

        // Tipo: recomendacion — adjunta top 3 productos del cliente
        if ($rec->tipo === 'recomendacion') {
            $top = Pedido::where('codcli', $codcli)
                ->selectRaw('descrip, COUNT(*) as veces')
                ->groupBy('descrip')
                ->orderByDesc('veces')
                ->take(3)
                ->pluck('descrip')
                ->implode(', ');

            $mensaje = str_replace('{recomendaciones}', $top ?: 'nuestros productos', $mensaje);
        }

        return $mensaje;
    }
}
