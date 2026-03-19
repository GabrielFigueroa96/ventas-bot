<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Factventas;
use App\Models\Message;
use App\Models\Pedido;
use App\Models\Pedidosia;
use App\Models\Seguimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        $empresa = Empresa::first();

        // Tipo de cambio — configurar en .env
        $dolarArs = (float) env('DOLAR_ARS', 1300);

        // Costo OpenAI: suma costo_usd guardado por llamada (incluye todos los modelos)
        $tokensMes = DB::table('token_usos')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('SUM(prompt_tokens) as input, SUM(completion_tokens) as output, SUM(total_tokens) as total, SUM(costo_usd) as costo')
            ->first();

        $inputTokens  = (int) ($tokensMes->input  ?? 0);
        $outputTokens = (int) ($tokensMes->output ?? 0);
        $totalTokens  = (int) ($tokensMes->total  ?? 0);
        $costoMesUsd  = round((float) ($tokensMes->costo ?? 0), 6);

        // Costo WhatsApp: conversaciones únicas por cliente/día este mes
        // WhatsApp cobra por ventana de 24h por cliente (aprox. 1 conversación por cliente/día activo)
        $waCostoPorConv = (float) env('WA_COSTO_USD', 0.05);
        $waConvMes = DB::table('messages')
            ->where('direction', 'outgoing')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('COUNT(DISTINCT CONCAT(cliente_id, DATE(created_at))) as conversaciones')
            ->value('conversaciones') ?? 0;

        $costoWaUsd = round($waConvMes * $waCostoPorConv, 4);

        $costoTotalUsd = round($costoMesUsd + $costoWaUsd, 4);
        $costoMes = $costoMesUsd;

        $seguimientos = Seguimiento::with('cliente')
            ->orderByDesc('enviado_at')
            ->take(10)
            ->get();

        $stats = [
            'clientes'     => Cliente::count(),
            'pedidos_hoy'  => Pedido::whereDate('fecha', today())->distinct('nro')->count('nro'),
            'pedidos_pend' => Pedido::where('estado', Pedido::ESTADO_PENDIENTE)->distinct('nro')->count('nro'),
            'mensajes_hoy' => Message::whereDate('created_at', today())->count(),
            'tokens_mes'      => number_format($totalTokens),
            'costo_mes_usd'   => $costoMesUsd,
            'costo_mes_ars'   => round($costoMesUsd * $dolarArs, 2),
            'wa_conv_mes'     => (int) $waConvMes,
            'wa_costo_usd'    => $costoWaUsd,
            'wa_costo_ars'    => round($costoWaUsd * $dolarArs, 2),
            'costo_total_usd' => $costoTotalUsd,
            'costo_total_ars' => round($costoTotalUsd * $dolarArs, 2),
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

        return view('admin.cliente', compact('cliente', 'mensajes', 'pedidos', 'factventas', 'pedidosia', 'lastPedidoReg'));
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

    public function configuracion()
    {
        $empresa = Empresa::first();
        return view('admin.configuracion', compact('empresa'));
    }

    public function guardarConfiguracion(Request $request)
    {
        $empresa = Empresa::first();
        $empresa->update([
            'bot_info'           => $request->bot_info,
            'bot_instrucciones'  => $request->bot_instrucciones,
            'bot_permite_retiro' => $request->boolean('bot_permite_retiro'),
            'bot_permite_envio'  => $request->boolean('bot_permite_envio'),
            'bot_medios_pago'    => $request->has('bot_medios_pago') ? $request->bot_medios_pago : null,
        ]);

        // Limpiar cache para que el bot tome los cambios de inmediato
        \Illuminate\Support\Facades\Cache::forget('productos_bot_lista');
        \Illuminate\Support\Facades\Cache::forget('bot_empresa_config');

        return back()->with('ok', 'Configuración guardada.');
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
