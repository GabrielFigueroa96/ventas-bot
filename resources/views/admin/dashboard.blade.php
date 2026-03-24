@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')

{{-- Header empresa --}}
@if($empresa)
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $empresa->nombre }}</h1>
        <p class="text-sm text-gray-400 mt-0.5">{{ $empresa->domi }}{{ $empresa->prov ? ' · '.$empresa->prov : '' }}</p>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-xs text-gray-400 bg-white border border-gray-100 rounded-xl px-4 py-2 shadow-sm">
        <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
        Bot activo
    </div>
</div>
@else
<h1 class="text-2xl font-bold text-gray-900 mb-6">Dashboard</h1>
@endif

{{-- KPIs --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Clientes</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($stats['clientes']) }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4.13a4 4 0 10-8 0 4 4 0 008 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Pedidos hoy</p>
                <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['pedidos_hoy'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 3H8l-2 4h12l-2-4z"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Pendientes</p>
                <p class="text-3xl font-bold mt-1 {{ $stats['pedidos_pend'] > 0 ? 'text-amber-500' : 'text-gray-900' }}">
                    {{ $stats['pedidos_pend'] }}
                </p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        @if($stats['pedidos_pend'] > 0)
        <a href="{{ route('admin.pedidos', ['estado' => 0]) }}" class="inline-block mt-3 text-xs text-amber-600 hover:text-amber-700 font-medium">Ver pendientes →</a>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Mensajes hoy</p>
                <p class="text-3xl font-bold text-emerald-600 mt-1">{{ $stats['mensajes_hoy'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 16c0 1.1-.9 2-2 2H7l-4 4V6a2 2 0 012-2h14a2 2 0 012 2v10z"/>
                </svg>
            </div>
        </div>
    </div>

</div>

{{-- Gráficos fila 1 --}}
<div class="grid lg:grid-cols-3 gap-4 mb-4">

    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700">Pedidos — últimos 14 días</h2>
        </div>
        <canvas id="chartPedidos" height="110"></canvas>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-col">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Estado de pedidos</h2>
        <div class="flex-1 flex flex-col items-center justify-center">
            <div class="relative w-36 h-36">
                <canvas id="chartEstados"></canvas>
                @php $total = array_sum($chartEstados); @endphp
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-2xl font-bold text-gray-800">{{ $total }}</span>
                    <span class="text-xs text-gray-400">total</span>
                </div>
            </div>
            <div class="flex gap-5 mt-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span> Pendientes
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span> Finalizados
                </span>
            </div>
        </div>
    </div>

</div>

{{-- Gráficos fila 2 --}}
<div class="grid md:grid-cols-2 gap-4 mb-6">

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Top productos pedidos <span class="text-gray-400 font-normal">(kg)</span></h2>
        <canvas id="chartArticulos" height="170"></canvas>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Clientes nuevos <span class="text-gray-400 font-normal">— últimas 8 semanas</span></h2>
        <canvas id="chartClientes" height="170"></canvas>
    </div>

</div>

{{-- Recordatorios de hoy --}}
@if($recordatoriosHoy->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-4">
    <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">Recordatorios de hoy</h2>
        </div>
        <a href="{{ route('admin.recordatorios') }}" class="text-xs text-red-500 hover:text-red-600 font-medium">Ver todos →</a>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach($recordatoriosHoy as $rec)
        <div class="px-5 py-3.5 flex items-center justify-between gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-gray-800">{{ $rec->nombre }}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-medium">{{ $rec->hora }}</span>
                    @if($rec->filtro_localidad || $rec->filtro_provincia)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 font-medium">
                            {{ $rec->filtro_localidad ?? $rec->filtro_provincia }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-400 mt-0.5 truncate">{{ Str::limit($rec->mensaje, 80) }}</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <span class="text-xs text-gray-500 font-medium">{{ $rec->clientes_count }} dest.</span>
                @if($rec->disparado)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ $rec->ultimo_envio_at->format('H:i') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Pendiente
                    </span>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Seguimientos --}}
@if($seguimientos->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-4">
    <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">Seguimientos enviados</h2>
        </div>
        <span class="text-xs text-gray-400">Últimos 10</span>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach($seguimientos as $s)
        <div class="px-5 py-3.5 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    @if($s->cliente)
                    <a href="{{ route('admin.cliente', $s->cliente) }}" class="text-sm font-medium text-gray-800 hover:text-red-600 transition-colors">
                        {{ $s->cliente->name ?? $s->cliente->phone }}
                    </a>
                    @else
                    <span class="text-sm font-medium text-gray-400">Cliente eliminado</span>
                    @endif
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium
                        {{ $s->tipo === 'sin_pedido' ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-500' }}">
                        {{ $s->tipo === 'sin_pedido' ? 'Sin pedido' : 'Inactivo' }}
                    </span>
                    @if($s->respondio)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600 font-medium">✓ Respondió</span>
                    @endif
                </div>
                <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $s->mensaje_enviado }}</p>
            </div>
            <span class="text-xs text-gray-400 shrink-0 mt-0.5">{{ \Carbon\Carbon::parse($s->enviado_at)->format('d/m H:i') }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Pedidos recientes --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01m-.01 4h.01"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">Pedidos recientes</h2>
        </div>
        <a href="{{ route('admin.pedidos') }}" class="text-xs text-red-500 hover:text-red-600 font-medium">Ver todos →</a>
    </div>
    <div class="divide-y divide-gray-50">
        @forelse($pedidos_recientes as $pedido)
        <div class="px-5 py-3.5 flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex items-baseline gap-2 flex-wrap">
                    <span class="text-xs font-bold text-gray-400">#{{ $pedido->nro }}</span>
                    <span class="text-sm font-semibold text-gray-800 truncate">{{ $pedido->nomcli }}</span>
                    <span class="text-xs text-gray-400">{{ $pedido->fecha->format('d/m/Y') }}</span>
                </div>
                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
                    @foreach($pedido->items as $item)
                    <span class="text-xs text-gray-500">
                        {{ $item->descrip }}
                        <span class="text-gray-400">
                            @if($item->kilos > 0 && $item->cant > 0)
                                — {{ (int) $item->cant }}u · {{ number_format($item->kilos, 2, ',', '.') }}kg
                            @elseif($item->kilos > 0)
                                — {{ number_format($item->kilos, 3, ',', '.') }}kg
                            @elseif($item->cant > 0)
                                — {{ (int) $item->cant }}u
                            @endif
                        </span>
                    </span>
                    @endforeach
                </div>
            </div>
            <span class="shrink-0 inline-flex items-center text-xs px-2.5 py-1 rounded-full font-semibold {{ $pedido->estadoCss() }}">
                {{ $pedido->estadoLabel() }}
            </span>
        </div>
        @empty
        <div class="px-5 py-8 text-center">
            <p class="text-sm text-gray-400">No hay pedidos aún.</p>
        </div>
        @endforelse
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const gridColor = 'rgba(0,0,0,0.04)';
Chart.defaults.font.family = "'Inter','Segoe UI',sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#9ca3af';

new Chart(document.getElementById('chartPedidos'), {
    type: 'bar',
    data: {
        labels: @json($chartDias),
        datasets: [{
            data: @json($chartPedidos),
            backgroundColor: 'rgba(220,38,38,0.12)',
            borderColor: 'rgba(220,38,38,0.85)',
            borderWidth: 2,
            borderRadius: 5,
            borderSkipped: false,
        }]
    },
    options: {
        plugins: { legend: { display: false }, tooltip: { callbacks: {
            label: ctx => ` ${ctx.parsed.y} pedidos`
        }}},
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor }, border: { display: false } },
            x: { grid: { display: false }, border: { display: false } }
        }
    }
});

new Chart(document.getElementById('chartEstados'), {
    type: 'doughnut',
    data: {
        labels: ['Pendientes', 'Finalizados'],
        datasets: [{
            data: @json($chartEstados),
            backgroundColor: ['#fbbf24', '#10b981'],
            borderWidth: 0,
            hoverOffset: 4,
        }]
    },
    options: {
        cutout: '72%',
        plugins: { legend: { display: false }, tooltip: { callbacks: {
            label: ctx => ` ${ctx.label}: ${ctx.parsed}`
        }}}
    }
});

new Chart(document.getElementById('chartArticulos'), {
    type: 'bar',
    data: {
        labels: @json($chartArticulosLabels),
        datasets: [{
            data: @json($chartArticulosData),
            backgroundColor: [
                'rgba(220,38,38,0.75)', 'rgba(234,88,12,0.75)', 'rgba(202,138,4,0.75)',
                'rgba(22,163,74,0.75)', 'rgba(37,99,235,0.75)', 'rgba(124,58,237,0.75)'
            ],
            borderRadius: 5,
            borderWidth: 0,
        }]
    },
    options: {
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, grid: { color: gridColor }, border: { display: false } },
            y: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

new Chart(document.getElementById('chartClientes'), {
    type: 'line',
    data: {
        labels: @json($chartSemanas),
        datasets: [{
            data: @json($chartClientes),
            borderColor: 'rgba(37,99,235,0.85)',
            backgroundColor: 'rgba(37,99,235,0.07)',
            borderWidth: 2.5,
            pointBackgroundColor: 'rgba(37,99,235,1)',
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.4,
            fill: true,
        }]
    },
    options: {
        plugins: { legend: { display: false }, tooltip: { callbacks: {
            label: ctx => ` ${ctx.parsed.y} clientes`
        }}},
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor }, border: { display: false } },
            x: { grid: { display: false }, border: { display: false } }
        }
    }
});
</script>
@endsection
