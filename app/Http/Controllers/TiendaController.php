<?php

namespace App\Http\Controllers;

use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\IaEmpresa;
use App\Models\Localidad;
use App\Models\Pedido;
use App\Models\Pedidosia;
use App\Models\Producto;
use App\Services\BotService;
use Illuminate\Http\Request;

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

    private function getEmpresaNombre(): string
    {
        try {
            return Empresa::first()?->nombre ?? '';
        } catch (\Throwable) {
            return '';
        }
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
        // Normaliza ambos formatos: bot usa cant+kilos, tienda usaba cantidad
        $items = array_map(function ($i) {
            $cant  = $i['cant']  ?? 0;
            $kilos = $i['kilos'] ?? 0;
            $cantidad = $cant > 0 ? $cant : ($kilos > 0 ? $kilos : ($i['cantidad'] ?? 0));
            return array_merge($i, ['cantidad' => $cantidad]);
        }, $carrito->items ?? []);
        $count = array_sum(array_column($items, 'cantidad'));
        $total = array_sum(array_map(fn($i) => $i['neto'] ?? 0, $items));
        return [
            'items' => $items,
            'count' => $count,
            'total' => $total,
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = ltrim($phone, '0');
        }

        if (strlen($phone) <= 10) {
            $phone = '54' . $phone;
        }

        if (str_starts_with($phone, '549') && strlen($phone) == 13) {
            // ok
        } elseif (str_starts_with($phone, '54') && strlen($phone) == 12) {
            $phone = '549' . substr($phone, 2);
        }

        return $phone;
    }

    // -------------------------------------------------------------------------
    // Catálogo
    // -------------------------------------------------------------------------

    public function index(string $slug)
    {
        $empresa       = $this->getEmpresa();
        $empresaNombre = $this->getEmpresaNombre() ?: ($empresa->nombre_ia ?? 'Tienda');
        $clienteId     = $this->getClienteId($slug);
        $cliente       = $clienteId ? Cliente::find($clienteId) : null;

        $productos = Producto::paraBot()->orderBy('tablaplu.desgrupo')->orderBy('tablaplu.des')->get();
        $grupos    = $productos->groupBy('desgrupo');

        return view('tienda.index', compact(
            'slug', 'empresa', 'empresaNombre', 'cliente', 'grupos'
        ));
    }

    // -------------------------------------------------------------------------
    // Autenticación
    // -------------------------------------------------------------------------

    public function showLogin(string $slug)
    {
        $empresa       = $this->getEmpresa();
        $empresaNombre = $this->getEmpresaNombre() ?: ($empresa->nombre_ia ?? 'Tienda');
        return view('tienda.login', compact('slug', 'empresa', 'empresaNombre'));
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

        $cliente = Cliente::firstOrCreate(
            ['phone' => $phone],
            ['name' => '', 'estado' => 'activo', 'modo' => 'bot']
        );

        $code      = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10)->timestamp;

        session([
            "tienda_{$slug}_code"     => $code,
            "tienda_{$slug}_phone"    => $phone,
            "tienda_{$slug}_code_exp" => $expiresAt,
        ]);

        try {
            app(BotService::class)->sendWhatsapp($phone, "Tu código de acceso para la tienda es: *{$code}*\n\nVálido por 10 minutos.");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("TiendaController::postLogin WA error: " . $e->getMessage());
        }

        return redirect()->route('tienda.verificar', ['slug' => $slug]);
    }

    public function showVerificar(string $slug)
    {
        $empresa       = $this->getEmpresa();
        $empresaNombre = $this->getEmpresaNombre() ?: ($empresa->nombre_ia ?? 'Tienda');
        $phone         = session("tienda_{$slug}_phone");

        if (!$phone) {
            return redirect()->route('tienda.login', ['slug' => $slug]);
        }

        return view('tienda.verificar', compact('slug', 'empresa', 'empresaNombre', 'phone'));
    }

    public function postVerificar(Request $request, string $slug)
    {
        $request->validate(['code' => 'required|string|size:4']);

        $sessionCode  = session("tienda_{$slug}_code");
        $sessionExp   = session("tienda_{$slug}_code_exp");
        $sessionPhone = session("tienda_{$slug}_phone");

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

        $cliente = Cliente::where('phone', $sessionPhone)->first();
        if (!$cliente) {
            return redirect()->route('tienda.login', ['slug' => $slug])
                ->withErrors(['code' => 'No se encontró el cliente. Intentá de nuevo.']);
        }

        session(["tienda_{$slug}_cliente_id" => $cliente->id]);
        session()->forget(["tienda_{$slug}_code", "tienda_{$slug}_code_exp"]);

        return redirect()->route('tienda.index', ['slug' => $slug]);
    }

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
    // Mis pedidos
    // -------------------------------------------------------------------------

    public function misPedidos(string $slug)
    {
        $clienteId = $this->getClienteId($slug);
        if (!$clienteId) {
            return redirect()->route('tienda.login', ['slug' => $slug]);
        }

        $empresa       = $this->getEmpresa();
        $empresaNombre = $this->getEmpresaNombre() ?: ($empresa->nombre_ia ?? 'Tienda');
        $cliente       = Cliente::find($clienteId);
        $carritoData   = $this->carritoJson($this->getCarrito($clienteId));

        $pedidos = Pedidosia::where('idcliente', $clienteId)
            ->orderByDesc('pedido_at')
            ->get();

        // Cargar los ítems de cada pedido
        $nros = $pedidos->pluck('nro')->unique()->filter()->values();
        $itemsPorNro = $nros->isNotEmpty()
            ? Pedido::whereIn('nro', $nros)->get()->groupBy('nro')
            : collect();

        return view('tienda.pedidos', compact(
            'slug', 'empresa', 'empresaNombre', 'cliente', 'carritoData', 'pedidos', 'itemsPorNro'
        ));
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

        // Validar: unidades deben ser enteras
        $esPorKilo = strtolower($producto->tipo ?? '') !== 'unidad';
        if (!$esPorKilo && floor($cantidad) != $cantidad) {
            return response()->json(['error' => 'La cantidad debe ser un número entero.'], 422);
        }
        // Validar: peso en múltiplos de 0.5
        if ($esPorKilo && (fmod($cantidad * 2, 1) != 0)) {
            return response()->json(['error' => 'El peso debe ser múltiplo de 0,5 kg.'], 422);
        }

        // Costo extra por zona del cliente
        $clienteObj = Cliente::find($clienteId);
        $costoExtra = 0.0;
        if ($clienteObj?->localidad_id) {
            $costoExtra = (float) (Localidad::find($clienteObj->localidad_id)?->costo_extra ?? 0);
        }
        $precio = (float) $producto->precio + $costoExtra;

        $carrito = Carrito::firstOrCreate(
            ['cliente_id' => $clienteId],
            ['items' => [], 'expires_at' => now()->addDays(7)]
        );

        $items = $carrito->items ?? [];
        $found = false;

        $cant  = $esPorKilo ? 0 : (int) $cantidad;
        $kilos = $esPorKilo ? $cantidad : 0;
        $base  = $esPorKilo ? $kilos : $cant;

        foreach ($items as &$item) {
            if ((string) $item['cod'] === (string) $cod) {
                $item['cant']   = $cant;
                $item['kilos']  = $kilos;
                $item['precio'] = $precio;
                $item['neto']   = round($precio * $base, 2);
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            $items[] = [
                'cod'    => $producto->cod,
                'des'    => $producto->des,
                'precio' => $precio,
                'cant'   => $cant,
                'kilos'  => $kilos,
                'neto'   => round($precio * $base, 2),
                'tipo'   => $producto->tipo,
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
            $items = array_values(array_filter($items, fn($i) => (string) $i['cod'] !== (string) $cod));
        } else {
            foreach ($items as &$item) {
                if ((string) $item['cod'] === (string) $cod) {
                    $esPeso        = strtolower($item['tipo'] ?? '') !== 'unidad';
                    $item['cant']  = $esPeso ? 0 : (int) $cantidad;
                    $item['kilos'] = $esPeso ? $cantidad : 0;
                    $base          = $esPeso ? $cantidad : (int) $cantidad;
                    $item['neto']  = round($item['precio'] * $base, 2);
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

        $empresa       = $this->getEmpresa();
        $empresaNombre = $this->getEmpresaNombre() ?: ($empresa->nombre_ia ?? 'Tienda');
        $cliente       = Cliente::find($clienteId);
        $carrito       = Carrito::where('cliente_id', $clienteId)->first();
        $carritoData   = $this->carritoJson($carrito);

        if (empty($carritoData['items'])) {
            return redirect()->route('tienda.index', ['slug' => $slug])
                ->with('info', 'Tu carrito está vacío.');
        }

        $pedidoMinimo = (float) ($empresa->pedido_minimo ?? 0);
        if ($pedidoMinimo > 0 && $carritoData['total'] < $pedidoMinimo) {
            return redirect()->route('tienda.index', ['slug' => $slug])
                ->with('info', "El pedido mínimo es $" . number_format($pedidoMinimo, 2, ',', '.') . ". Agregá más productos.");
        }

        $localidades = Localidad::where('activo', true)->orderBy('nombre')->get();
        $mediosPago  = $empresa->bot_medios_pago ?? array_keys(IaEmpresa::MEDIOS_PAGO);

        return view('tienda.checkout', compact(
            'slug', 'empresa', 'empresaNombre', 'cliente', 'carrito', 'carritoData',
            'localidades', 'mediosPago', 'pedidoMinimo'
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

        if ($tipoEntrega === 'envio') {
            $localidadId = $request->input('localidad_id');
            $calle       = $request->input('calle', '');
            $numero      = $request->input('numero', '');
            $datoExtra   = $request->input('dato_extra', '');

            $updateData = [];
            if ($localidadId) $updateData['localidad_id'] = $localidadId;
            if ($calle)       $updateData['calle']        = $calle;
            if ($numero)      $updateData['numero']       = $numero;
            if ($datoExtra)   $updateData['dato_extra']   = $datoExtra;
            if ($updateData)  $cliente->update($updateData);
        }

        $items    = $carrito->items ?? [];
        $subtotal = array_sum(array_map(fn($i) => ($i['precio'] ?? 0) * ($i['cantidad'] ?? 0), $items));
        $total    = $subtotal;

        // Validar pedido mínimo
        $pedidoMinimo = (float) ($empresa->pedido_minimo ?? 0);
        if ($pedidoMinimo > 0 && $subtotal < $pedidoMinimo) {
            return redirect()->route('tienda.index', ['slug' => $slug])
                ->with('info', "El pedido mínimo es $" . number_format($pedidoMinimo, 2, ',', '.') . ".");
        }

        $nro    = ((int) Pedido::max('nro') ?? 0) + 1;
        $suc    = $empresa->suc ?? '001';
        $pv     = $empresa->pv  ?? '0001';
        $fecha  = now()->format('Y-m-d');
        $nomcli = $cliente->name ?: $cliente->phone;

        $kilosTot = array_sum(array_map(fn($i) => ($i['kilos'] ?? 0), $items));

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

        foreach ($items as $item) {
            Pedido::create([
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
        }

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

        $carrito->items = [];
        $carrito->save();

        // Notificación WhatsApp al cliente
        try {
            $resumen = "✅ *Pedido #{$nro} recibido*\n\n";
            foreach ($items as $item) {
                $tipo = strtolower($item['tipo'] ?? '');
                $resumen .= $tipo === 'unidad'
                    ? "• {$item['des']} x{$item['cantidad']}\n"
                    : "• {$item['des']} {$item['cantidad']}kg\n";
            }
            $resumen .= "\n*Total: $" . number_format($total, 2, ',', '.') . "*";
            $resumen .= "\n" . ($tipoEntrega === 'retiro' ? 'Retiro en local' : 'Envío');
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

        $empresaNombre = $this->getEmpresaNombre() ?: ($empresa->nombre_ia ?? 'Tienda');
        $carritoData   = ['items' => [], 'count' => 0, 'total' => 0];

        return view('tienda.confirmado', compact(
            'slug', 'empresa', 'empresaNombre', 'carritoData',
            'nro', 'items', 'total', 'tipoEntrega', 'medioPago'
        ));
    }
}
