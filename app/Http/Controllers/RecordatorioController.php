<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Localidad;
use App\Models\Message;
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
            'nombre'           => 'required|string|max:100',
            'mensaje'          => 'required|string',
            'imagen_url'       => 'nullable|url|max:500',
            'template_nombre'  => 'nullable|string|max:200',
            'filtro_localidad' => 'nullable|string|max:100',
            'filtro_provincia' => 'nullable|string|max:100',
            'dias'             => 'nullable|array',
            'dias.*'           => 'integer|between:0,6',
            'hora'             => 'required|date_format:H:i',
            'activo'           => 'boolean',
        ]);

        $data['activo'] = $request->boolean('activo', true);
        $data['dias']   = !empty($data['dias']) ? $data['dias'] : null;

        Recordatorio::create($data);

        return redirect()->route('admin.recordatorios')->with('ok', 'Recordatorio creado.');
    }

    public function edit(int $id)
    {
        $rec           = Recordatorio::findOrFail($id);
        $recordatorios = Recordatorio::orderByDesc('id')->get();
        $localidades   = Localidad::where('activo', true)->orderBy('nombre')->get();
        $provincias    = Localidad::where('activo', true)->whereNotNull('provincia')->distinct()->orderBy('provincia')->pluck('provincia');

        return view('admin.recordatorios', compact('recordatorios', 'localidades', 'provincias') + ['editando' => $rec]);
    }

    public function update(Request $request, int $id)
    {
        $rec  = Recordatorio::findOrFail($id);
        $data = $request->validate([
            'nombre'           => 'required|string|max:100',
            'mensaje'          => 'required|string',
            'imagen_url'       => 'nullable|url|max:500',
            'template_nombre'  => 'nullable|string|max:200',
            'filtro_localidad' => 'nullable|string|max:100',
            'filtro_provincia' => 'nullable|string|max:100',
            'dias'             => 'nullable|array',
            'dias.*'           => 'integer|between:0,6',
            'hora'             => 'required|date_format:H:i',
            'activo'           => 'boolean',
        ]);

        $data['activo'] = $request->boolean('activo', true);
        $data['dias']   = !empty($data['dias']) ? $data['dias'] : null;

        $rec->update($data);

        return redirect()->route('admin.recordatorios')->with('ok', 'Recordatorio actualizado.');
    }

    public function destroy(int $id)
    {
        Recordatorio::findOrFail($id)->delete();
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

        $cliente = Cliente::where('phone', $phone)->with('cuenta')->first()
            ?? new Cliente(['phone' => $phone, 'name' => 'Prueba']);

        $mensaje   = str_replace('{nombre}', $cliente->name ?? 'cliente', $recordatorio->mensaje);
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

            return response()->json(['ok' => true, 'mensaje' => $mensaje]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
