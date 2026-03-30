<?php

namespace App\Http\Controllers;

use App\Models\IaProducto;
use App\Models\Localidad;
use App\Models\Producto;
use App\Models\ProductoLocalidad;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $search     = $request->input('search');
        $filtroCat  = $request->input('catalogo');   // 'si' | 'no' | null
        $filtroDisp = $request->input('disponible'); // 'si' | 'no' | null

        $productos = Producto::with(['iaProducto', 'iaProducto.localidades.localidad'])
            ->when($search, fn($q) => $q->where('des', 'like', "%{$search}%"))
            ->when($filtroCat === 'si',  fn($q) => $q->whereHas('iaProducto'))
            ->when($filtroCat === 'no',  fn($q) => $q->whereDoesntHave('iaProducto'))
            ->when($filtroDisp === 'si', fn($q) => $q->whereHas('iaProducto', fn($q) => $q->where('disponible', true)))
            ->when($filtroDisp === 'no', fn($q) => $q->whereHas('iaProducto', fn($q) => $q->where('disponible', false)))
            ->orderBy('des')
            ->get();

        $localidades = Localidad::where('activo', true)->orderBy('nombre')->get();

        return view('admin.productos', compact('productos', 'localidades'));
    }

    /** Agrega el producto al catálogo del bot (crea ia_productos si no existe). */
    public function agregarCatalogo($cod)
    {
        $producto = Producto::where('cod', $cod)->firstOrFail();
        if (!$producto->iaProducto) {
            IaProducto::create([
                'cod'        => $producto->cod,
                'precio'     => (float) $producto->pre,
                'disponible' => true,
            ]);
            $this->limpiarCacheProductos();
        }
        return response()->json(['ok' => true]);
    }

    /** Quita el producto del catálogo del bot (elimina ia_productos). */
    public function quitarCatalogo($cod)
    {
        $producto = Producto::where('cod', $cod)->firstOrFail();
        if ($producto->iaProducto) {
            $img = $producto->iaProducto->imagen;
            if ($img && file_exists(public_path($img))) {
                unlink(public_path($img));
            }
            $producto->iaProducto->delete();
            $this->limpiarCacheProductos();
            // productos_bot_precios ya cubierto por limpiarCacheProductos
        }
        return response()->json(['ok' => true]);
    }

    /** Activa / desactiva la visibilidad del producto para el bot. */
    public function toggleDisponible($cod)
    {
        $producto = Producto::where('cod', $cod)->firstOrFail();
        $ia = $producto->iaProducto;
        if (!$ia) {
            return response()->json(['ok' => false], 422);
        }
        $ia->update(['disponible' => !$ia->disponible]);
        $this->limpiarCacheProductos();
        return response()->json(['ok' => true, 'disponible' => $ia->disponible]);
    }

    public function updatePrecio(Request $request, $cod)
    {
        $request->validate(['precio' => 'required|numeric|min:0']);

        $producto = Producto::findOrFail($cod);
        $ia = $producto->iaProducto;
        if (!$ia) {
            return response()->json(['ok' => false, 'error' => 'No en catálogo'], 422);
        }
        $ia->update(['precio' => $request->input('precio')]);
        $this->limpiarCacheProductos();
        // productos_bot_precios ya cubierto por limpiarCacheProductos

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', "Precio de {$producto->des} actualizado.");
    }

    public function uploadImagen(Request $request, $cod)
    {
        $producto = Producto::findOrFail($cod);
        $ia       = $this->getOrCreateIa($producto);
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

        $this->limpiarCacheProductos();
        // productos_bot_precios ya cubierto por limpiarCacheProductos

        return back()->with('success', "Imagen de {$producto->des} actualizada.");
    }

    public function updateDescripcion(Request $request, $cod)
    {
        $request->validate(['descripcion' => 'nullable|string|max:500']);

        $producto = Producto::findOrFail($cod);
        $ia = $this->getOrCreateIa($producto);
        $ia->update(['descripcion' => $request->input('descripcion', '')]);

        $this->limpiarCacheProductos();
        // productos_bot_precios ya cubierto por limpiarCacheProductos

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', "Descripción de {$producto->des} actualizada.");
    }

    public function updateNotasIa(Request $request, $cod)
    {
        $request->validate(['notas_ia' => 'nullable|string|max:500']);

        $producto = Producto::findOrFail($cod);
        $ia = $this->getOrCreateIa($producto);
        $ia->update(['notas_ia' => $request->input('notas_ia', '')]);

        $this->limpiarCacheProductos();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', "Notas IA de {$producto->des} actualizadas.");
    }

    public function deleteImagen($cod)
    {
        $producto = Producto::findOrFail($cod);
        $ia = $producto->iaProducto;
        if ($ia && $ia->imagen && $ia->imagen !== 'sinimagen.webp') {
            $path = public_path($ia->imagen);
            if (file_exists($path)) {
                unlink($path);
            }
            $ia->update(['imagen' => null]);
        }

        $this->limpiarCacheProductos();
        // productos_bot_precios ya cubierto por limpiarCacheProductos

        return back()->with('success', "Imagen eliminada.");
    }

    public function storeLocalidad(Request $request, $cod)
    {
        $data = $request->validate([
            'localidad_id' => 'required|integer',
            'precio'       => 'nullable|numeric|min:0',
            'dias_reparto' => 'nullable|array',
        ]);

        ProductoLocalidad::updateOrCreate(
            ['cod' => $cod, 'localidad_id' => $data['localidad_id']],
            [
                'precio'       => isset($data['precio']) && $data['precio'] !== '' ? $data['precio'] : null,
                'dias_reparto' => !empty($data['dias_reparto']) ? $data['dias_reparto'] : null,
            ]
        );

        $this->limpiarCacheProductos();
        return response()->json(['ok' => true]);
    }

    public function patchLocalidad(Request $request, $cod, $localidad_id)
    {
        $pl = ProductoLocalidad::where('cod', $cod)->where('localidad_id', $localidad_id)->firstOrFail();
        $data = $request->validate([
            'precio'       => 'nullable|numeric|min:0',
            'dias_reparto' => 'nullable|array',
        ]);
        $pl->update([
            'precio'       => isset($data['precio']) && $data['precio'] !== '' ? $data['precio'] : null,
            'dias_reparto' => array_key_exists('dias_reparto', $data) ? ($data['dias_reparto'] ?? null) : null,
        ]);
        $this->limpiarCacheProductos();
        return response()->json(['ok' => true]);
    }

    public function destroyLocalidad($cod, $localidad_id)
    {
        ProductoLocalidad::where('cod', $cod)->where('localidad_id', $localidad_id)->delete();
        $this->limpiarCacheProductos();
        return response()->json(['ok' => true]);
    }

    // -------------------------------------------------------------------

    private function limpiarCacheProductos(): void
    {
        $tenantId = app(TenantManager::class)->get()?->id ?? 0;
        Cache::forget('bot_mas_vendidos_' . $tenantId);
    }

    private function getOrCreateIa(Producto $producto): IaProducto
    {
        return $producto->iaProducto ?? IaProducto::create([
            'cod'        => $producto->cod,
            'precio'     => (float) $producto->PRE,
            'disponible' => true,
        ]);
    }

    private function optimizarImagen(string $srcPath, string $mime, string $slug, int $tenantId): string
    {
        $maxWidth  = 800;
        $maxHeight = 800;
        $quality   = 80;
        $base      = "producto-images/{$tenantId}";
        $ts        = time();

        if (!extension_loaded('gd')) {
            $name = "{$base}/{$slug}-{$ts}.jpg";
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
            $name = "{$base}/{$slug}-{$ts}.jpg";
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

        $name = "{$base}/{$slug}-{$ts}.jpg";
        imagejpeg($dst, public_path($name), $quality);

        return $name;
    }

    public function sugerirDescripcion($cod)
    {
        $producto = Producto::where('cod', $cod)->firstOrFail();
        $ia       = $producto->iaProducto;

        $nombre   = $producto->des;
        $grupo    = $producto->desgrupo ?? '';
        $tipo     = $producto->tipo ?? '';
        $desc     = $ia?->descripcion ?? '';
        $notas    = $ia?->notas_ia ?? '';

        $contexto = "Producto: {$nombre}";
        if ($grupo) $contexto .= "\nGrupo: {$grupo}";
        if ($tipo)  $contexto .= "\nTipo de venta: " . ($tipo === 'Unidad' ? 'por unidad' : 'por kilogramo');
        if ($desc)  $contexto .= "\nDescripción actual: {$desc}";
        if ($notas) $contexto .= "\nNotas internas: {$notas}";

        $prompt = <<<EOT
Sos un asistente de una carnicería/rotisería argentina. Escribí una descripción corta y atractiva para el cliente sobre este producto.
- Máximo 120 caracteres
- Lenguaje cercano, sin tecnicismos
- Destacá lo mejor del corte o producto (sabor, uso recomendado, textura)
- No repitas el nombre del producto al inicio
- No uses comillas

{$contexto}

Respondé solo con la descripción, sin explicaciones adicionales.
EOT;

        try {
            $response = Http::withToken(config('api.openai.key'))
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => 'gpt-4.1-mini',
                    'max_tokens'  => 80,
                    'temperature' => 0.7,
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ])->json();

            $sugerencia = trim($response['choices'][0]['message']['content'] ?? '');

            if (!$sugerencia) {
                return response()->json(['error' => 'La IA no devolvió una sugerencia.'], 500);
            }

            return response()->json(['sugerencia' => $sugerencia]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al conectar con la IA.'], 500);
        }
    }
}
