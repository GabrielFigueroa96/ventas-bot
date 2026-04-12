<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Localidad;
use App\Models\Message;
use App\Models\Producto;
use App\Models\ProductoLocalidad;
use App\Models\Recordatorio;
use App\Services\BotService;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RecordatorioController extends Controller
{
    public function index()
    {
        $recordatorios = Recordatorio::orderByDesc('id')->get();
        $localidades   = Localidad::where('activo', true)->orderBy('nombre')->get();
        $provincias    = Localidad::where('activo', true)->whereNotNull('provincia')->distinct()->orderBy('provincia')->pluck('provincia');

        return view('admin.recordatorios', compact('recordatorios', 'localidades', 'provincias'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'            => 'required|string|max:100',
            'mensaje'           => 'required|string',
            'imagen_url'        => 'nullable|url|max:500',
            'template_nombre'   => 'nullable|string|max:200',
            'filtro_localidad'  => 'nullable|string|max:100',
            'filtro_provincia'  => 'nullable|string|max:100',
            'dias'              => 'nullable|array',
            'dias.*'            => 'integer|between:0,6',
            'hora'              => 'required|date_format:H:i',
            'activo'            => 'boolean',
        ]);

        $data['activo']                   = $request->boolean('activo', true);
        $data['dias']                     = !empty($data['dias']) ? $data['dias'] : null;
        $data['productos_flash']          = $this->parseProductosFlash($request);
        $data['flash_localidades']        = $this->parseFlashLocalidades($request);
        $data['flash_horas']              = $request->filled('flash_horas') ? (int) $request->input('flash_horas') : 24;
        $data['seguimiento_horas_antes']  = $request->filled('seguimiento_horas_antes') ? (int) $request->input('seguimiento_horas_antes') : null;
        $data['seguimiento_mensaje']      = $request->filled('seguimiento_mensaje') ? $request->input('seguimiento_mensaje') : null;

        Recordatorio::create($data);

        return redirect()->route('admin.recordatorios')->with('ok', 'Recordatorio creado.');
    }

    public function edit(int $id)
    {
        $rec = Recordatorio::findOrFail($id);
        $recordatorios = Recordatorio::orderByDesc('id')->get();
        $localidades   = Localidad::where('activo', true)->orderBy('nombre')->get();
        $provincias    = Localidad::where('activo', true)->whereNotNull('provincia')->distinct()->orderBy('provincia')->pluck('provincia');

        return view('admin.recordatorios', compact('recordatorios', 'localidades', 'provincias') + ['editando' => $rec]);
    }

    public function update(Request $request, int $id)
    {
        $rec = Recordatorio::findOrFail($id);
        $data = $request->validate([
            'nombre'            => 'required|string|max:100',
            'mensaje'           => 'required|string',
            'imagen_url'        => 'nullable|url|max:500',
            'template_nombre'   => 'nullable|string|max:200',
            'filtro_localidad'  => 'nullable|string|max:100',
            'filtro_provincia'  => 'nullable|string|max:100',
            'dias'              => 'nullable|array',
            'dias.*'            => 'integer|between:0,6',
            'hora'              => 'required|date_format:H:i',
            'activo'            => 'boolean',
        ]);

        $data['activo']                   = $request->boolean('activo', true);
        $data['dias']                     = !empty($data['dias']) ? $data['dias'] : null;
        $data['productos_flash']          = $this->parseProductosFlash($request);
        $data['flash_localidades']        = $this->parseFlashLocalidades($request);
        $data['flash_horas']              = $request->filled('flash_horas') ? (int) $request->input('flash_horas') : 24;
        $data['seguimiento_horas_antes']  = $request->filled('seguimiento_horas_antes') ? (int) $request->input('seguimiento_horas_antes') : null;
        $data['seguimiento_mensaje']      = $request->filled('seguimiento_mensaje') ? $request->input('seguimiento_mensaje') : null;

        $rec->update($data);

        return redirect()->route('admin.recordatorios')->with('ok', 'Recordatorio actualizado.');
    }

    public function productosLocalidad(Request $request)
    {
        $request->validate(['localidad_nombre' => 'required|string']);

        $localidad = Localidad::where('nombre', $request->input('localidad_nombre'))
            ->where('activo', true)
            ->firstOrFail();

        $prodLocConfigs = ProductoLocalidad::where('localidad_id', $localidad->id)
            ->get()->keyBy('cod');

        $productos = Producto::paraBot()
            ->orderBy('tablaplu.des')
            ->get()
            ->filter(fn($p) => $prodLocConfigs->has($p->cod))
            ->map(function ($p) use ($prodLocConfigs) {
                $override = $prodLocConfigs->get($p->cod);
                return [
                    'cod'    => $p->cod,
                    'des'    => $p->des,
                    'precio' => $override?->precio !== null ? (float) $override->precio : (float) $p->precio,
                    'tipo'   => $p->tipo,
                ];
            })->values();

        return response()->json($productos);
    }

    public function destroy(int $id)
    {
        $rec = Recordatorio::findOrFail($id);
        $rec->delete();
        return redirect()->route('admin.recordatorios')->with('ok', 'Recordatorio eliminado.');
    }

    public function toggle(int $id)
    {
        $rec = Recordatorio::findOrFail($id);
        $rec->update(['activo' => !$rec->activo]);
        return response()->json(['activo' => $rec->activo]);
    }

    public function probar(Request $request, int $rec)
    {
        $request->validate(['phone' => 'required|string|max:20']);

        $recordatorio = Recordatorio::findOrFail($rec);
        $phone        = preg_replace('/\D/', '', $request->input('phone'));

        // Buscar cliente por teléfono o usar uno ficticio
        $cliente = Cliente::where('phone', $phone)->with('cuenta')->first();
        if (!$cliente) {
            $cliente = new Cliente(['phone' => $phone, 'name' => 'Prueba']);
        }

        $mensaje   = $this->construirMensaje($recordatorio, $cliente);
        $imagenUrl = $recordatorio->imagen_url ?? null;
        $template  = trim($recordatorio->template_nombre ?? '');
        $nombre    = $cliente->name ?? 'cliente';

        $bot = app(BotService::class);

        try {
            if ($template) {
                $bot->sendRecordatorioTemplate($phone, $template, $nombre, $mensaje);
            } elseif ($imagenUrl) {
                $bot->sendWhatsappImageByUrl($phone, $imagenUrl, $mensaje);
            } else {
                $bot->sendWhatsapp($phone, $mensaje);
            }

            if ($cliente->id) {
                Message::create([
                    'cliente_id' => $cliente->id,
                    'message'    => "[Recordatorio: {$recordatorio->nombre}]\n{$mensaje}",
                    'direction'  => 'outgoing',
                ]);
            }

            $this->activarFlashSiCorresponde($recordatorio);

            return response()->json(['ok' => true, 'mensaje' => $mensaje]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function construirMensaje(Recordatorio $rec, Cliente $cliente): string
    {
        $nombre = $cliente->name ?? 'cliente';
        return str_replace('{nombre}', $nombre, $rec->mensaje);
    }

    private function parseProductosFlash(Request $request): ?array
    {
        $raw = $request->input('productos_flash');
        if (!$raw) return null;
        $decoded = json_decode($raw, true);
        return (is_array($decoded) && count($decoded) > 0) ? $decoded : null;
    }

    private function parseFlashLocalidades(Request $request): ?array
    {
        $raw = $request->input('flash_localidades');
        if (!$raw) return null;
        $decoded = json_decode($raw, true);
        return (is_array($decoded) && count($decoded) > 0) ? $decoded : null;
    }

    private function activarFlashSiCorresponde(Recordatorio $recordatorio): void
    {
        // Requiere localidades configuradas (el selector de localidades es la señal de "modo express activo")
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
            $localidad = Localidad::where('nombre', $nombre)->where('activo', true)->first();
            if (!$localidad) continue;

            $key      = "flash_orders_{$tenant->id}_{$localidad->id}";
            $existing = Cache::get($key, []);
            $existing = array_values(array_filter(
                is_array($existing) ? $existing : [],
                fn($s) => isset($s['expira_en']) && \Carbon\Carbon::parse($s['expira_en'])->isFuture()
            ));
            $existing[] = $nueva;

            $maxExpira = collect($existing)->max(fn($s) => $s['expira_en']);
            Cache::put($key, $existing, \Carbon\Carbon::parse((string) $maxExpira));
        }
    }

}
