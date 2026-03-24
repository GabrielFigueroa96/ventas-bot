<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\IaEmpresa;
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

        $pedidos = Pedidosia::where('idcliente', $clienteId)
            ->orderByDesc('pedido_at')
            ->get();

        $nros = $pedidos->pluck('nro')->unique()->filter()->values();
        $itemsPorNro = $nros->isNotEmpty()
            ? Pedido::whereIn('nro', $nros)->get()->groupBy('nro')
            : collect();

        return view('tienda.pedidos', compact(
            'slug', 'empresa', 'empresaNombre', 'cliente', 'pedidos', 'itemsPorNro'
        ));
    }
}
