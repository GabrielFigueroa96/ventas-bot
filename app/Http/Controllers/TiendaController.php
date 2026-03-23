<?php

namespace App\Http\Controllers;

use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\IaEmpresa;
use App\Models\Localidad;
use App\Models\Pedido;
use App\Models\Pedidosia;
use App\Models\Producto;
use App\Services\BotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class TiendaController extends Controller
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getClienteId(string $slug): ?int
    {
        $id = session("tienda_{$slug}_cliente_id");
        return $id ? (int) $id : null;
    }

    private function getEmpresa(): IaEmpresa
    {
        return IaEmpresa::first() ?? new IaEmpresa();
    }

    private function getCarrito(?int $clienteId): ?Carrito
    {
        if (!$clienteId) return null;
        return Carrito::where('cliente_id', $clienteId)->first();
    }

    private function carritoJson(?Carrito $carrito): array
    {
        if (!$carrito) {
            return ['items' => [], 'count' => 0, 'total' => 0];
        }
        $items = $carrito->items ?? [];
        $count = array_sum(array_column($items, 'cantidad'));
        $total = array_sum(array_map(fn($i) => ($i['precio'] ?? 0) * ($i['cantidad'] ?? 0), $items));
        return [
            'items' => $items,
            'count' => $count,
            'total' => $total,
        ];
    }

    private function normalizePhone(string $phone): string
    {
        // Quitar todo lo que no sea dígito
        $phone = preg_replace('/\D/', '', $phone);

        // Si empieza con 0, quitarlo
        if (str_starts_with($phone, '0')) {
            $phone = ltrim($phone, '0');
        }

        // Si no tiene código de país (menos de 11 dígitos) agregar 54
        if (strlen($phone) <= 10) {
            $phone = '54' . $phone;
        }

        // WhatsApp Argentina: 549 + 10 dígitos (quitar el 15 si lo tiene)
        // Si tiene 5491X con X = 15..., normalizar
        if (str_starts_with($phone, '549') && strlen($phone) == 13) {
            // tiene 549 + 10 dígitos => ok
        } elseif (str_starts_with($phone, '54') && strlen($phone) == 12) {
            // 54 + 10 dígitos, agregar 9 después del 54
            $phone = '549' . substr($phone, 2);
        }

        return $phone;
    }

    // -------------------------------------------------------------------------
    // Catálogo
    // -------------------------------------------------------------------------

    public function index(string $slug)
    {
        $empresa    = $this->getEmpresa();
        $clienteId  = $this->getClienteId($slug);
        $cliente    = $clienteId ? Cliente::find($clienteId) : null;
        $carrito    = $this->getCarrito($clienteId);

        $productos  = Producto::paraBot()->orderBy('tablaplu.desgrupo')->orderBy('tablaplu.des')->get();
        $grupos     = $productos->groupBy('desgrupo');
        $carritoData = $this->carritoJson($carrito);

        return view('tienda.index', compact('slug', 'empresa', 'cliente', 'grupos', 'carritoData', 'carrito'));
    }

    // -------------------------------------------------------------------------
    // Autenticación: Login
    // -------------------------------------------------------------------------

    public function showLogin(string $slug)
    {
        $empresa = $this->getEmpresa();
        return view('tienda.login', compact('slug', 'empresa'));
    }

    public function postLogin(Request $request, string $slug)
    {
        $request->validate([
            'phone' => 'required|string|min:6',
        ], [
            'phone.required' => 'El teléfono es obligatorio.',
            'phone.min'      => 'El teléfono debe tener al menos 6 caracteres.',
        ]);

        $phoneRaw = $request->input('phone');
        $phone    = $this->normalizePhone($phoneRaw);

        // Buscar o crear cliente
        $cliente = Cliente::firstOrCreate(
            ['phone' => $phone],
            ['name' => '', 'estado' => 'activo', 'modo' => 'bot']
        );

        // Generar código de 4 dígitos
        $code      = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10)->timestamp;

        session([
            "tienda_{$slug}_code"       => $code,
            "tienda_{$slug}_phone"      => $phone,
            "tienda_{$slug}_code_exp"   => $expiresAt,
        ]);

        // Enviar código por WhatsApp
        try {
            app(BotService::class)->sendWhatsapp($phone, "Tu código de acceso para la tienda es: *{$code}*\n\nVálido por 10 minutos.");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("TiendaController::postLogin WA error: " . $e->getMessage());
        }

        return redirect()->route('tienda.verificar', ['slug' => $slug]);
    }

    // -------------------------------------------------------------------------
    // Autenticación: Verificar código
    // -------------------------------------------------------------------------

    public function showVerificar(string $slug)
    {
        $empresa = $this->getEmpresa();
        $phone   = session("tienda_{$slug}_phone");

        if (!$phone) {
            return redirect()->route('tienda.login', ['slug' => $slug]);
        }

        return view('tienda.verificar', compact('slug', 'empresa', 'phone'));
    }

    public function postVerificar(Request $request, string $slug)
    {
        $request->validate(['code' => 'required|string|size:4']);

        $sessionCode   = session("tienda_{$slug}_code");
        $sessionExp    = session("tienda_{$slug}_code_exp");
        $sessionPhone  = session("tienda_{$slug}_phone");

        if (!$sessionCode || !$sessionPhone) {
            return redirect()->route('tienda.login', ['slug' => $slug])
                ->withErrors(['code' => 'La sesión expiró. Solicitá un nuevo código.']);
        }

        if ($sessionExp && now()->timestamp > $sessionExp) {
            return back()->withErrors(['code' => 'El código expiró. Solicitá uno nuevo.']);
        }

        if ($request->input('code') !== $sessionCode) {
            return back()->withErrors(['code' => 'El código ingresado no es correcto.']);
        }

        // Código correcto: guardar cliente en session
        $cliente = Cliente::where('phone', $sessionPhone)->first();
        if (!$cliente) {
            return redirect()->route('tienda.login', ['slug' => $slug])
                ->withErrors(['code' => 'No se encontró el cliente. Intentá de nuevo.']);
        }

        session([
            "tienda_{$slug}_cliente_id" => $cliente->id,
        ]);

        // Limpiar datos de verificación
        session()->forget(["tienda_{$slug}_code", "tienda_{$slug}_code_exp"]);

        return redirect()->route('tienda.index', ['slug' => $slug]);
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    public function logout(string $slug)
    {
        session()->forget([
            "tienda_{$slug}_cliente_id",
            "tienda_{$slug}_code",
            "tienda_{$slug}_code_exp",
            "tienda_{$slug}_phone",
        ]);

        return redirect()->route('tienda.index', ['slug' => $slug]);
    }

    // -------------------------------------------------------------------------
    // Carrito (AJAX)
    // -------------------------------------------------------------------------

    public function agregarItem(Request $request, string $slug)
    {
        $clienteId = $this->getClienteId($slug);

        if (!$clienteId) {
            return response()->json(['redirect' => route('tienda.login', ['slug' => $slug])], 401);
        }

        $request->validate([
            'cod'      => 'required',
            'cantidad' => 'required|numeric|min:0.01',
        ]);

        $cod      = $request->input('cod');
        $cantidad = (float) $request->input('cantidad');

        $producto = Producto::paraBot()->where('tablaplu.cod', $cod)->first();

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        $carrito = Carrito::firstOrCreate(
            ['cliente_id' => $clienteId],
            ['items' => [], 'expires_at' => now()->addDays(7)]
        );

        $items = $carrito->items ?? [];

        // Buscar si ya está el producto
        $found = false;
        foreach ($items as &$item) {
            if ((string) $item['cod'] === (string) $cod) {
                $item['cantidad'] = $cantidad;
                $item['neto']     = round($producto->precio * $cantidad, 2);
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            $items[] = [
                'cod'      => $producto->cod,
                'des'      => $producto->des,
                'precio'   => (float) $producto->precio,
                'cantidad' => $cantidad,
                'kilos'    => strtolower($producto->tipo ?? '') !== 'unidad' ? $cantidad : 0,
                'neto'     => round($producto->precio * $cantidad, 2),
                'tipo'     => $producto->tipo,
            ];
        }

        $carrito->items      = $items;
        $carrito->expires_at = now()->addDays(7);
        $carrito->save();

        return response()->json($this->carritoJson($carrito));
    }

    public function quitarItem(Request $request, string $slug)
    {
        $clienteId = $this->getClienteId($slug);

        if (!$clienteId) {
            return response()->json(['error' => 'No autenticado.'], 401);
        }

        $cod     = $request->input('cod');
        $carrito = Carrito::where('cliente_id', $clienteId)->first();

        if (!$carrito) {
            return response()->json($this->carritoJson(null));
        }

        $items = array_values(array_filter(
            $carrito->items ?? [],
            fn($item) => (string) $item['cod'] !== (string) $cod
        ));

        $carrito->items = $items;
        $carrito->save();

        return response()->json($this->carritoJson($carrito));
    }

    public function actualizarCantidad(Request $request, string $slug)
    {
        $clienteId = $this->getClienteId($slug);

        if (!$clienteId) {
            return response()->json(['error' => 'No autenticado.'], 401);
        }

        $request->validate([
            'cod'      => 'required',
            'cantidad' => 'required|numeric|min:0',
        ]);

        $cod      = $request->input('cod');
        $cantidad = (float) $request->input('cantidad');

        $carrito = Carrito::where('cliente_id', $clienteId)->first();

        if (!$carrito) {
            return response()->json($this->carritoJson(null));
        }

        $items = $carrito->items ?? [];

        if ($cantidad <= 0) {
            // Quitar el item
            $items = array_values(array_filter($items, fn($i) => (string) $i['cod'] !== (string) $cod));
        } else {
            foreach ($items as &$item) {
                if ((string) $item['cod'] === (string) $cod) {
                    $item['cantidad'] = $cantidad;
                    $item['neto']     = round($item['precio'] * $cantidad, 2);
                    break;
                }
            }
            unset($item);
        }

        $carrito->items = $items;
        $carrito->save();

        return response()->json($this->carritoJson($carrito));
    }

    // -------------------------------------------------------------------------
    // Checkout
    // -------------------------------------------------------------------------

    public function checkout(string $slug)
    {
        $clienteId = $this->getClienteId($slug);

        if (!$clienteId) {
            return redirect()->route('tienda.login', ['slug' => $slug]);
        }

        $empresa    = $this->getEmpresa();
        $cliente    = Cliente::find($clienteId);
        $carrito    = Carrito::where('cliente_id', $clienteId)->first();
        $carritoData = $this->carritoJson($carrito);

        if (empty($carritoData['items'])) {
            return redirect()->route('tienda.index', ['slug' => $slug])
                ->with('info', 'Tu carrito está vacío.');
        }

        $localidades = Localidad::where('activo', true)->orderBy('nombre')->get();
        $mediosPago  = $empresa->bot_medios_pago ?? array_keys(IaEmpresa::MEDIOS_PAGO);

        return view('tienda.checkout', compact(
            'slug', 'empresa', 'cliente', 'carrito', 'carritoData', 'localidades', 'mediosPago'
        ));
    }

    public function confirmar(Request $request, string $slug)
    {
        $clienteId = $this->getClienteId($slug);

        if (!$clienteId) {
            return redirect()->route('tienda.login', ['slug' => $slug]);
        }

        $empresa = $this->getEmpresa();
        $cliente = Cliente::findOrFail($clienteId);
        $carrito = Carrito::where('cliente_id', $clienteId)->first();

        if (!$carrito || empty($carrito->items)) {
            return redirect()->route('tienda.index', ['slug' => $slug])
                ->with('info', 'Tu carrito está vacío.');
        }

        $request->validate([
            'tipo_entrega' => 'required|in:envio,retiro',
            'medio_pago'   => 'required|string',
        ]);

        $tipoEntrega = $request->input('tipo_entrega');
        $medioPago   = $request->input('medio_pago');
        $obs         = $request->input('obs', '');
        $fecFin      = $request->input('fecha_deseada', null);
        $localidadId = null;

        // Dirección si es envío
        if ($tipoEntrega === 'envio') {
            $localidadId = $request->input('localidad_id');
            $calle       = $request->input('calle', '');
            $numero      = $request->input('numero', '');
            $datoExtra   = $request->input('dato_extra', '');

            // Actualizar datos del cliente
            $updateData = [];
            if ($localidadId) $updateData['localidad_id'] = $localidadId;
            if ($calle)       $updateData['calle']        = $calle;
            if ($numero)      $updateData['numero']       = $numero;
            if ($datoExtra)   $updateData['dato_extra']   = $datoExtra;
            if ($updateData)  $cliente->update($updateData);
        }

        // Número de pedido
        $nro = ((int) Pedido::max('nro') ?? 0) + 1;

        $suc = $empresa->suc ?? '001';
        $pv  = $empresa->pv  ?? '0001';

        $fecha    = now()->format('Y-m-d');
        $nomcli   = $cliente->name ?: $cliente->phone;

        $items    = $carrito->items ?? [];
        $total    = array_sum(array_map(fn($i) => ($i['precio'] ?? 0) * ($i['cantidad'] ?? 0), $items));
        $kilosTot = array_sum(array_map(fn($i) => ($i['kilos'] ?? 0), $items));

        // Armar descripción del pedido
        $lineas = [];
        foreach ($items as $item) {
            $tipo = strtolower($item['tipo'] ?? '');
            if ($tipo === 'unidad') {
                $lineas[] = "{$item['des']} x{$item['cantidad']} = $" . number_format($item['neto'], 2, ',', '.');
            } else {
                $lineas[] = "{$item['des']} {$item['cantidad']}kg = $" . number_format($item['neto'], 2, ',', '.');
            }
        }
        $descripcion = implode("\n", $lineas);
        $obsCompleta = trim(
            ($tipoEntrega === 'retiro' ? 'RETIRO EN LOCAL' : 'ENVIO') .
            " | Pago: " . (IaEmpresa::MEDIOS_PAGO[$medioPago] ?? $medioPago) .
            ($obs ? " | " . $obs : '')
        );

        // Crear los renglones del pedido (uno por item)
        $createdPedidos = [];
        foreach ($items as $item) {
            $pedido = Pedido::create([
                'fecha'     => $fecha,
                'nro'       => $nro,
                'nomcli'    => $nomcli,
                'cant'      => $item['cantidad'],
                'descrip'   => $item['des'],
                'kilos'     => $item['kilos'] ?? 0,
                'precio'    => $item['precio'],
                'neto'      => $item['neto'],
                'codigo'    => $item['cod'],
                'codcli'    => $cliente->id,
                'estado'    => Pedido::ESTADO_PENDIENTE,
                'fecfin'    => $fecFin,
                'obs'       => $obsCompleta,
                'pedido_at' => now(),
                'suc'       => $suc,
                'pv'        => $pv,
                'venta'     => null,
            ]);
            $createdPedidos[] = $pedido;
        }

        // También crear registro en pedidosia
        try {
            $localNombre = '';
            if ($tipoEntrega === 'envio' && !empty($localidadId)) {
                $localNombre = Localidad::find($localidadId)?->nombre ?? '';
            }

            Pedidosia::create([
                'nro'          => $nro,
                'nomcli'       => $nomcli,
                'idcliente'    => $cliente->id,
                'codcli'       => $cliente->id,
                'fecha'        => $fecha,
                'total'        => $total,
                'estado'       => Pedidosia::ESTADO_PENDIENTE,
                'tipo_entrega' => $tipoEntrega,
                'forma_pago'   => $medioPago,
                'obs'          => $obs,
                'calle'        => $tipoEntrega === 'envio' ? ($request->input('calle') ?? '') : '',
                'numero'       => $tipoEntrega === 'envio' ? ($request->input('numero') ?? '') : '',
                'localidad'    => $localNombre,
                'dato_extra'   => $tipoEntrega === 'envio' ? ($request->input('dato_extra') ?? '') : '',
                'pedido_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("TiendaController::confirmar pedidosia error: " . $e->getMessage());
        }

        // Vaciar el carrito
        $carrito->items = [];
        $carrito->save();

        // Notificación WhatsApp al cliente
        try {
            $resumen = "✅ *Pedido #{$nro} recibido*\n\n";
            foreach ($items as $item) {
                $tipo = strtolower($item['tipo'] ?? '');
                if ($tipo === 'unidad') {
                    $resumen .= "• {$item['des']} x{$item['cantidad']}\n";
                } else {
                    $resumen .= "• {$item['des']} {$item['cantidad']}kg\n";
                }
            }
            $resumen .= "\n*Total: $" . number_format($total, 2, ',', '.') . "*";
            $resumen .= "\n" . ($tipoEntrega === 'retiro' ? 'Retiro en local' : 'Envío a domicilio');
            $resumen .= "\nPago: " . (IaEmpresa::MEDIOS_PAGO[$medioPago] ?? $medioPago);
            app(BotService::class)->sendWhatsapp($cliente->phone, $resumen);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("TiendaController::confirmar WA cliente error: " . $e->getMessage());
        }

        // Notificación WhatsApp al negocio
        try {
            if ($empresa->telefono_pedidos) {
                $notif = "🛒 *Nuevo pedido web #{$nro}*\n";
                $notif .= "Cliente: {$nomcli} ({$cliente->phone})\n";
                $notif .= $descripcion . "\n";
                $notif .= "*Total: $" . number_format($total, 2, ',', '.') . "*\n";
                $notif .= ($tipoEntrega === 'retiro' ? 'Retiro en local' : 'Envío');
                $notif .= " | " . (IaEmpresa::MEDIOS_PAGO[$medioPago] ?? $medioPago);
                app(BotService::class)->sendWhatsapp($empresa->telefono_pedidos, $notif);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("TiendaController::confirmar WA negocio error: " . $e->getMessage());
        }

        return view('tienda.confirmado', compact('slug', 'empresa', 'nro', 'items', 'total', 'tipoEntrega', 'medioPago'));
    }
}
