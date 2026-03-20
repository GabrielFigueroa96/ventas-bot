<?php

namespace App\Http\Controllers;

use App\Models\IaProducto;
use App\Models\Producto;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $productos = Producto::where('PRE', '>', 0)
            ->with('iaProducto')
            ->when($search, fn($q) =>
                $q->where('des', 'like', "%{$search}%")
            )
            ->orderBy('des')
            ->get();

        return view('admin.productos', compact('productos'));
    }

    /** Agrega el producto al catálogo del bot (crea ia_productos si no existe). */
    public function agregarCatalogo(Producto $producto)
    {
        if (!$producto->iaProducto) {
            IaProducto::create([
                'tablaplu_id' => $producto->id,
                'precio'      => (float) $producto->PRE,
                'disponible'  => true,
            ]);
            Cache::forget('productos_bot_lista');
        }
        return back()->with('success', "{$producto->des} agregado al catálogo del bot.");
    }

    /** Quita el producto del catálogo del bot (elimina ia_productos). */
    public function quitarCatalogo(Producto $producto)
    {
        if ($producto->iaProducto) {
            // Borrar imagen si existe
            $img = $producto->iaProducto->imagen;
            if ($img && file_exists(public_path($img))) {
                unlink(public_path($img));
            }
            $producto->iaProducto->delete();
            Cache::forget('productos_bot_lista');
            Cache::forget('productos_bot_precios');
        }
        return back()->with('success', "{$producto->des} quitado del catálogo.");
    }

    /** Activa / desactiva la visibilidad del producto para el bot. */
    public function toggleDisponible(Producto $producto)
    {
        $ia = $producto->iaProducto;
        if (!$ia) {
            return back()->with('error', 'El producto no está en el catálogo del bot.');
        }
        $ia->update(['disponible' => !$ia->disponible]);
        Cache::forget('productos_bot_lista');
        return back();
    }

    public function updatePrecio(Request $request, Producto $producto)
    {
        $request->validate(['precio' => 'required|numeric|min:0']);

        $ia = $producto->iaProducto;
        if (!$ia) {
            return response()->json(['ok' => false, 'error' => 'No en catálogo'], 422);
        }
        $ia->update(['precio' => $request->input('precio')]);
        Cache::forget('productos_bot_lista');
        Cache::forget('productos_bot_precios');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', "Precio de {$producto->des} actualizado.");
    }

    public function uploadImagen(Request $request, Producto $producto)
    {
        $ia = $this->getOrCreateIa($producto);
        $tenantId = app(TenantManager::class)->get()->id;
        $request->validate(['imagen' => 'required|image|max:3072']);

        $dir = public_path("producto-images/{$tenantId}");
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($ia->imagen && $ia->imagen !== 'sinimagen.webp') {
            $anterior = public_path($ia->imagen);
            if (file_exists($anterior)) {
                unlink($anterior);
            }
        }

        $file = $request->file('imagen');
        $slug = Str::slug($producto->des) . '-' . $producto->cod;
        $name = $this->optimizarImagen($file->getRealPath(), $file->getMimeType(), $slug, $tenantId);

        $ia->update(['imagen' => $name]);

        Cache::forget('productos_bot_lista');
        Cache::forget('productos_bot_precios');

        return back()->with('success', "Imagen de {$producto->des} actualizada.");
    }

    public function updateDescripcion(Request $request, Producto $producto)
    {
        $request->validate(['descripcion' => 'nullable|string|max:500']);

        $ia = $this->getOrCreateIa($producto);
        $ia->update(['descripcion' => $request->input('descripcion', '')]);

        Cache::forget('productos_bot_lista');
        Cache::forget('productos_bot_precios');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', "Descripción de {$producto->des} actualizada.");
    }

    public function updateNotasIa(Request $request, Producto $producto)
    {
        $request->validate(['notas_ia' => 'nullable|string|max:500']);

        $ia = $this->getOrCreateIa($producto);
        $ia->update(['notas_ia' => $request->input('notas_ia', '')]);

        Cache::forget('productos_bot_lista');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', "Notas IA de {$producto->des} actualizadas.");
    }

    public function deleteImagen(Producto $producto)
    {
        $ia = $producto->iaProducto;
        if ($ia && $ia->imagen && $ia->imagen !== 'sinimagen.webp') {
            $path = public_path($ia->imagen);
            if (file_exists($path)) {
                unlink($path);
            }
            $ia->update(['imagen' => null]);
        }

        Cache::forget('productos_bot_lista');
        Cache::forget('productos_bot_precios');

        return back()->with('success', "Imagen eliminada.");
    }

    // -------------------------------------------------------------------

    private function getOrCreateIa(Producto $producto): IaProducto
    {
        return $producto->iaProducto ?? IaProducto::create([
            'tablaplu_id' => $producto->id,
            'precio'      => (float) $producto->PRE,
            'disponible'  => true,
        ]);
    }

    private function optimizarImagen(string $srcPath, string $mime, string $slug, int $tenantId): string
    {
        $maxWidth  = 800;
        $maxHeight = 800;
        $quality   = 80;
        $base      = "producto-images/{$tenantId}";

        if (!extension_loaded('gd')) {
            $name = "{$base}/{$slug}.jpg";
            copy($srcPath, public_path($name));
            return $name;
        }

        $src = match ($mime) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($srcPath),
            'image/png'               => imagecreatefrompng($srcPath),
            'image/gif'               => imagecreatefromgif($srcPath),
            'image/webp'              => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($srcPath) : imagecreatefromjpeg($srcPath),
            default                   => imagecreatefromjpeg($srcPath),
        };

        if (!$src) {
            $name = "{$base}/{$slug}.jpg";
            copy($srcPath, public_path($name));
            return $name;
        }

        [$origW, $origH] = [imagesx($src), imagesy($src)];
        $ratio = min($maxWidth / $origW, $maxHeight / $origH, 1.0);
        $newW  = (int) round($origW * $ratio);
        $newH  = (int) round($origH * $ratio);

        $dst   = imagecreatetruecolor($newW, $newH);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $name = "{$base}/{$slug}.jpg";
        imagejpeg($dst, public_path($name), $quality);

        return $name;
    }
}
