<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Factventas;
use App\Models\IaEmpresa;
use App\Models\Message;
use App\Models\Pedido;
use App\Models\Pedidosia;
use App\Models\Seguimiento;
use App\Services\BotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        $empresa = Empresa::first();

        $seguimientos = Seguimiento::with('cliente')
            ->orderByDesc('enviado_at')
            ->take(10)
            ->get();

        $stats = [
            'clientes'     => Cliente::count(),
            'pedidos_hoy'  => Pedido::whereDate('fecha', today())->distinct('nro')->count('nro'),
            'pedidos_pend' => Pedido::where('estado', Pedido::ESTADO_PENDIENTE)->distinct('nro')->count('nro'),
            'mensajes_hoy' => Message::whereDate('created_at', today())->count(),
        ];

        $pedidos_recientes = Pedido::orderByDesc('reg')
            ->get()
            ->groupBy('nro')
            ->take(10);

        // Pedidos por día — últimos 14 días
        $porDiaRaw = Pedido::selectRaw('fecha, COUNT(DISTINCT nro) as total')
            ->where('fecha', '>=', now()->subDays(13)->format('Y-m-d'))
            ->groupBy('fecha')
            ->pluck('total', 'fecha');

        $chartDias   = [];
        $chartPedidos = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $chartDias[]    = $d->format('d/m');
            $chartPedidos[] = (int) ($porDiaRaw[$d->format('Y-m-d')] ?? 0);
        }

        // Donut: pendientes vs finalizados
        $chartEstados = [
            Pedido::where('estado', Pedido::ESTADO_PENDIENTE)->distinct('nro')->count('nro'),
            Pedido::where('estado', Pedido::ESTADO_FINALIZADO)->distinct('nro')->count('nro'),
        ];

        // Top 6 artículos más pedidos (por kilos totales)
        $topArticulos = Pedido::selectRaw('descrip, SUM(kilos) as total_kilos')
            ->groupBy('descrip')
            ->orderByDesc('total_kilos')
            ->take(6)
            ->get();

        $chartArticulosLabels = $topArticulos->pluck('descrip')->toArray();
        $chartArticulosData   = $topArticulos->map(fn($r) => round($r->total_kilos, 1))->toArray();

        // Nuevos clientes por semana — últimas 8 semanas
        $clientesPorSemana = Cliente::selectRaw('YEARWEEK(created_at, 1) as semana, COUNT(*) as total')
            ->where('created_at', '>=', now()->subWeeks(7)->startOfWeek())
            ->groupBy('semana')
            ->orderBy('semana')
            ->pluck('total', 'semana');

        $chartSemanas  = [];
        $chartClientes = [];
        for ($i = 7; $i >= 0; $i--) {
            $d = now()->subWeeks($i)->startOfWeek();
            $key = $d->format('oW'); // ISO year+week
            $chartSemanas[]  = $d->format('d/m');
            $chartClientes[] = (int) ($clientesPorSemana[$key] ?? 0);
        }

        return view('admin.dashboard', compact(
            'stats', 'pedidos_recientes',
            'empresa',
            'chartDias', 'chartPedidos',
            'chartEstados',
            'chartArticulosLabels', 'chartArticulosData',
            'chartSemanas', 'chartClientes',
            'seguimientos',
        ));
    }

    public function clientes(Request $request)
    {
        $search = $request->input('search');

        $clientes = Cliente::withCount('messages')
            ->with('cuenta')
            ->when($search, fn($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
            )
            ->latest()
            ->paginate(20);

        return view('admin.clientes', compact('clientes'));
    }

    public function cliente(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->load('cuenta');

        $mensajes = Message::where('cliente_id', $cliente->id)
            ->oldest()
            ->get();

        $codcli     = $cliente->cuenta ? $cliente->cuenta->cod : $cliente->id;
        $pedidosRaw = Pedido::where('codcli', $codcli)
            ->orderByDesc('reg')
            ->get();

        $pedidos       = $pedidosRaw->groupBy('nro');
        $factventas    = $this->loadFactventas($pedidosRaw);
        $pedidosia     = $this->loadPedidosia($pedidosRaw);
        $lastPedidoReg = (int) ($pedidosRaw->max('reg') ?? 0);

        $totalPedidos    = $pedidos->count();
        $ultimoPedidoAt  = $pedidosRaw->max('pedido_at');

        return view('admin.cliente', compact('cliente', 'mensajes', 'pedidos', 'factventas', 'pedidosia', 'lastPedidoReg', 'totalPedidos', 'ultimoPedidoAt'));
    }

    public function pedidos(Request $request)
    {
        $estado = $request->input('estado');
        $fecha  = $request->input('fecha');
        $search = $request->input('search');

        $query = Pedido::orderByDesc('reg');

        if ($estado !== null && $estado !== '') {
            $query->where('estado', $estado);
        }

        if ($fecha) {
            $query->whereDate('fecha', $fecha);
        }

        if ($search) {
            $query->where('nomcli', 'like', "%{$search}%");
        }

        $pedidosRaw = $query->get();
        $pedidos    = $pedidosRaw->groupBy('nro');
        $factventas = $this->loadFactventas($pedidosRaw);
        $pedidosia  = $this->loadPedidosia($pedidosRaw);

        return view('admin.pedidos', compact('pedidos', 'factventas', 'pedidosia'));
    }

    public function usoIa()
    {
        // Tokens por modelo este mes
        $filasMes = DB::table('ia_token_usos')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('modelo, SUM(prompt_tokens) as input, SUM(completion_tokens) as output, SUM(total_tokens) as total')
            ->groupBy('modelo')
            ->get();

        $inputTokens  = 0;
        $outputTokens = 0;
        $totalTokens  = 0;
        foreach ($filasMes as $fila) {
            $inputTokens  += (int) $fila->input;
            $outputTokens += (int) $fila->output;
            $totalTokens  += (int) $fila->total;
        }

        // Tokens por día — últimos 14 días
        $tokensporDiaRaw = DB::table('ia_token_usos')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) as dia, SUM(total_tokens) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $chartDias   = [];
        $chartTokens = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $chartDias[]   = now()->subDays($i)->format('d/m');
            $chartTokens[] = (int) ($tokensporDiaRaw[$d] ?? 0);
        }

        // Conversaciones WhatsApp este mes
        try {
            $waConvMes = DB::table('ia_messages')
                ->where('direction', 'outgoing')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->selectRaw('COUNT(DISTINCT CONCAT(cliente_id, DATE(created_at))) as conversaciones')
                ->value('conversaciones') ?? 0;
        } catch (\Throwable $e) {
            $waConvMes = 0;
        }

        // Mensajes WA por día — últimos 14 días
        try {
            $waPorDiaRaw = DB::table('ia_messages')
                ->where('direction', 'outgoing')
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->selectRaw('DATE(created_at) as dia, COUNT(*) as total')
                ->groupBy('dia')
                ->pluck('total', 'dia');
        } catch (\Throwable $e) {
            $waPorDiaRaw = collect();
        }

        $chartWaDias = [];
        $chartWa     = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $chartWaDias[] = now()->subDays($i)->format('d/m');
            $chartWa[]     = (int) ($waPorDiaRaw[$d] ?? 0);
        }

        return view('admin.uso', compact(
            'filasMes', 'inputTokens', 'outputTokens', 'totalTokens',
            'waConvMes',
            'chartDias', 'chartTokens',
            'chartWaDias', 'chartWa',
        ));
    }

    public function configuracion()
    {
        $config = IaEmpresa::firstOrNew([]);
        return view('admin.configuracion', compact('config'));
    }

    public function guardarConfiguracion(Request $request)
    {
        $config = IaEmpresa::firstOrNew([]);

        $data = [
            'nombre_ia'          => $request->input('nombre_ia'),
            'telefono_pedidos'   => $request->input('telefono_pedidos'),
            'bot_info'           => $request->input('bot_info'),
            'bot_instrucciones'  => $request->input('bot_instrucciones'),
            'bot_permite_retiro'     => $request->boolean('bot_permite_retiro'),
            'bot_permite_envio'      => $request->boolean('bot_permite_envio'),
            'bot_medios_pago'        => $request->has('bot_medios_pago') ? $request->input('bot_medios_pago') : null,
            'bot_puede_pedir'        => $request->boolean('bot_puede_pedir'),
            'bot_puede_sugerir'      => $request->boolean('bot_puede_sugerir'),
            'bot_puede_mas_vendidos' => $request->boolean('bot_puede_mas_vendidos'),
            'bot_atiende_nuevos'     => $request->input('bot_atiende_nuevos', 'bot'),
            'suc'                    => $request->input('suc'),
            'pv'                     => $request->input('pv'),
        ];

        if ($request->hasFile('imagen_bienvenida')) {
            // Borra la imagen anterior si existe
            if ($config->imagen_bienvenida) {
                $anterior = public_path($config->imagen_bienvenida);
                if (file_exists($anterior)) unlink($anterior);
            }
            $tenantId = app(\App\Services\TenantManager::class)->get()->id;
            $dir = public_path("ia-imagenes/{$tenantId}");
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $file = $request->file('imagen_bienvenida');
            $name = "ia-imagenes/{$tenantId}/bienvenida.jpg";
            $file->move($dir, 'bienvenida.jpg');
            $data['imagen_bienvenida'] = $name;
        }

        if ($request->boolean('eliminar_imagen') && $config->imagen_bienvenida) {
            $anterior = public_path($config->imagen_bienvenida);
            if (file_exists($anterior)) unlink($anterior);
            $data['imagen_bienvenida'] = null;
        }

        $config->fill($data)->save();

        $tenantId = app(\App\Services\TenantManager::class)->get()?->id ?? 0;
        \Illuminate\Support\Facades\Cache::forget('bot_empresa_config_' . $tenantId);
        \Illuminate\Support\Facades\Cache::forget('productos_bot_lista_' . $tenantId);

        // Actualizar slug en ia_tenants (conexión mysql) y en ia_empresa
        $slugTienda = $request->input('slug_tienda');
        if ($tenantId && $slugTienda !== null) {
            $slugValue = $slugTienda !== '' ? preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($slugTienda))) : null;
            DB::connection('mysql')->table('ia_tenants')
                ->where('id', $tenantId)
                ->update(['slug' => $slugValue]);
            // Limpiar la caché del slug anterior y del nuevo
            \Illuminate\Support\Facades\Cache::forget('tenant_slug_' . ($slugValue ?? ''));
            // También guardar en ia_empresa si tiene la columna
            try {
                $config->fill(['slug' => $slugValue])->save();
            } catch (\Throwable $e) {
                // Si no tiene la columna aún, ignorar
            }
        }

        return back()->with('ok', 'Configuración guardada.');
    }

    public function avanzarEstadoPedido(int $id, Request $request)
    {
        $sia = Pedidosia::findOrFail($id);

        $nextEstado = $sia->estado + 1;
        if ($nextEstado > Pedidosia::ESTADO_ENTREGADO) {
            return response()->json(['error' => 'Ya está en el estado final.'], 422);
        }

        $sia->estado = $nextEstado;
        $sia->save();

        // Notificar al cliente por WhatsApp si hay un mensaje definido
        $plantilla = Pedidosia::MENSAJES_ESTADO[$nextEstado] ?? null;
        if ($plantilla && $sia->cliente?->phone) {
            $mensaje = str_replace('{nro}', $sia->nro, $plantilla);
            try {
                app(BotService::class)->sendWhatsapp($sia->cliente->phone, $mensaje);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("avanzarEstadoPedido WA error: " . $e->getMessage());
            }
        }

        $info = Pedidosia::ESTADOS[$nextEstado];
        return response()->json(['estado' => $nextEstado, 'label' => $info['label'], 'css' => $info['css']]);
    }

    private function loadPedidosia($pedidos): \Illuminate\Support\Collection
    {
        $nros = $pedidos->pluck('nro')->unique()->filter()->values();

        if ($nros->isEmpty()) {
            return collect();
        }

        return Pedidosia::whereIn('nro', $nros)->get()->keyBy('nro');
    }

    // Carga los renglones de factventas para pedidos finalizados.
    // Relación: pedidos.venta = factventas.nro  /  pedidos.pv = factventas.pv
    // Clave de agrupación: "{venta}-{pv}" para acceso rápido desde la vista.
    private function loadFactventas($pedidos): \Illuminate\Support\Collection
    {
        $pares = $pedidos
            ->where('estado', Pedido::ESTADO_FINALIZADO)
            ->whereNotNull('venta')
            ->where('venta', '>', 0)
            ->unique(fn($p) => "{$p->venta}-{$p->pv}")
            ->values();

        if ($pares->isEmpty()) {
            return collect();
        }

        $rows = Factventas::where(function ($q) use ($pares) {
            foreach ($pares as $p) {
                $q->orWhere(fn($s) => $s->where('nro', $p->venta)->where('pv', $p->pv));
            }
        })->get();

        // Se agrupa por "venta-pv" igual que la clave usada en la vista
        return $rows->groupBy(fn($f) => "{$f->nro}-{$f->pv}");
    }
}
