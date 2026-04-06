<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Localidad;
use App\Models\Message;
use App\Models\Producto;
use App\Models\ProductoLocalidad;
use App\Services\BotService;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnviarRecordatoriosVentana extends Command
{
    protected $signature   = 'recordatorios:ventana {--force : Ignorar restricción horaria}';
    protected $description = 'Envía recordatorios de apertura/cierre de pedidos por localidad';

    public function handle(): void
    {
        $tenants = DB::connection('mysql')->table('ia_tenants')->where('activo', true)->get();
        foreach ($tenants as $tenant) {
            app(TenantManager::class)->loadById($tenant->id);
            $this->procesarTenant($tenant->id, app(BotService::class));
        }
    }

    private function procesarTenant(int $tenantId, BotService $bot): void
    {
        $localidades = Localidad::where('activo', true)
            ->where(fn($q) => $q->where('rec_apertura', true)->orWhere('rec_cierre', true))
            ->get();

        foreach ($localidades as $loc) {
            $diasConfig  = $loc->diasConfig();
            $diasConfigMap = collect($diasConfig)->keyBy('dia');
            $diasReparto   = array_map(fn($d) => (int)$d['dia'], $diasConfig);

            // Buscar fechas de reparto en los próximos 7 días
            for ($i = 1; $i <= 7; $i++) {
                $candidato  = now()->addDays($i)->startOfDay();
                $diaSemana  = (int)$candidato->dayOfWeek;
                if (!in_array($diaSemana, $diasReparto, true)) continue;

                $cfg = $diasConfigMap->get($diaSemana) ?? [];

                if ($loc->rec_apertura) {
                    $this->checkYEnviar('apertura', $loc, $cfg, $diaSemana, $candidato, $tenantId, 0, $bot);
                }
                if ($loc->rec_cierre && $loc->rec_cierre_horas) {
                    $this->checkYEnviar('cierre', $loc, $cfg, $diaSemana, $candidato, $tenantId, (int)$loc->rec_cierre_horas, $bot);
                }
            }
        }
    }

    private function checkYEnviar(
        string $tipo, Localidad $loc, array $cfg, int $diaSemana,
        Carbon $fechaReparto, int $tenantId, int $horasAntes, BotService $bot
    ): void {
        $triggerTime = $this->calcularTrigger($tipo, $cfg, $diaSemana, $fechaReparto, $horasAntes);
        if ($triggerTime === null) return;

        $diff = now()->diffInMinutes($triggerTime, false);
        $disparar = ($diff >= -10 && $diff <= 0);

        if (!$disparar && !$this->option('force')) return;

        $cacheKey = "rec_ventana_{$tipo}_{$tenantId}_{$loc->id}_{$diaSemana}_{$fechaReparto->format('Y-m-d')}";
        if (Cache::has($cacheKey) && !$this->option('force')) return;
        Cache::put($cacheKey, true, now()->addHours(2));

        $mensaje  = $tipo === 'apertura' ? ($loc->rec_apertura_mensaje ?? '') : ($loc->rec_cierre_mensaje ?? '');
        $template = $tipo === 'apertura' ? ($loc->rec_apertura_template ?? '') : ($loc->rec_cierre_template ?? '');
        $catalogo = $this->buildCatalogo($loc, $diaSemana);
        $diaTexto = $fechaReparto->locale('es')->isoFormat('dddd D [de] MMMM');

        $clientes = Cliente::where('localidad_id', $loc->id)
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->with('cuenta')->get();

        $enviados = 0;
        foreach ($clientes as $cliente) {
            try {
                $nombre  = $cliente->name ?? 'cliente';
                $texto   = str_replace(
                    ['{nombre}', '{dia_reparto}', '{horas}'],
                    [$nombre, $diaTexto, $horasAntes],
                    $mensaje
                );

                if ($template && $catalogo) {
                    // Template para abrir ventana + catálogo como mensaje aparte
                    $bot->sendRecordatorioTemplate($cliente->phone, $template, $nombre, $texto);
                    sleep(1);
                    $bot->sendWhatsapp($cliente->phone, $catalogo);
                } elseif ($template) {
                    $bot->sendRecordatorioTemplate($cliente->phone, $template, $nombre, $texto);
                } else {
                    $mensajeCompleto = $catalogo ? $texto . "\n\n" . $catalogo : $texto;
                    $bot->sendWhatsapp($cliente->phone, $mensajeCompleto);
                }

                Message::create([
                    'cliente_id' => $cliente->id,
                    'message'    => "[Recordatorio {$tipo} {$loc->nombre}]\n{$texto}" . ($catalogo ? "\n\n{$catalogo}" : ''),
                    'direction'  => 'outgoing',
                ]);

                $enviados++;
            } catch (\Throwable $e) {
                Log::error("RecordatorioVentana {$tipo} localidad#{$loc->id} cliente#{$cliente->id}: {$e->getMessage()}");
            }
        }

        $this->line("✓ {$tipo} {$loc->nombre} ({$diaTexto}): {$enviados} enviados.");
    }

    private function calcularTrigger(string $tipo, array $cfg, int $diaSemana, Carbon $fechaReparto, int $horasAntes): ?Carbon
    {
        if ($tipo === 'apertura') {
            if (empty($cfg['desde_dia']) && empty($cfg['desde_hora'])) return null;
            $diaRef  = isset($cfg['desde_dia']) && $cfg['desde_dia'] !== null ? (int)$cfg['desde_dia'] : $diaSemana;
            $horaRef = !empty($cfg['desde_hora']) ? $cfg['desde_hora'] : '00:00';
            $diff    = ($diaSemana - $diaRef + 7) % 7 ?: 7;
            return $fechaReparto->copy()->subDays($diff)->setTimeFromTimeString($horaRef);
        }

        // cierre
        if (empty($cfg['hasta_dia']) && empty($cfg['hasta_hora'])) return null;
        $diaRef  = isset($cfg['hasta_dia']) && $cfg['hasta_dia'] !== null ? (int)$cfg['hasta_dia'] : $diaSemana;
        $horaRef = !empty($cfg['hasta_hora']) ? $cfg['hasta_hora'] : '23:59';
        $diff    = ($diaSemana - $diaRef + 7) % 7 ?: 7;
        return $fechaReparto->copy()->subDays($diff)->setTimeFromTimeString($horaRef)->subHours($horasAntes);
    }

    private function buildCatalogo(Localidad $loc, int $diaReparto): string
    {
        $prodLocConfigs = ProductoLocalidad::where('localidad_id', $loc->id)->get()->keyBy('cod');
        if ($prodLocConfigs->isEmpty()) return '';

        $todos = Producto::paraBot()->orderBy('tablaplu.desgrupo')->orderBy('tablaplu.des')->get();

        $productos = $todos->filter(function ($p) use ($prodLocConfigs, $diaReparto) {
            if (!$prodLocConfigs->has($p->cod)) return false;
            $diasCfg = $prodLocConfigs->get($p->cod)->dias_reparto;
            if ($diasCfg === null) return true;
            if (empty($diasCfg)) return false;
            $diasNum = array_map(fn($d) => is_array($d) ? (int)$d['dia'] : (int)$d, $diasCfg);
            return in_array($diaReparto, $diasNum, true);
        })->map(function ($p) use ($prodLocConfigs) {
            $override = $prodLocConfigs->get($p->cod);
            if ($override && $override->precio !== null) {
                $p->precio = $override->precio;
            }
            return $p;
        });

        if ($productos->isEmpty()) return '';

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
