<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Message;
use App\Models\Recordatorio;
use App\Services\BotService;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnviarRecordatorios extends Command
{
    protected $signature   = 'recordatorios:enviar {--force : Ignorar restricción horaria y enviar todos los activos}';
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
        $todos = Recordatorio::where('activo', true)->get();

        if ($this->option('force')) {
            $recordatorios = $todos;
            $this->line('Modo --force: ignorando restricción horaria.');
        } else {
            $recordatorios = $todos->filter->deberia();
        }

        if ($recordatorios->isEmpty()) {
            $this->line('No hay recordatorios para enviar ahora. (Usá --force para forzar el envío)');
            return;
        }

        foreach ($recordatorios as $recordatorio) {
            $clientes = $this->obtenerClientes($recordatorio);
            $enviados = 0;

            foreach ($clientes as $cliente) {
                try {
                    $mensaje   = str_replace('{nombre}', $cliente->name ?? 'cliente', $recordatorio->mensaje);
                    $imagenUrl = null;
                    try { $imagenUrl = $recordatorio->imagen_url; } catch (\Throwable) {}

                    $template = trim($recordatorio->template_nombre ?? '');
                    $nombre   = $cliente->name ?? 'cliente';

                    if ($template) {
                        $bot->sendRecordatorioTemplate($cliente->phone, $template, $nombre, $mensaje);
                    } elseif (!empty($imagenUrl)) {
                        $bot->sendWhatsappImageByUrl($cliente->phone, $imagenUrl, $mensaje);
                    } else {
                        $bot->sendWhatsapp($cliente->phone, $mensaje);
                    }

                    Message::create([
                        'cliente_id' => $cliente->id,
                        'message'    => "[Recordatorio: {$recordatorio->nombre}]\n{$mensaje}",
                        'direction'  => 'outgoing',
                    ]);

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
                $q->whereHas('localidadObj', function ($q2) use ($rec) {
                    if ($rec->filtro_localidad) $q2->where('nombre', $rec->filtro_localidad);
                    if ($rec->filtro_provincia) $q2->where('provincia', $rec->filtro_provincia);
                });
                $q->orWhereHas('cuenta', function ($q2) use ($rec) {
                    if ($rec->filtro_localidad) $q2->where('loca', 'like', "%{$rec->filtro_localidad}%");
                    if ($rec->filtro_provincia) $q2->where('prov', 'like', "%{$rec->filtro_provincia}%");
                });
            });
        }

        return $query->with(['cuenta'])->get();
    }
}
