<?php

namespace App\Http\Controllers;

use App\Models\IaFlujo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache as CacheFacade;

class FlujoController extends Controller
{
    public function index()
    {
        $flujos = IaFlujo::orderByDesc('updated_at')->get();
        return view('admin.flujos', compact('flujos'));
    }

    public function crear()
    {
        return view('admin.flujo-editor', ['flujo' => null]);
    }

    public function editar(IaFlujo $flujo)
    {
        return view('admin.flujo-editor', compact('flujo'));
    }

    public function store(Request $request)
    {
        $request->validate(['nombre' => 'required|string|max:100']);
        $flujo = IaFlujo::create([
            'nombre'     => $request->nombre,
            'definicion' => $request->definicion ? json_decode($request->definicion, true) : null,
        ]);
        return response()->json(['id' => $flujo->id, 'ok' => true]);
    }

    public function update(Request $request, IaFlujo $flujo)
    {
        $request->validate(['nombre' => 'required|string|max:100']);
        $flujo->update([
            'nombre'     => $request->nombre,
            'definicion' => $request->definicion ? json_decode($request->definicion, true) : null,
        ]);
        CacheFacade::forget('flujo_activo');
        return response()->json(['ok' => true]);
    }

    public function destroy(IaFlujo $flujo)
    {
        $flujo->delete();
        return response()->json(['ok' => true]);
    }

    public function activar(IaFlujo $flujo)
    {
        // Solo uno activo a la vez
        IaFlujo::where('id', '!=', $flujo->id)->update(['activo' => false]);
        $flujo->update(['activo' => !$flujo->activo]);
        return response()->json(['activo' => $flujo->activo]);
    }
}
