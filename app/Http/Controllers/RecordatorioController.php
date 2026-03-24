<?php

namespace App\Http\Controllers;

use App\Models\Localidad;
use App\Models\Recordatorio;
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
            'tipo'              => 'required|in:libre,recomendacion,repetir_pedido',
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
            'tipo'              => 'required|in:libre,recomendacion,repetir_pedido',
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
}
