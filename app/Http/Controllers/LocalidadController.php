<?php

namespace App\Http\Controllers;

use App\Models\Localidad;
use Illuminate\Http\Request;

class LocalidadController extends Controller
{
    public function index()
    {
        return view('admin.localidades', [
            'localidades' => Localidad::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'         => 'required|string|max:100|unique:localidades,nombre',
            'provincia'      => 'nullable|string|max:100',
            'dias_reparto'   => 'nullable|array',
            'dias_reparto.*' => 'integer|between:0,6',
            'costo_extra'    => 'nullable|numeric|min:0',
            'activo'         => 'boolean',
        ]);
        $data['activo']       = $request->boolean('activo', true);
        $data['dias_reparto'] = !empty($data['dias_reparto']) ? $data['dias_reparto'] : null;
        $data['costo_extra']  = $data['costo_extra'] ?? 0;

        Localidad::create($data);
        return redirect()->route('admin.localidades')->with('ok', 'Localidad creada.');
    }

    public function update(Request $request, Localidad $localidad)
    {
        $data = $request->validate([
            'nombre'         => 'required|string|max:100|unique:localidades,nombre,' . $localidad->id,
            'provincia'      => 'nullable|string|max:100',
            'dias_reparto'   => 'nullable|array',
            'dias_reparto.*' => 'integer|between:0,6',
            'costo_extra'    => 'nullable|numeric|min:0',
            'activo'         => 'boolean',
        ]);
        $data['activo']       = $request->boolean('activo', true);
        $data['dias_reparto'] = !empty($data['dias_reparto']) ? $data['dias_reparto'] : null;
        $data['costo_extra']  = $data['costo_extra'] ?? 0;

        $localidad->update($data);
        return redirect()->route('admin.localidades')->with('ok', 'Localidad actualizada.');
    }

    public function destroy(Localidad $localidad)
    {
        $localidad->delete();
        return redirect()->route('admin.localidades')->with('ok', 'Localidad eliminada.');
    }

    public function toggle(Localidad $localidad)
    {
        $localidad->update(['activo' => !$localidad->activo]);
        return response()->json(['activo' => $localidad->activo]);
    }
}
