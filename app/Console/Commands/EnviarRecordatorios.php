<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Localidad;
use App\Models\Message;
use App\Models\Producto;
use App\Models\ProductoLocalidad;
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
        $diasRec = !empty($rec->dias) ? array_map('intval', $rec->dias) : [];

        // Obtener localidad para filtrar productos
        $localidadObj = $rec->filtro_localidad
            ? Localidad::where('nombre', $rec->filtro_localidad)->where('activo', true)->first()
            : null;

        $todosProductos = Producto::paraBot()->orderBy('tablaplu.desgrupo')->orderBy('tablaplu.des')->get();

        if ($localidadObj) {
            $prodLocConfigs = ProductoLocalidad::where('localidad_id', $localidadObj->id)->get()->keyBy('cod');

            if ($prodLocConfigs->isNotEmpty()) {
                $productos = $todosProductos->filter(function ($p) use ($prodLocConfigs, $diasRec) {
                    if (!$prodLocConfigs->has($p->cod)) return false;
                    if (empty($diasRec)) return true; // sin días configurados: mostrar todos
                    $diasCfg = $prodLocConfigs->get($p->cod)->dias_reparto;
                    if ($diasCfg === null) return true;  // sin restricción de días
                    if (empty($diasCfg)) return false;
                    $diasNum = array_map(fn($d) => is_array($d) ? (int)$d['dia'] : (int)$d, $diasCfg);
                    return !empty(array_intersect($diasRec, $diasNum));
                });

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
