<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Message;
use App\Services\BotService;
use Illuminate\Http\Request;

class AdminChatController extends Controller
{
    public function mensajesNuevos(Cliente $cliente, Request $request)
    {
        $desdeId = (int) $request->input('since', 0);

        $mensajes = Message::where('cliente_id', $cliente->id)
            ->where('id', '>', $desdeId)
            ->oldest('id')
            ->get(['id', 'message', 'direction', 'type', 'created_at']);

        return response()->json($mensajes);
    }

    public function tomarControl(Cliente $cliente)
    {
        $cliente->update(['modo' => 'humano']);
        return back()->with('success', 'Tomaste el control del chat.');
    }

    public function liberarControl(Cliente $cliente)
    {
        $cliente->update(['modo' => 'bot']);
        return back()->with('success', 'El bot retomó el control.');
    }

    public function enviar(Request $request, Cliente $cliente)
    {
        $request->validate([
            'mensaje' => 'nullable|string|max:4096',
            'archivo' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf|max:16384',
        ]);

        $bot = app(BotService::class);

        if ($request->hasFile('archivo')) {
            $file     = $request->file('archivo');
            $mime     = $file->getMimeType();
            $isImage  = str_starts_with($mime, 'image/');
            $mediaId  = $bot->uploadMedia($file);
            $caption  = $request->input('mensaje', '');

            $bot->sendWhatsappMedia($cliente->phone, $mediaId, $isImage ? 'image' : 'document', $caption);

            $texto = $isImage
                ? '[Imagen enviada]' . ($caption ? ": {$caption}" : '')
                : '[PDF enviado]'   . ($caption ? ": {$caption}" : '');
        } else {
            $texto = $request->input('mensaje');
            $bot->sendWhatsapp($cliente->phone, $texto);
        }

        Message::create([
            'cliente_id' => $cliente->id,
            'message'    => $texto,
            'direction'  => 'outgoing',
            'type'       => $request->hasFile('archivo') ? 'media' : 'text',
        ]);

        return back();
    }
}
