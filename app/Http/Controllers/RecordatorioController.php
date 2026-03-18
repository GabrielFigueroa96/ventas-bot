<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\Recordatorio;
use Illuminate\Http\Request;

class RecordatorioController extends Controller
{
    public function index()
    {
        $recordatorios = Recordatorio::orderByDesc('id')->get();
        $localidades   = Cuenta::select('loca')->distinct()->orderBy('loca')->pluck('loca')->filter()->values();
        $provincias    = Cuenta::select('prov')->distinct()->orderBy('prov')->pluck('prov')->filter()->values();

        return view('admin.recordatorios', compact('recordatorios', 'localidades', 'provincias'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'            => 'required|string|max:100',
            'mensaje'           => 'required|string',
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

    public function edit(Recordatorio $rec)
    {
        $localidades = Cuenta::select('loca')->distinct()->orderBy('loca')->pluck('loca')->filter()->values();
        $provincias  = Cuenta::select('prov')->distinct()->orderBy('prov')->pluck('prov')->filter()->values();

        return view('admin.recordatorios', [
            'recordatorios' => Recordatorio::orderByDesc('id')->get(),
            'editando'      => $rec,
            'localidades'   => $localidades,
            'provincias'    => $provincias,
        ]);
    }

    public function update(Request $request, Recordatorio $rec)
    {
        $data = $request->validate([
            'nombre'            => 'required|string|max:100',
            'mensaje'           => 'required|string',
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

    public function destroy(Recordatorio $rec)
    {
        $rec->delete();
        return redirect()->route('admin.recordatorios')->with('ok', 'Recordatorio eliminado.');
    }

    public function toggle(Recordatorio $rec)
    {
        $rec->update(['activo' => !$rec->activo]);
        return response()->json(['activo' => $rec->activo]);
    }
}
