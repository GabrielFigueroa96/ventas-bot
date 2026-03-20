<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Factventas;
use App\Models\Message;
use App\Models\Pedido;
use App\Models\Pedidosia;
use App\Services\BotService;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminChatController extends Controller
{
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
            ->get(['id', 'message', 'direction', 'type', 'media_path', 'created_at'])
            ->map(fn($m) => [
                'id'         => $m->id,
                'message'    => $m->message,
                'direction'  => $m->direction,
                'type'       => $m->type,
                'media_path' => $m->media_path ? asset($m->media_path) : null,
                'created_at' => $m->fecha,
            ]);

        return response()->json($mensajes);
    }

    public function tomarControl(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->update(['modo' => 'humano']);
        return back()->with('success', 'Tomaste el control del chat.');
    }

    public function liberarControl(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->update(['modo' => 'bot']);
        return back()->with('success', 'El bot retomó el control.');
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
        $lastReg    = (int) ($pedidosRaw->max('reg') ?? 0);

        $html = view('admin.partials.pedidos', compact('pedidos', 'factventas', 'pedidosia'))->render();

        return response()->json(['html' => $html, 'lastReg' => $lastReg]);
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
