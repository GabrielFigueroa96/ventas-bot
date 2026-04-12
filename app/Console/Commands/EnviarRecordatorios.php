<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Localidad;
use App\Models\Message;
use App\Models\Pedidosia;
use App\Models\Producto;
use App\Models\ProductoLocalidad;
use App\Models\Recordatorio;
use App\Services\BotService;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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
                    $mensaje   = $this->construirMensaje($recordatorio, $cliente);
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
            $this->activarFlashSiCorresponde($recordatorio);
            $this->line("✓ Recordatorio '{$recordatorio->nombre}': {$enviados} mensajes enviados.");
        }

        // Seguimientos de pedidos pendientes
        $this->procesarSeguimientos($bot);
        $this->procesarSeguimientosManuales($bot);
    }

    private function procesarSeguimientos(BotService $bot): void
    {
        $tenantId = app(TenantManager::class)->get()?->id ?? 0;

        $candidatos = Recordatorio::where('activo', true)
            ->whereNotNull('ultimo_envio_at')
            ->whereNotNull('seguimiento_horas_antes')
            ->whereNotNull('seguimiento_mensaje')
            ->get()
            ->filter(fn($r) => $r->ultimo_envio_at->isToday());

        foreach ($candidatos as $rec) {
            $sentAt      = $rec->ultimo_envio_at;
            $flashHoras  = (int) ($rec->flash_horas ?? 24);
            $avisarHoras = (int) $rec->seguimiento_horas_antes;
            $flashExpiry = $sentAt->copy()->addHours($flashHoras);
            $triggerAt   = $flashExpiry->copy()->subHours($avisarHoras);

            // Solo dentro de la ventana de 10 minutos
            $diff = now()->diffInMinutes($triggerAt, false);
            if ($diff > 0 || $diff < -10) continue;

            // Evitar doble envío
            $cacheKey = "flash_seguimiento_{$tenantId}_{$rec->id}_" . now()->format('Y-m-d');
            if (Cache::has($cacheKey)) continue;

            $clientes = $this->obtenerClientesSinPedido($rec, $sentAt);
            $enviados  = 0;

            foreach ($clientes as $cliente) {
                try {
                    $mensaje = str_replace('{nombre}', $cliente->name ?? 'cliente', $rec->seguimiento_mensaje);
                    $bot->sendWhatsapp($cliente->phone, $mensaje);
                    Message::create([
                        'cliente_id' => $cliente->id,
                        'message'    => "[Seguimiento express: {$rec->nombre}]\n{$mensaje}",
                        'direction'  => 'outgoing',
                    ]);
                    $enviados++;
                } catch (\Throwable $e) {
                    Log::error("Seguimiento #{$rec->id} cliente #{$cliente->id}: {$e->getMessage()}");
                }
            }

            Cache::put($cacheKey, true, now()->endOfDay());
            $this->line("✓ Seguimiento '{$rec->nombre}': {$enviados} recordatorios enviados ({$avisarHoras}hs antes del cierre).");
        }
    }

    private function procesarSeguimientosManuales(BotService $bot): void
    {
        $tenant = app(TenantManager::class)->get();
        if (!$tenant) return;

        $localidades = Localidad::where('activo', true)->get();

        foreach ($localidades as $loc) {
            $sessions = Cache::get("flash_orders_{$tenant->id}_{$loc->id}", []);
            if (!is_array($sessions)) continue;

            foreach ($sessions as $session) {
                $avisarHoras = $session['seguimiento_horas_antes'] ?? null;
                $mensaje     = $session['seguimiento_mensaje'] ?? null;
                if (!$avisarHoras || !$mensaje) continue;

                $expira    = isset($session['expira_en']) ? \Carbon\Carbon::parse($session['expira_en']) : null;
                $triggerAt = $expira?->copy()->subHours((int) $avisarHoras);
                if (!$triggerAt) continue;

                $diff = now()->diffInMinutes($triggerAt, false);
                if ($diff > 0 || $diff < -10) continue;

                $sid      = $session['id'] ?? md5(json_encode($session));
                $cacheKey = "flash_seg_{$tenant->id}_{$loc->id}_{$sid}";
                if (Cache::has($cacheKey)) continue;

                $activadoEn  = isset($session['activado_en']) ? \Carbon\Carbon::parse($session['activado_en']) : now()->subDay();
                $yaOrdenaron = Pedidosia::where('pedido_at', '>=', $activadoEn)
                    ->where('estado', '!=', Pedidosia::ESTADO_CANCELADO)
                    ->whereHas('cliente', fn($q) => $q->where('localidad_id', $loc->id))
                    ->pluck('idcliente')->unique();

                $clientes = Cliente::whereNotNull('phone')->where('phone', '!=', '')
                    ->where('localidad_id', $loc->id)
                    ->whereNotIn('id', $yaOrdenaron)
                    ->get();

                $enviados = 0;
                foreach ($clientes as $cliente) {
                    try {
                        $txt = str_replace('{nombre}', $cliente->name ?? 'cliente', $mensaje);
                        $bot->sendWhatsapp($cliente->phone, $txt);
                        Message::create([
                            'cliente_id' => $cliente->id,
                            'message'    => "[Seguimiento express: {$loc->nombre}]\n{$txt}",
                            'direction'  => 'outgoing',
                        ]);
                        $enviados++;
                    } catch (\Throwable $e) {
                        Log::error("Seguimiento {$loc->nombre} sesión {$sid} cliente #{$cliente->id}: {$e->getMessage()}");
                    }
                }

                Cache::put($cacheKey, true, now()->addHours(2));
                $this->line("✓ Seguimiento '{$session['nombre']}' {$loc->nombre}: {$enviados} mensajes enviados.");
            }
        }
    }

    private function obtenerClientesSinPedido(Recordatorio $rec, \Carbon\Carbon $desde)
    {
        $nombres = !empty($rec->flash_localidades)
            ? $rec->flash_localidades
            : ($rec->filtro_localidad ? [$rec->filtro_localidad] : []);

        if (empty($nombres)) return collect();

        // IDs de clientes que YA pidieron desde el envío del flash
        $yaOrdenaron = Pedidosia::where('pedido_at', '>=', $desde)
            ->where('estado', '!=', Pedidosia::ESTADO_CANCELADO)
            ->pluck('idcliente')
            ->unique();

        $query = Cliente::whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotIn('id', $yaOrdenaron)
            ->where(function ($q) use ($nombres) {
                $q->whereHas('localidadObj', fn($q2) => $q2->whereIn('nombre', $nombres));
                foreach ($nombres as $nombre) {
                    $q->orWhereHas('cuenta', fn($q2) => $q2->where('loca', 'like', "%{$nombre}%"));
                }
            });

        return $query->get();
    }

    private function activarFlashSiCorresponde(Recordatorio $recordatorio): void
    {
        $nombres = !empty($recordatorio->flash_localidades)
            ? $recordatorio->flash_localidades
            : ((!empty($recordatorio->productos_flash) && $recordatorio->filtro_localidad)
                ? [$recordatorio->filtro_localidad]
                : []);

        if (empty($nombres)) return;

        $tenant = app(TenantManager::class)->get();
        if (!$tenant) return;

        $horas     = (int) ($recordatorio->flash_horas ?? 24);
        $expiraEn  = now()->addHours($horas);
        $sessionId = 'rec_' . $recordatorio->id . '_' . now()->format('YmdHi');

        $nueva = [
            'id'                      => $sessionId,
            'nombre'                  => $recordatorio->nombre,
            'productos'               => $recordatorio->productos_flash ?? null,
            'activado_en'             => now()->toISOString(),
            'expira_en'               => $expiraEn->toISOString(),
            'seguimiento_horas_antes' => $recordatorio->seguimiento_horas_antes ?? null,
            'seguimiento_mensaje'     => $recordatorio->seguimiento_mensaje ?? null,
        ];

        foreach ($nombres as $nombre) {
            $loc = Localidad::where('nombre', $nombre)->where('activo', true)->first();
            if (!$loc) continue;

            $key      = "flash_orders_{$tenant->id}_{$loc->id}";
            $existing = Cache::get($key, []);
            $existing = array_values(array_filter(
                is_array($existing) ? $existing : [],
                fn($s) => isset($s['expira_en']) && \Carbon\Carbon::parse($s['expira_en'])->isFuture()
            ));
            $existing[] = $nueva;

            $maxExpira = collect($existing)->max(fn($s) => $s['expira_en']);
            Cache::put($key, $existing, \Carbon\Carbon::parse((string) $maxExpira));
            $this->line("  → Flash order activado para {$nombre} ({$horas}hs).");
        }
    }

    private function obtenerClientes(Recordatorio $rec)
    {
        $query = Cliente::whereNotNull('phone')->where('phone', '!=', '');

        // Modo express con múltiples localidades: usa flash_localidades como filtro
        if (!empty($rec->flash_localidades) && !empty($rec->productos_flash)) {
            $nombres = $rec->flash_localidades;
            $query->where(function ($q) use ($nombres) {
                $q->whereHas('localidadObj', fn($q2) => $q2->whereIn('nombre', $nombres));
                foreach ($nombres as $nombre) {
                    $q->orWhereHas('cuenta', fn($q2) => $q2->where('loca', 'like', "%{$nombre}%"));
                }
            });
        } elseif ($rec->filtro_localidad || $rec->filtro_provincia) {
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

    private function construirMensaje(Recordatorio $rec, Cliente $cliente): string
    {
        $nombre = $cliente->name ?? 'cliente';
        return str_replace('{nombre}', $nombre, $rec->mensaje);
    }
}
