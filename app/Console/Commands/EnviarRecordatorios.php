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
                    $catalogo  = $recordatorio->tipo === 'catalogo' ? $this->buildCatalogo($recordatorio) : null;
                    $mensaje   = $this->construirMensaje($recordatorio, $cliente, $catalogo);
                    $imagenUrl = null;
                    try { $imagenUrl = $recordatorio->imagen_url; } catch (\Throwable) {}

                    $template = trim($recordatorio->template_nombre ?? '');
                    $nombre   = $cliente->name ?? 'cliente';

                    if ($catalogo !== null && $template) {
                        // Catálogo con template: enviar saludo por template (abre ventana 24hs)
                        // y el catálogo como mensaje de texto aparte
                        $bot->sendRecordatorioTemplate($cliente->phone, $template, $nombre, $mensaje);
                        sleep(1);
                        $bot->sendWhatsapp($cliente->phone, $catalogo);
                    } elseif ($template) {
                        $bot->sendRecordatorioTemplate($cliente->phone, $template, $nombre, $mensaje);
                    } elseif (!empty($imagenUrl)) {
                        $bot->sendWhatsappImageByUrl($cliente->phone, $imagenUrl, $mensaje);
                    } else {
                        $bot->sendWhatsapp($cliente->phone, $mensaje);
                    }

                    // Guardar en historial
                    $historial = $catalogo !== null ? $mensaje . "\n\n" . $catalogo : $mensaje;
                    Message::create([
                        'cliente_id' => $cliente->id,
                        'message'    => "[Recordatorio: {$recordatorio->nombre}]\n{$historial}",
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
            $session = Cache::get("flash_order_{$tenant->id}_{$loc->id}");
            if (!$session) continue;

            $avisarHoras = $session['seguimiento_horas_antes'] ?? null;
            $mensaje     = $session['seguimiento_mensaje'] ?? null;
            if (!$avisarHoras || !$mensaje) continue;

            $expira    = isset($session['expira_en']) ? \Carbon\Carbon::parse($session['expira_en']) : null;
            $triggerAt = $expira?->copy()->subHours((int) $avisarHoras);
            if (!$triggerAt) continue;

            $diff = now()->diffInMinutes($triggerAt, false);
            if ($diff > 0 || $diff < -10) continue;

            $cacheKey = "flash_seg_manual_{$tenant->id}_{$loc->id}_" . now()->format('Y-m-d_H');
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
                    Log::error("Seguimiento manual {$loc->nombre} cliente #{$cliente->id}: {$e->getMessage()}");
                }
            }

            Cache::put($cacheKey, true, now()->addHours(2));
            $this->line("✓ Seguimiento express {$loc->nombre}: {$enviados} mensajes enviados.");
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

        $horas   = (int) ($recordatorio->flash_horas ?? 24);
        $expira  = now()->addHours($horas);
        // productos null = modo auto (catálogo del día); array = modo manual con precios de override
        $payload = ['productos' => $recordatorio->productos_flash ?? null, 'nombre' => $recordatorio->nombre];

        foreach ($nombres as $nombre) {
            $loc = Localidad::where('nombre', $nombre)->where('activo', true)->first();
            if ($loc) {
                Cache::put("flash_order_{$tenant->id}_{$loc->id}", $payload, $expira);
                $this->line("  → Flash order activado para {$nombre} ({$horas}hs).");
            }
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

    private function construirMensaje(Recordatorio $rec, Cliente $cliente, ?string $catalogo = null): string
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
            $items = Pedido::where('codcli', $codcli)
                ->selectRaw('descrip, COUNT(*) as veces')
                ->groupBy('descrip')
                ->orderByDesc('veces')
                ->take(3)
                ->pluck('descrip');

            $top = $items->isNotEmpty()
                ? $items->map(fn($d) => "• {$d}")->implode("\n")
                : 'nuestros productos';

            $mensaje = str_replace('{recomendaciones}', $top, $mensaje);
        }

        // Tipo: catalogo — si se pasó el catálogo ya construido lo inserta, si no lo construye inline
        if ($rec->tipo === 'catalogo') {
            $contenido = $catalogo ?? $this->buildCatalogo($rec);
            $mensaje   = str_replace('{catalogo}', $contenido, $mensaje);
        }

        return $mensaje;
    }

    private function buildCatalogo(Recordatorio $rec): string
    {
        // Obtener localidad para filtrar productos
        $localidadObj = $rec->filtro_localidad
            ? Localidad::where('nombre', $rec->filtro_localidad)->where('activo', true)->first()
            : null;

        $todosProductos = Producto::paraBot()->orderBy('tablaplu.desgrupo')->orderBy('tablaplu.des')->get();

        if ($localidadObj) {
            $prodLocConfigs = ProductoLocalidad::where('localidad_id', $localidadObj->id)->get()->keyBy('cod');

            if ($prodLocConfigs->isNotEmpty()) {
                // Mostrar todos los productos de la localidad, sin filtrar por día de envío
                $productos = $todosProductos->filter(fn($p) => $prodLocConfigs->has($p->cod));

                // Para precio: usar override de localidad si existe
                $productos = $productos->map(function ($p) use ($prodLocConfigs) {
                    $override = $prodLocConfigs->get($p->cod);
                    if ($override && $override->precio !== null) {
                        $p->precio = $override->precio;
                    }
                    return $p;
                });
            } else {
                $productos = collect();
            }
        } else {
            // Sin localidad: mostrar todo, filtrar por días si hay restricción en producto_localidad
            $productos = $todosProductos;
        }

        if ($productos->isEmpty()) {
            return '(sin productos disponibles)';
        }

        $formatPrecio = fn($p) => ($p->precio == floor($p->precio))
            ? '$' . number_format($p->precio, 0, ',', '')
            : '$' . number_format($p->precio, 2, ',', '');

        $lineas = [];
        foreach (['Peso', 'Unidad'] as $tipo) {
            $grupo = $productos->where('tipo', $tipo)->groupBy(fn($p) => $p->desgrupo ?: 'Varios');
            if ($grupo->isEmpty()) continue;
            foreach ($grupo as $nombreGrupo => $items) {
                $lineas[] = "*{$nombreGrupo}*";
                foreach ($items as $p) {
                    $unidad   = $tipo === 'Peso' ? '/kg' : '/u';
                    $lineas[] = "• {$p->des} — {$formatPrecio($p)}{$unidad}";
                }
                $lineas[] = '';
            }
        }

        return trim(implode("\n", $lineas));
    }
}
