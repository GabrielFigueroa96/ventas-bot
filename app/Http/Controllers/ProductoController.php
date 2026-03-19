<?php

namespace App\Http\Controllers;

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
            ->when($search, fn($q) =>
                $q->where('des', 'like', "%{$search}%")
            )
            ->orderBy('des')
            ->get();

        return view('admin.productos', compact('productos'));
    }

    public function uploadImagen(Request $request, int $id)
    {
        $producto  = Producto::findOrFail($id);
        $tenantId  = app(TenantManager::class)->get()->id;
        $request->validate(['imagen' => 'required|image|max:3072']);

        $dir = public_path("producto-images/{$tenantId}");
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
        $slug = Str::slug($producto->des) . '-' . $producto->cod;

        $name = $this->optimizarImagen($file->getRealPath(), $file->getMimeType(), $slug, $tenantId);

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

    /**
     * Redimensiona y comprime la imagen a máx 800px, guardando como WebP (si GD lo soporta) o JPEG.
     * Retorna la ruta relativa desde public/.
     */
    private function optimizarImagen(string $srcPath, string $mime, string $slug, int $tenantId): string
    {
        $maxWidth  = 800;
        $maxHeight = 800;
        $quality   = 80;
        $base      = "producto-images/{$tenantId}";

        // Si GD no está disponible, guardar tal cual como JPEG
        if (!extension_loaded('gd')) {
            $name = "{$base}/{$slug}.jpg";
            copy($srcPath, public_path($name));
            return $name;
        }

        // Crear imagen GD según MIME
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

        // Calcular nuevas dimensiones manteniendo proporción
        $ratio  = min($maxWidth / $origW, $maxHeight / $origH, 1.0);
        $newW   = (int) round($origW * $ratio);
        $newH   = (int) round($origH * $ratio);

        $dst = imagecreatetruecolor($newW, $newH);

        // Fondo blanco (para PNGs con transparencia al convertir a JPEG)
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        // Guardar siempre como JPEG calidad 80 (compatible con WhatsApp, liviano)
        $name = "{$base}/{$slug}.jpg";
        imagejpeg($dst, public_path($name), $quality);

        return $name;
    }
}
