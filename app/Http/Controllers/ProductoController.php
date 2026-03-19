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

    public function uploadImagen(Request $request, int $id)
    {
        $producto = Producto::findOrFail($id);
        $request->validate(['imagen' => 'required|image|max:3072']);

        $dir = public_path('producto-images');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Borrar imagen anterior si existe
        if ($producto->imagen && $producto->imagen !== 'sinimagen.webp') {
            $anterior = public_path($producto->imagen);
            if (file_exists($anterior)) {
                unlink($anterior);
            }
        }

        $file = $request->file('imagen');
        $ext  = $file->getClientOriginalExtension();
        $name = 'producto-images/' . Str::slug($producto->des) . '-' . $producto->cod . '.' . $ext;

        copy($file->getRealPath(), public_path($name));

        $producto->update(['imagen' => $name]);

        Cache::forget('productos_bot_lista');
        Cache::forget('productos_bot_precios');

        return back()->with('success', "Imagen de {$producto->des} actualizada.");
    }

    public function updateDescripcion(Request $request, int $id)
    {
        $producto = Producto::findOrFail($id);
        $request->validate(['descripcion' => 'nullable|string|max:255']);

        $producto->update(['descripcion' => $request->input('descripcion', '')]);

        Cache::forget('productos_bot_lista');
        Cache::forget('productos_bot_precios');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', "Descripción de {$producto->des} actualizada.");
    }

    public function deleteImagen(int $id)
    {
        $producto = Producto::findOrFail($id);
        if ($producto->imagen && $producto->imagen !== 'sinimagen.webp') {
            $path = public_path($producto->imagen);
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $producto->update(['imagen' => 'sinimagen.webp']);

        Cache::forget('productos_bot_lista');
        Cache::forget('productos_bot_precios');

        return back()->with('success', "Imagen eliminada.");
    }
}
