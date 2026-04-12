<?php

namespace App\Http\Controllers;

use App\Models\Localidad;
use App\Models\Pedidosia;
use App\Models\Producto;
use App\Models\ProductoLocalidad;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LocalidadController extends Controller
{
    public function index()
    {
        $localidades = Localidad::orderBy('nombre')->get();

        // Solo recordatorios express (con flash_localidades o productos_flash)
        $todosRecs   = \App\Models\Recordatorio::orderBy('hora')->get();
        $recsPorLoc  = [];
        foreach ($localidades as $loc) {
            $recsPorLoc[$loc->id] = $todosRecs->filter(function ($r) use ($loc) {
                if (empty($r->productos_flash) && empty($r->flash_localidades)) return false;
                if (!empty($r->flash_localidades) && in_array($loc->nombre, $r->flash_localidades)) return true;
                return $r->filtro_localidad === $loc->nombre && !empty($r->productos_flash);
            })->values();
        }

        return view('admin.localidades', compact('localidades', 'recsPorLoc'));
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

        $localidad->update($data);
        return redirect()->route('admin.localidades')->with('ok', 'Localidad actualizada.');
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
        // Resetea dias_reparto a NULL en todos los productos de la localidad
        // → cada producto hereda todos los días de la localidad
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

    public function flashProductos(int $id)
    {
        $localidad = Localidad::findOrFail($id);
        $configs   = ProductoLocalidad::where('localidad_id', $localidad->id)->get()->keyBy('cod');

        $productos = Producto::paraBot()
            ->orderBy('tablaplu.des')
            ->get()
            ->filter(fn($p) => $configs->has($p->cod))
            ->map(fn($p) => [
                'cod'    => $p->cod,
                'des'    => $p->des,
                'precio' => $configs->get($p->cod)?->precio !== null
                    ? (float) $configs->get($p->cod)->precio
                    : (float) $p->precio,
                'tipo'   => $p->tipo,
            ])->values();

        return response()->json($productos);
    }

    public function flashActivar(Request $request, int $id)
    {
        $request->validate([
            'nombre'                 => 'nullable|string|max:100',
            'horas'                  => 'required|integer|min:1|max:168',
            'productos'              => 'nullable|array',
            'seguimiento_horas_antes'=> 'nullable|integer|min:1|max:100',
            'seguimiento_mensaje'    => 'nullable|string|max:500',
        ]);

        $localidad = Localidad::findOrFail($id);
        $tenant    = app(TenantManager::class)->get();
        if (!$tenant) return response()->json(['ok' => false, 'error' => 'Sin tenant'], 500);

        $horas    = (int) $request->input('horas');
        $expiraEn = now()->addHours($horas);
        $sessionId = uniqid('flash_', true);

        $nueva = [
            'id'                      => $sessionId,
            'nombre'                  => $request->filled('nombre') ? $request->input('nombre') : "Express — {$localidad->nombre}",
            'productos'               => !empty($request->input('productos')) ? $request->input('productos') : null,
            'activado_en'             => now()->toISOString(),
            'expira_en'               => $expiraEn->toISOString(),
            'seguimiento_horas_antes' => $request->filled('seguimiento_horas_antes') ? (int) $request->input('seguimiento_horas_antes') : null,
            'seguimiento_mensaje'     => $request->input('seguimiento_mensaje') ?: null,
        ];

        $key      = "flash_orders_{$tenant->id}_{$localidad->id}";
        $existing = Cache::get($key, []);
        // Filtrar expiradas
        $existing = array_values(array_filter(
            is_array($existing) ? $existing : [],
            fn($s) => isset($s['expira_en']) && \Carbon\Carbon::parse($s['expira_en'])->isFuture()
        ));
        $existing[] = $nueva;

        $maxExpira = collect($existing)->max(fn($s) => $s['expira_en']);
        Cache::put($key, $existing, Carbon::parse((string) $maxExpira));

        return response()->json([
            'ok'        => true,
            'session_id'=> $sessionId,
            'expira_en' => $nueva['expira_en'],
            'sessions'  => $existing,
        ]);
    }

    public function flashDesactivar(Request $request, int $id)
    {
        $localidad = Localidad::findOrFail($id);
        $tenant    = app(TenantManager::class)->get();
        if (!$tenant) return response()->json(['ok' => false, 'error' => 'Sin tenant'], 500);

        $key      = "flash_orders_{$tenant->id}_{$localidad->id}";
        $sessions = Cache::get($key, []);
        $sessionId = $request->input('session_id');

        if ($sessionId) {
            // Eliminar solo la sesión indicada
            $sessions = array_values(array_filter(
                is_array($sessions) ? $sessions : [],
                fn($s) => ($s['id'] ?? null) !== $sessionId
            ));
            if (empty($sessions)) {
                Cache::forget($key);
            } else {
                $maxExpira = collect($sessions)->max(fn($s) => $s['expira_en']);
                Cache::put($key, $sessions, Carbon::parse((string) $maxExpira));
            }
        } else {
            // Sin ID: limpiar todo
            Cache::forget($key);
            $sessions = [];
        }

        return response()->json(['ok' => true, 'sessions' => $sessions]);
    }

    public function flashEstado(int $id)
    {
        $localidad = Localidad::findOrFail($id);
        $tenant    = app(TenantManager::class)->get();
        if (!$tenant) return response()->json(['activo' => false, 'sessions' => []]);

        $key      = "flash_orders_{$tenant->id}_{$localidad->id}";
        $all      = Cache::get($key, []);
        $sessions = array_values(array_filter(
            is_array($all) ? $all : [],
            fn($s) => isset($s['expira_en']) && \Carbon\Carbon::parse($s['expira_en'])->isFuture()
        ));

        if (empty($sessions)) return response()->json(['activo' => false, 'sessions' => []]);

        // Contar pedidos desde la sesión más antigua activa
        $masAntigua = collect($sessions)->min(fn($s) => $s['activado_en'] ?? now()->toISOString());
        $pedidos = Pedidosia::where('pedido_at', '>=', \Carbon\Carbon::parse($masAntigua))
            ->where('estado', '!=', Pedidosia::ESTADO_CANCELADO)
            ->whereHas('cliente', fn($q) => $q->where('localidad_id', $localidad->id))
            ->count();

        return response()->json([
            'activo'   => true,
            'sessions' => $sessions,
            'pedidos'  => $pedidos,
        ]);
    }

    private function limpiarCache(): void
    {
        $tenantId = app(TenantManager::class)->get()?->id ?? 0;
        Cache::forget('bot_mas_vendidos_' . $tenantId);
    }

}
