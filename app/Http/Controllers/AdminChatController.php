<?php

namespace App\Http\Controllers;

use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Factventas;
use App\Models\Localidad;
use App\Models\Message;
use App\Models\Pedido;
use App\Models\Pedidosia;
use App\Models\Vmayo;
use App\Services\BotService;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminChatController extends Controller
{
    public function conversaciones()
    {
        $clientes = Cliente::selectRaw('ia_clientes.*')
            ->selectSub(
                Message::select('message')->whereColumn('cliente_id', 'ia_clientes.id')->latest('id')->limit(1),
                'last_message'
            )
            ->selectSub(
                Message::select('direction')->whereColumn('cliente_id', 'ia_clientes.id')->latest('id')->limit(1),
                'last_direction'
            )
            ->selectSub(
                Message::select('created_at')->whereColumn('cliente_id', 'ia_clientes.id')->latest('id')->limit(1),
                'last_message_at'
            )
            ->whereHas('messages')
            ->orderByDesc('last_message_at')
            ->get();

        $localidades = $clientes->pluck('localidad')->filter()->unique()->sort()->values();

        return view('admin.conversaciones', compact('clientes', 'localidades'));
    }

    public function conversacionPanel(int $id)
    {
        $cliente  = Cliente::findOrFail($id);
        $mensajes = Message::where('cliente_id', $cliente->id)->oldest('id')->get();
        $lastId   = $mensajes->last()?->id ?? 0;

        return response()->json([
            'html'   => view('admin.partials.conversacion-panel', compact('cliente', 'mensajes'))->render(),
            'lastId' => $lastId,
            'modo'   => $cliente->modo,
            'pollUrl'  => route('admin.chat.mensajes', $cliente),
            'enviarUrl' => route('admin.chat.enviar', $cliente),
            'tomarUrl'  => route('admin.chat.tomar', $cliente),
            'liberarUrl' => route('admin.chat.liberar', $cliente),
        ]);
    }

    public function imprimir(Request $request, int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $desde = \Carbon\Carbon::parse($request->desde)->startOfDay();
        $hasta = \Carbon\Carbon::parse($request->hasta)->endOfDay();

        $mensajes = Message::where('cliente_id', $cliente->id)
            ->whereBetween('created_at', [$desde, $hasta])
            ->oldest('id')
            ->get();

        return view('admin.imprimir-conversacion', compact('cliente', 'mensajes', 'desde', 'hasta'));
    }

    public function mensajesNuevos(int $id, Request $request)
    {
        $cliente = Cliente::findOrFail($id);
        $desdeId = (int) $request->input('since', 0);

        $mensajes = Message::where('cliente_id', $cliente->id)
            ->where('id', '>', $desdeId)
            ->oldest('id')
            ->get(['id', 'message', 'direction', 'type', 'media_path', 'status', 'created_at'])
            ->map(fn($m) => [
                'id'         => $m->id,
                'message'    => $m->message,
                'direction'  => $m->direction,
                'type'       => $m->type,
                'media_path' => $m->media_path ? asset($m->media_path) : null,
                'status'     => $m->status,
                'created_at' => $m->fecha,
            ]);

        return response()->json($mensajes);
    }

    public function tomarControl(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->update(['modo' => 'humano']);
        return response()->json(['ok' => true]);
    }

    public function liberarControl(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->update(['modo' => 'bot']);
        return response()->json(['ok' => true]);
    }

    public function enviar(Request $request, int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $request->validate([
            'mensaje' => 'nullable|string|max:4096',
            'archivo' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf|max:16384',
        ]);

        $bot       = app(BotService::class);
        $tenantId  = app(TenantManager::class)->get()->id;
        $texto     = '';
        $mediaPath = null;
        $tipo      = 'text';

        try {
            if ($request->hasFile('archivo')) {
                $file    = $request->file('archivo');
                $mime    = $file->getMimeType();
                $isImage = str_starts_with($mime, 'image/');
                $caption = $request->input('mensaje', '');

                // Guardar localmente para mostrar en la conversación
                $filename  = "chat-images/{$tenantId}/" . Str::uuid() . '.' . $file->getClientOriginalExtension();
                if (!is_dir(public_path("chat-images/{$tenantId}"))) {
                    mkdir(public_path("chat-images/{$tenantId}"), 0755, true);
                }
                copy($file->getRealPath(), public_path($filename));
                $mediaPath = $filename;

                // Subir y enviar según canal
                if (str_starts_with($cliente->phone, 'ig_') || str_starts_with($cliente->phone, 'fb_')) {
                    $bot->sendReplyImage($cliente, url($filename), $caption);
                } else {
                    $mediaId = $bot->uploadMedia($file);
                    $bot->sendWhatsappMedia($cliente->phone, $mediaId, $isImage ? 'image' : 'document', $caption);
                }

                $texto = $caption;
                $tipo  = 'media';
            } else {
                $texto = $request->input('mensaje', '');
                $bot->sendReply($cliente, $texto);
            }
        } catch (\Throwable $e) {
            Log::error("AdminChat enviar error: {$e->getMessage()}");
            $jsonError = ['error' => $e->getMessage()];
            return $request->expectsJson()
                ? response()->json($jsonError, 500)
                : back()->withErrors(['archivo' => $e->getMessage()]);
        }

        $msg = Message::create([
            'cliente_id' => $cliente->id,
            'message'    => $texto,
            'direction'  => 'outgoing',
            'type'       => $tipo,
            'media_path' => $mediaPath,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'id'         => $msg->id,
                'message'    => $msg->message,
                'direction'  => $msg->direction,
                'type'       => $msg->type,
                'media_path' => $msg->media_path ? asset($msg->media_path) : null,
                'status'     => $msg->status,
                'created_at' => $msg->fecha,
            ]);
        }

        return back();
    }

    public function cuentaBuscar(Request $request)
    {
        $q = $request->input('q', '');

        $cuentas = Cuenta::where('nom', 'like', "%{$q}%")
            ->orWhere('cod', 'like', "%{$q}%")
            ->orderBy('nom')
            ->take(10)
            ->get(['cod', 'nom', 'dom', 'loca', 'prov']);

        return response()->json($cuentas);
    }

    public function setCuenta(Request $request, int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $request->validate(['cuenta_cod' => 'nullable|string']);
        $cuentaCod = $request->input('cuenta_cod');

        $cliente->update(['cuenta_cod' => $cuentaCod]);

        // Reasignar pedidos existentes creados con el id interno del bot
        if ($cuentaCod) {
            $cuenta = Cuenta::find($cuentaCod);
            if ($cuenta) {
                Pedido::where('codcli', $cliente->id)
                    ->update(['codcli' => $cuenta->cod, 'nomcli' => $cuenta->nom]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function pedidosPanel(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->load('cuenta');
        $codcli     = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
        $pedidosRaw = Pedido::where('codcli', $codcli)
            ->orderByDesc('reg')
            ->get();

        $pedidos    = $pedidosRaw->groupBy('nro');
        $factventas = $this->loadFactventas($pedidosRaw);
        $pedidosia  = Pedidosia::whereIn('nro', $pedidosRaw->pluck('nro')->unique())->get()->keyBy('nro');
        $vmayo      = $this->loadVmayo($pedidosia);
        $lastReg    = (int) ($pedidosRaw->max('reg') ?? 0);

        $html = view('admin.partials.pedidos', compact('pedidos', 'factventas', 'pedidosia', 'vmayo'))->render();

        return response()->json(['html' => $html, 'lastReg' => $lastReg]);
    }

    private function loadVmayo(\Illuminate\Support\Collection $pedidosia): \Illuminate\Support\Collection
    {
        $map = $pedidosia
            ->filter(fn($s) => !empty($s->vmayo_nro))
            ->pluck('vmayo_nro', 'nro');

        if ($map->isEmpty()) return collect();

        $rows = Vmayo::whereIn('nro', $map->values())->get();

        $result = collect();
        foreach ($map as $nroBot => $vmayoNro) {
            $items = $rows->where('nro', $vmayoNro)->values();
            if ($items->isNotEmpty()) {
                $result->put($nroBot, $items);
            }
        }
        return $result;
    }

    // ── Test bot ──────────────────────────────────────────────────────────

    public function testBot()
    {
        $localidades = Localidad::where('activo', true)->orderBy('nombre')->get();
        $cliente     = $this->getTestCliente();
        $mensajes    = Message::where('cliente_id', $cliente->id)->oldest('id')->get();
        return view('admin.test-bot', compact('localidades', 'cliente', 'mensajes'));
    }

    public function testBotMensaje(Request $request)
    {
        $request->validate(['mensaje' => 'required|string|max:4096']);

        $localidadId = $request->input('localidad_id');
        $cliente     = $this->getTestCliente($localidadId);
        $bot         = app(BotService::class);

        // Guardar mensaje entrante
        Message::create([
            'cliente_id' => $cliente->id,
            'message'    => $request->mensaje,
            'direction'  => 'incoming',
        ]);

        // Procesar con el bot
        try {
            $respuesta = $bot->process($cliente, $request->mensaje);
        } catch (\Throwable $e) {
            Log::error("TestBot error: {$e->getMessage()}");
            $respuesta = "❌ Error: {$e->getMessage()}";
        }

        // Guardar respuesta del bot
        if ($respuesta) {
            Message::create([
                'cliente_id' => $cliente->id,
                'message'    => $respuesta,
                'direction'  => 'outgoing',
            ]);
        }

        $mensajes = Message::where('cliente_id', $cliente->id)
            ->oldest('id')
            ->get(['id', 'message', 'direction', 'created_at']);

        return response()->json([
            'respuesta' => $respuesta,
            'mensajes'  => $mensajes->map(fn($m) => [
                'id'         => $m->id,
                'message'    => $m->message,
                'direction'  => $m->direction,
                'created_at' => $m->created_at->format('H:i'),
            ]),
        ]);
    }

    public function testBotEstado(Request $request)
    {
        $cliente = $this->getTestCliente($request->input('localidad_id'));
        $carrito = \App\Models\Carrito::where('cliente_id', $cliente->id)->latest()->first();

        $items = [];
        $total = 0;
        if ($carrito && !empty($carrito->items)) {
            foreach ($carrito->items as $item) {
                $cant = ($item['tipo'] !== 'Unidad' && ($item['kilos'] ?? 0) > 0)
                    ? number_format($item['kilos'], 3, ',', '.') . ' kg'
                    : ($item['cant'] ?? 0) . ' u';
                $items[] = ['des' => $item['des'], 'cant' => $cant, 'neto' => $item['neto']];
                $total  += $item['neto'];
            }
        }

        $fechaElegida = Cache::get('fecha_reparto_elegida_' . $cliente->id);

        return response()->json([
            'estado'       => $cliente->fresh()->estado ?? 'activo',
            'fecha_elegida'=> $fechaElegida
                ? \Carbon\Carbon::parse($fechaElegida)->locale('es')->isoFormat('dddd D [de] MMMM')
                : null,
            'items'        => $items,
            'total'        => $total,
        ]);
    }

    public function testBotReset(Request $request)
    {
        $localidadId = $request->input('localidad_id');
        $cliente     = $this->getTestCliente($localidadId);

        // Borrar mensajes, carrito, caché
        Message::where('cliente_id', $cliente->id)->delete();
        Carrito::where('cliente_id', $cliente->id)->delete();
        Cache::forget('fecha_reparto_elegida_' . $cliente->id);
        Cache::forget('proxima_fecha_entrega_' . $cliente->id);
        Cache::forget('pedido_conf_' . $cliente->id);
        Cache::forget('reg_nombre_' . $cliente->id);
        Cache::forget('reg_localidad_' . $cliente->id);
        Cache::forget('reg_calle_' . $cliente->id);
        $cliente->update(['estado' => 'activo', 'modo' => 'bot', 'memoria_ia' => null]);

        return response()->json(['ok' => true]);
    }

    public function testBotIaPaso(Request $request)
    {
        $request->validate([
            'objetivo'  => 'required|string|max:500',
            'historial' => 'nullable|array|max:40',
        ]);

        $objetivo  = $request->input('objetivo');
        $historial = $request->input('historial', []);

        $system = <<<PROMPT
Sos un cliente de una distribuidora de alimentos que está chateando con un bot de ventas por WhatsApp.
Tu objetivo es: {$objetivo}

Reglas:
- Respondé SOLO con el mensaje que mandarías como cliente (texto plano, como si fuera WhatsApp).
- Sé natural y conciso. No expliques lo que hacés.
- Si el objetivo ya fue cumplido (el pedido fue confirmado, la consulta fue respondida, etc.), respondé exactamente con la palabra: FIN
- Si el bot te pide elegir una opción numerada, elegí la primera (respondé "1").
- Si el bot te pide confirmar con sí/no, respondé "sí".
- Si el bot te pide un nombre, inventá uno (ej: "Juan").
- Si el bot te pide una dirección, inventá una (ej: "San Martín 123").
PROMPT;

        $messages = [['role' => 'system', 'content' => $system]];

        foreach ($historial as $h) {
            $role = ($h['direction'] ?? '') === 'incoming' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $h['message'] ?? ''];
        }

        // Si no hay historial, el cliente inicia la conversación
        if (empty($historial)) {
            $messages[] = ['role' => 'user', 'content' => 'Iniciá la conversación para cumplir el objetivo.'];
        }

        try {
            $response = Http::withToken(config('api.openai.key'))
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => 'gpt-4.1-mini',
                    'messages'    => $messages,
                    'max_tokens'  => 80,
                    'temperature' => 0.4,
                ]);

            $texto = trim($response->json('choices.0.message.content') ?? '');
        } catch (\Throwable $e) {
            Log::error("TestBot IA paso error: {$e->getMessage()}");
            return response()->json(['fin' => true, 'mensaje' => null]);
        }

        if (strtoupper($texto) === 'FIN' || $texto === '') {
            return response()->json(['fin' => true, 'mensaje' => null]);
        }

        return response()->json(['fin' => false, 'mensaje' => $texto]);
    }

    private function getTestCliente(?int $localidadId = null): Cliente
    {
        $tenantId = app(TenantManager::class)->get()?->id ?? 0;
        $phone    = 'test_admin_' . $tenantId;

        $cliente = Cliente::firstOrCreate(
            ['phone' => $phone],
            ['name' => '🧪 Test Admin', 'modo' => 'bot', 'estado' => 'activo']
        );

        // Actualizar localidad si se pasó una
        if ($localidadId !== null) {
            $loc = Localidad::find($localidadId);
            if ($loc) {
                $cliente->update([
                    'localidad_id' => $loc->id,
                    'localidad'    => $loc->nombre,
                ]);
                $cliente->refresh();
            }
        }

        return $cliente;
    }

    private function loadFactventas($pedidos): \Illuminate\Support\Collection
    {
        $pares = $pedidos
            ->where('estado', Pedido::ESTADO_FINALIZADO)
            ->whereNotNull('venta')
            ->where('venta', '>', 0)
            ->unique(fn($p) => "{$p->venta}-{$p->pv}")
            ->values();

        if ($pares->isEmpty()) {
            return collect();
        }

        $rows = Factventas::where(function ($q) use ($pares) {
            foreach ($pares as $p) {
                $q->orWhere(fn($s) => $s->where('nro', $p->venta)->where('pv', $p->pv));
            }
        })->get();

        return $rows->groupBy(fn($f) => "{$f->nro}-{$f->pv}");
    }
}
