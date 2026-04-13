<?php

namespace App\Http\Controllers;

use App\Models\Localidad;
use App\Models\Producto;
use App\Models\ProductoLocalidad;
use App\Services\BotService;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LocalidadController extends Controller
{
    public function index()
    {
        $localidades = Localidad::orderBy('nombre')->get();
        return view('admin.localidades', compact('localidades'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'    => 'required|string|max:100|unique:ia_localidades,nombre',
            'provincia' => 'nullable|string|max:100',
            'activo'    => 'boolean',
        ]);
        $data['activo']       = $request->boolean('activo', true);
        $data['dias_reparto'] = $this->buildDiasReparto($request);
        $this->fillRecordatorios($request, $data);

        Localidad::create($data);
        return redirect()->route('admin.localidades')->with('ok', 'Localidad creada.');
    }

    public function update(Request $request, int $id)
    {
        $localidad = Localidad::findOrFail($id);
        $data = $request->validate([
            'nombre'    => 'required|string|max:100|unique:ia_localidades,nombre,' . $localidad->id,
            'provincia' => 'nullable|string|max:100',
            'activo'    => 'boolean',
        ]);
        $data['activo']       = $request->boolean('activo', true);
        $data['dias_reparto'] = $this->buildDiasReparto($request);
        $this->fillRecordatorios($request, $data);

        $localidad->update($data);
        return redirect()->route('admin.localidades')->with('ok', 'Localidad actualizada.');
    }

    private function fillRecordatorios(Request $request, array &$data): void
    {
        $data['rec_apertura']          = $request->boolean('rec_apertura');
        $data['rec_apertura_mensaje']  = $request->input('rec_apertura_mensaje') ?: null;
        $data['rec_apertura_template'] = $request->input('rec_apertura_template') ?: null;
        $data['rec_cierre']            = $request->boolean('rec_cierre');
        $data['rec_cierre_horas']      = $request->filled('rec_cierre_horas') ? (int) $request->input('rec_cierre_horas') : null;
        $data['rec_cierre_mensaje']    = $request->input('rec_cierre_mensaje') ?: null;
        $data['rec_cierre_template']   = $request->input('rec_cierre_template') ?: null;
    }

    private function buildDiasReparto(Request $request): ?array
    {
        $activos = $request->input('dias_activos', []);
        $configs = $request->input('dias', []);

        if (empty($activos)) return null;

        $result = [];
        foreach ($activos as $dia) {
            $dia  = (int) $dia;
            $cfg  = $configs[$dia] ?? [];
            $entry = ['dia' => $dia];
            if (!empty($cfg['desde_dia']) || !empty($cfg['desde_hora'])) {
                $entry['desde_dia']  = isset($cfg['desde_dia'])  && $cfg['desde_dia']  !== '' ? (int) $cfg['desde_dia']  : null;
                $entry['desde_hora'] = !empty($cfg['desde_hora']) ? $cfg['desde_hora'] : null;
            }
            if (!empty($cfg['hasta_dia']) || !empty($cfg['hasta_hora'])) {
                $entry['hasta_dia']  = isset($cfg['hasta_dia'])  && $cfg['hasta_dia']  !== '' ? (int) $cfg['hasta_dia']  : null;
                $entry['hasta_hora'] = !empty($cfg['hasta_hora']) ? $cfg['hasta_hora'] : null;
            }
            if (!empty($cfg['horario_reparto'])) {
                $entry['horario_reparto'] = trim($cfg['horario_reparto']);
            }
            $result[] = $entry;
        }
        return $result ?: null;
    }

    public function destroy(int $id)
    {
        $localidad = Localidad::findOrFail($id);
        $localidad->delete();
        return redirect()->route('admin.localidades')->with('ok', 'Localidad eliminada.');
    }

    public function toggle(int $id)
    {
        $localidad = Localidad::findOrFail($id);
        $localidad->update(['activo' => !$localidad->activo]);
        return response()->json(['activo' => $localidad->activo]);
    }

    public function probar(Request $request, int $id)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'tipo'  => 'required|in:apertura,cierre',
            'dia'   => 'required|integer|between:0,6',
        ]);

        $localidad = Localidad::findOrFail($id);
        $phone     = preg_replace('/\D/', '', $request->input('phone'));
        $tipo      = $request->input('tipo');
        $dia       = (int) $request->input('dia');

        $diasLabel = \App\Models\IaEmpresa::DIAS_LABEL;
        $hoy  = now();
        $diff = ($dia - (int)$hoy->dayOfWeek + 7) % 7 ?: 7;
        $fechaReparto = $hoy->copy()->addDays($diff)->startOfDay();
        $diaTexto     = $fechaReparto->locale('es')->isoFormat('dddd D [de] MMMM');

        $mensaje  = $tipo === 'apertura' ? ($localidad->rec_apertura_mensaje ?? '') : ($localidad->rec_cierre_mensaje ?? '');
        $template = $tipo === 'apertura' ? ($localidad->rec_apertura_template ?? '') : ($localidad->rec_cierre_template ?? '');
        $horas    = $localidad->rec_cierre_horas ?? 0;

        $texto    = str_replace(['{nombre}', '{dia_reparto}', '{horas}'], ['Cliente', $diaTexto, $horas], $mensaje);
        $catalogo = $this->buildCatalogo($localidad, $dia);

        $bot = app(BotService::class);
        try {
            if ($template && $catalogo) {
                $bot->sendRecordatorioTemplate($phone, $template, 'Cliente', $texto);
                sleep(1);
                $bot->sendWhatsapp($phone, $catalogo);
            } elseif ($template) {
                $bot->sendRecordatorioTemplate($phone, $template, 'Cliente', $texto);
            } else {
                $mensajeCompleto = $catalogo ? $texto . "\n\n" . $catalogo : $texto;
                $bot->sendWhatsapp($phone, $mensajeCompleto);
            }

            $preview = $texto . ($catalogo ? "\n\n" . $catalogo : '');
            return response()->json(['ok' => true, 'mensaje' => $preview]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function precios(Request $request, int $id)
    {
        $localidad     = Localidad::findOrFail($id);
        $todas         = Localidad::orderBy('nombre')->get();
        $search        = $request->input('search');
        $diasLocConfig = $localidad->diasConfig();

        $productos = Producto::paraBot()
            ->when($search, fn($q) => $q->where('tablaplu.des', 'like', "%{$search}%"))
            ->orderBy('tablaplu.desgrupo')
            ->orderBy('tablaplu.des')
            ->get();

        $plConfigs = ProductoLocalidad::where('localidad_id', $localidad->id)
            ->get()
            ->keyBy('cod');

        return view('admin.localidad-precios', compact('localidad', 'todas', 'productos', 'plConfigs', 'diasLocConfig', 'search'));
    }

    public function preciosBulkDias(int $id)
    {
        ProductoLocalidad::where('localidad_id', $id)->update(['dias_reparto' => null]);
        $this->limpiarCache();
        return response()->json(['ok' => true]);
    }

    public function precioUpsert(Request $request, int $id, $cod)
    {
        $data = $request->validate([
            'precio'       => 'nullable|numeric|min:0',
            'dias_reparto' => 'nullable|array',
        ]);

        $update = [];
        if (array_key_exists('precio', $data)) {
            $update['precio'] = isset($data['precio']) && $data['precio'] !== '' ? (float) $data['precio'] : null;
        }
        if (array_key_exists('dias_reparto', $data)) {
            $update['dias_reparto'] = !empty($data['dias_reparto']) ? $data['dias_reparto'] : null;
        }

        ProductoLocalidad::updateOrCreate(
            ['cod' => $cod, 'localidad_id' => $id],
            $update
        );

        $this->limpiarCache();
        return response()->json(['ok' => true]);
    }

    public function precioRemove(int $id, $cod)
    {
        ProductoLocalidad::where('cod', $cod)->where('localidad_id', $id)->delete();
        $this->limpiarCache();
        return response()->json(['ok' => true]);
    }

    private function limpiarCache(): void
    {
        $tenantId = app(TenantManager::class)->get()?->id ?? 0;
        Cache::forget('bot_mas_vendidos_' . $tenantId);
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
            if ($override && $override->precio !== null) $p->precio = $override->precio;
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
                    $lineas[] = "• {$p->des} — {$formatPrecio($p)}" . ($tipo === 'Peso' ? '/kg' : '/u');
                }
                $lineas[] = '';
            }
        }
        return trim(implode("\n", $lineas));
    }
}
