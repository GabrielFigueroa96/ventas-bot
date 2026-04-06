<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Localidad;
use App\Models\Message;
use App\Models\Producto;
use App\Models\ProductoLocalidad;
use App\Models\Recordatorio;
use App\Services\BotService;
use Illuminate\Http\Request;

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
            'tipo'              => 'required|in:libre,catalogo',
            'filtro_localidad'  => 'nullable|string|max:100',
            'filtro_provincia'  => 'nullable|string|max:100',
            'dias'              => 'nullable|array',
            'dias.*'            => 'integer|between:0,6',
            'hora'              => 'required|date_format:H:i',
            'activo'            => 'boolean',
        ]);

        $data['activo'] = $request->boolean('activo', true);
        $data['dias']   = !empty($data['dias']) ? $data['dias'] : null;

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
            'tipo'              => 'required|in:libre,catalogo',
            'filtro_localidad'  => 'nullable|string|max:100',
            'filtro_provincia'  => 'nullable|string|max:100',
            'dias'              => 'nullable|array',
            'dias.*'            => 'integer|between:0,6',
            'hora'              => 'required|date_format:H:i',
            'activo'            => 'boolean',
        ]);

        $data['activo'] = $request->boolean('activo', true);
        $data['dias']   = !empty($data['dias']) ? $data['dias'] : null;

        $rec->update($data);

        return redirect()->route('admin.recordatorios')->with('ok', 'Recordatorio actualizado.');
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

        $catalogo  = $recordatorio->tipo === 'catalogo' ? $this->buildCatalogo($recordatorio) : null;
        $mensaje   = $this->construirMensaje($recordatorio, $cliente, $catalogo);
        $imagenUrl = $recordatorio->imagen_url ?? null;
        $template  = trim($recordatorio->template_nombre ?? '');
        $nombre    = $cliente->name ?? 'cliente';

        $bot = app(BotService::class);

        try {
            if ($catalogo !== null && $template) {
                $bot->sendRecordatorioTemplate($phone, $template, $nombre, $mensaje);
                sleep(1);
                $bot->sendWhatsapp($phone, $catalogo);
            } elseif ($template) {
                $bot->sendRecordatorioTemplate($phone, $template, $nombre, $mensaje);
            } elseif ($imagenUrl) {
                $bot->sendWhatsappImageByUrl($phone, $imagenUrl, $mensaje);
            } else {
                $bot->sendWhatsapp($phone, $mensaje);
            }

            if ($cliente->id) {
                $historial = $catalogo !== null ? $mensaje . "\n\n" . $catalogo : $mensaje;
                Message::create([
                    'cliente_id' => $cliente->id,
                    'message'    => "[Recordatorio: {$recordatorio->nombre}]\n{$historial}",
                    'direction'  => 'outgoing',
                ]);
            }

            return response()->json(['ok' => true, 'mensaje' => $catalogo !== null ? $mensaje . "\n\n" . $catalogo : $mensaje]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function construirMensaje(Recordatorio $rec, Cliente $cliente, ?string $catalogo = null): string
    {
        $nombre  = $cliente->name ?? 'cliente';
        $mensaje = str_replace('{nombre}', $nombre, $rec->mensaje);

        if ($rec->tipo === 'catalogo') {
            $contenido = $catalogo ?? $this->buildCatalogo($rec);
            $mensaje   = str_replace('{catalogo}', $contenido, $mensaje);
        }

        return $mensaje;
    }

    private function buildCatalogo(Recordatorio $rec): string
    {
        $localidadObj = $rec->filtro_localidad
            ? Localidad::where('nombre', $rec->filtro_localidad)->where('activo', true)->first()
            : null;

        $todosProductos = Producto::paraBot()->orderBy('tablaplu.desgrupo')->orderBy('tablaplu.des')->get();

        if ($localidadObj) {
            $prodLocConfigs = ProductoLocalidad::where('localidad_id', $localidadObj->id)->get()->keyBy('cod');

            if ($prodLocConfigs->isNotEmpty()) {
                $productos = $todosProductos->filter(fn($p) => $prodLocConfigs->has($p->cod));

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
