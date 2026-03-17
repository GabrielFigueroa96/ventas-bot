<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Factventas;
use App\Models\Message;
use App\Models\Pedido;
use App\Models\Seguimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        $empresa = Empresa::first();

        // Precios gpt-4o-mini por token (USD)
        $precioPorMillon = ['input' => 0.150, 'output' => 0.600];

        $tokensMes = DB::table('token_usos')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('SUM(prompt_tokens) as input, SUM(completion_tokens) as output, SUM(total_tokens) as total')
            ->first();

        $inputTokens  = (int) ($tokensMes->input  ?? 0);
        $outputTokens = (int) ($tokensMes->output ?? 0);
        $totalTokens  = (int) ($tokensMes->total  ?? 0);

        $costoMesUsd = round(
            ($inputTokens  / 1_000_000 * $precioPorMillon['input']) +
            ($outputTokens / 1_000_000 * $precioPorMillon['output']),
            6
        );

        // Tipo de cambio aprox — actualizá este valor en .env como DOLAR_ARS
        $dolarArs = (float) env('DOLAR_ARS', 1300);
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
            'tokens_mes'   => number_format($totalTokens),
            'costo_mes_usd'=> $costoMes,
            'costo_mes_ars'=> round($costoMes * $dolarArs, 2),
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

    public function cliente(Cliente $cliente)
    {
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
        $lastPedidoReg = (int) ($pedidosRaw->max('reg') ?? 0);

        return view('admin.cliente', compact('cliente', 'mensajes', 'pedidos', 'factventas', 'lastPedidoReg'));
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

        return view('admin.pedidos', compact('pedidos', 'factventas'));
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
