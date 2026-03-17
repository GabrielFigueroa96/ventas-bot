<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $productos = Producto::where('PRE', '>', 0)
            ->when($search, fn($q) =>
                $q->where('des', 'like', "%{$search}%")
            )
            ->orderBy('des')
            ->get();

        return view('admin.productos', compact('productos'));
    }

    public function uploadImagen(Request $request, Producto $producto)
    {
        $request->validate(['imagen' => 'required|image|max:3072']);

        // Borrar imagen anterior si existe
        if ($producto->imagen && file_exists(storage_path('app/public/' . $producto->imagen))) {
            unlink(storage_path('app/public/' . $producto->imagen));
        }

        $ext  = $request->file('imagen')->getClientOriginalExtension();
        $name = 'productos/' . Str::slug($producto->des) . '-' . $producto->cod . '.' . $ext;

        $request->file('imagen')->storeAs('public/' . dirname($name), basename($name));
        $producto->update(['imagen' => $name]);

        // Invalidar cache del bot
        Cache::forget('productos_bot_lista');
        Cache::forget('productos_bot_precios');

        return back()->with('success', "Imagen de {$producto->des} actualizada.");
    }

    public function updateDescripcion(Request $request, Producto $producto)
    {
        $request->validate(['descripcion' => 'nullable|string|max:255']);

        $producto->update(['descripcion' => $request->input('descripcion', '')]);

        Cache::forget('productos_bot_lista');
        Cache::forget('productos_bot_precios');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', "Descripción de {$producto->des} actualizada.");
    }

    public function deleteImagen(Producto $producto)
    {
        if ($producto->imagen && file_exists(storage_path('app/public/' . $producto->imagen))) {
            unlink(storage_path('app/public/' . $producto->imagen));
        }

        $producto->update(['imagen' => null]);

        Cache::forget('productos_bot_lista');
        Cache::forget('productos_bot_precios');

        return back()->with('success', "Imagen eliminada.");
    }
}
