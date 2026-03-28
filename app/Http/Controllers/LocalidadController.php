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
            'nombre'      => 'required|string|max:100|unique:ia_localidades,nombre',
            'provincia'   => 'nullable|string|max:100',
            'costo_extra' => 'nullable|numeric|min:0',
            'activo'      => 'boolean',
        ]);
        $data['activo']       = $request->boolean('activo', true);
        $data['costo_extra']  = $data['costo_extra'] ?? 0;
        $data['dias_reparto'] = $this->buildDiasReparto($request);

        Localidad::create($data);
        return redirect()->route('admin.localidades')->with('ok', 'Localidad creada.');
    }

    public function update(Request $request, int $id)
    {
        $localidad = Localidad::findOrFail($id);
        $data = $request->validate([
            'nombre'      => 'required|string|max:100|unique:ia_localidades,nombre,' . $localidad->id,
            'provincia'   => 'nullable|string|max:100',
            'costo_extra' => 'nullable|numeric|min:0',
            'activo'      => 'boolean',
        ]);
        $data['activo']       = $request->boolean('activo', true);
        $data['costo_extra']  = $data['costo_extra'] ?? 0;
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
}
