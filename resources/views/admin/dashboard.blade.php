@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')

{{-- Info empresa --}}
@if($empresa)
<div class="bg-white rounded-xl shadow px-4 sm:px-6 py-4 mb-6 flex items-center gap-4">
    <div class="bg-red-100 text-red-600 rounded-full w-12 h-12 flex items-center justify-center text-2xl shrink-0">🥩</div>
    <div class="min-w-0">
        <h2 class="text-lg sm:text-xl font-bold text-gray-800 truncate">{{ $empresa->nombre }}</h2>
        <p class="text-sm text-gray-500 truncate">{{ $empresa->domi }} — {{ $empresa->prov }}</p>
    </div>
</div>
@endif

<h1 class="text-xl sm:text-2xl font-bold text-gray-800 mb-5">Dashboard</h1>

{{-- Stats --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-4">
    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex items-center gap-3 sm:gap-4">
        <div class="bg-red-100 text-red-600 rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-lg sm:text-xl shrink-0">👥</div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase font-medium">Clientes</p>
            <p class="text-2xl sm:text-3xl font-bold text-gray-800">{{ $stats['clientes'] }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex items-center gap-3 sm:gap-4">
        <div class="bg-blue-100 text-blue-600 rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-lg sm:text-xl shrink-0">📦</div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase font-medium">Pedidos hoy</p>
            <p class="text-2xl sm:text-3xl font-bold text-blue-600">{{ $stats['pedidos_hoy'] }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex items-center gap-3 sm:gap-4">
        <div class="bg-yellow-100 text-yellow-600 rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-lg sm:text-xl shrink-0">⏳</div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase font-medium">Pendientes</p>
            <p class="text-2xl sm:text-3xl font-bold text-yellow-500">{{ $stats['pedidos_pend'] }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex items-center gap-3 sm:gap-4">
        <div class="bg-green-100 text-green-600 rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-lg sm:text-xl shrink-0">💬</div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase font-medium">Mensajes hoy</p>
            <p class="text-2xl sm:text-3xl font-bold text-green-600">{{ $stats['mensajes_hoy'] }}</p>
        </div>
    </div>
</div>

{{-- Card costos mes --}}
<div class="bg-white rounded-xl shadow mb-6 sm:mb-8 overflow-hidden">
    <div class="divide-y sm:divide-y-0 sm:divide-x divide-gray-100 flex flex-col sm:flex-row">

        {{-- OpenAI --}}
        <div class="flex items-start gap-3 flex-1 px-4 sm:px-5 py-4">
            <div class="bg-purple-100 text-purple-600 rounded-full w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center text-base sm:text-lg shrink-0 mt-0.5">🤖</div>
            <div class="min-w-0">
                <p class="text-xs text-gray-500 uppercase font-medium tracking-wide">OpenAI este mes</p>
                <p class="text-xl sm:text-2xl font-bold text-gray-800 mt-0.5">{{ $stats['tokens_mes'] }} <span class="text-sm font-normal text-gray-400">tokens</span></p>
                @php
                    $usd = $stats['costo_mes_usd'];
                    $usdFormato = $usd < 0.001 ? '< $0.001' : '$' . rtrim(rtrim(number_format($usd, 4), '0'), '.');
                @endphp
                <p class="text-lg font-bold text-purple-600">{{ $usdFormato }} <span class="text-sm font-normal text-gray-400">USD</span></p>
                <p class="text-sm text-gray-500">≈ ${{ number_format($stats['costo_mes_ars'], 2, ',', '.') }} ARS</p>
                <p class="text-xs text-gray-400 mt-1">gpt-4.1 · $2/1M in · $8/1M out</p>
            </div>
        </div>

        {{-- WhatsApp --}}
        <div class="flex items-start gap-3 flex-1 px-4 sm:px-5 py-4">
            <div class="bg-green-100 text-green-600 rounded-full w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center text-base sm:text-lg shrink-0 mt-0.5">💬</div>
            <div class="min-w-0">
                <p class="text-xs text-gray-500 uppercase font-medium tracking-wide">WhatsApp este mes</p>
                <p class="text-xl sm:text-2xl font-bold text-gray-800 mt-0.5">{{ number_format($stats['wa_conv_mes']) }} <span class="text-sm font-normal text-gray-400">conv.</span></p>
                <p class="text-lg font-bold text-green-600">${{ number_format($stats['wa_costo_usd'], 2) }} <span class="text-sm font-normal text-gray-400">USD</span></p>
                <p class="text-sm text-gray-500">≈ ${{ number_format($stats['wa_costo_ars'], 2, ',', '.') }} ARS</p>
                <p class="text-xs text-gray-400 mt-1">~${{ env('WA_COSTO_USD', '0.05') }}/conv · ajustar en .env</p>
            </div>
        </div>

        {{-- Total --}}
        <div class="flex items-start gap-3 flex-1 px-4 sm:px-5 py-4 bg-orange-50 sm:bg-white">
            <div class="bg-orange-100 text-orange-600 rounded-full w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center text-base sm:text-lg shrink-0 mt-0.5">💰</div>
            <div class="min-w-0">
                <p class="text-xs text-gray-500 uppercase font-medium tracking-wide">Total estimado</p>
                <p class="text-xl sm:text-2xl font-bold text-orange-600 mt-0.5">${{ number_format($stats['costo_total_usd'], 2) }} <span class="text-sm font-normal text-gray-400">USD</span></p>
                <p class="text-sm text-gray-500">≈ ${{ number_format($stats['costo_total_ars'], 2, ',', '.') }} ARS</p>
                <p class="text-xs text-gray-400 mt-1">OpenAI + WhatsApp</p>
            </div>
        </div>

    </div>
</div>

{{-- Gráficos fila 1 --}}
<div class="grid md:grid-cols-3 gap-4 sm:gap-6 mb-4 sm:mb-6">
    <div class="md:col-span-2 bg-white rounded-xl shadow p-4 sm:p-5">
        <h2 class="font-semibold text-gray-700 mb-4">Pedidos — últimos 14 días</h2>
        <canvas id="chartPedidos" height="120"></canvas>
    </div>
    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex flex-col items-center justify-center">
        <h2 class="font-semibold text-gray-700 mb-4 self-start">Estado de pedidos</h2>
        <canvas id="chartEstados" class="max-w-[180px]"></canvas>
        <div class="flex gap-4 mt-4 text-xs sm:text-sm">
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-yellow-400 inline-block"></span> Pendientes</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span> Finalizados</span>
        </div>
    </div>
</div>

{{-- Gráficos fila 2 --}}
<div class="grid md:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white rounded-xl shadow p-4 sm:p-5">
        <h2 class="font-semibold text-gray-700 mb-4">Top artículos pedidos (kg totales)</h2>
        <canvas id="chartArticulos" height="160"></canvas>
    </div>
    <div class="bg-white rounded-xl shadow p-4 sm:p-5">
        <h2 class="font-semibold text-gray-700 mb-4">Clientes nuevos — últimas 8 semanas</h2>
        <canvas id="chartClientes" height="160"></canvas>
    </div>
</div>

{{-- Seguimientos --}}
@if($seguimientos->isNotEmpty())
<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 sm:px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Seguimientos enviados</h2>
        <span class="text-xs text-gray-400">Últimos 10</span>
    </div>
    <div class="divide-y">
        @foreach($seguimientos as $s)
        <div class="px-4 sm:px-6 py-3 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    @if($s->cliente)
                    <a href="{{ route('admin.cliente', $s->cliente) }}" class="font-medium text-gray-800 hover:underline text-sm">
                        {{ $s->cliente->name ?? $s->cliente->phone }}
                    </a>
                    @else
                    <span class="font-medium text-gray-400 text-sm">Cliente eliminado</span>
                    @endif
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium
                        {{ $s->tipo === 'sin_pedido' ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-500' }}">
                        {{ $s->tipo === 'sin_pedido' ? 'Sin pedido' : 'Inactivo' }}
                    </span>
                    @if($s->respondio)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-600 font-medium">✓ Respondió</span>
                    @endif
                </div>
                <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $s->mensaje_enviado }}</p>
            </div>
            <span class="text-xs text-gray-400 shrink-0">{{ \Carbon\Carbon::parse($s->enviado_at)->format('d/m H:i') }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Pedidos recientes --}}
<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Pedidos recientes</h2>
        <a href="{{ route('admin.pedidos') }}" class="text-sm text-red-600 hover:underline">Ver todos</a>
    </div>
    <div class="divide-y">
        @forelse($pedidos_recientes as $nro => $items)
        <div class="px-4 sm:px-6 py-4">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <span class="font-semibold text-gray-800">#{{ $nro }}</span>
                        <span class="text-sm text-gray-500 truncate">{{ $items->first()->nomcli }} — {{ $items->first()->fecha }}</span>
                    </div>
                    <ul class="mt-1 text-sm text-gray-600">
                        @foreach($items as $item)
                            <li>• {{ $item->descrip }} — {{ $item->kilos }} kg/u</li>
                        @endforeach
                    </ul>
                </div>
                <span class="shrink-0 text-xs px-2 py-1 rounded-full font-medium
                    {{ $items->first()->estado == 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' }}">
                    {{ $items->first()->estado_texto }}
                </span>
            </div>
        </div>
        @empty
        <p class="px-6 py-4 text-gray-400 text-sm">No hay pedidos aún.</p>
        @endforelse
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const gridColor  = 'rgba(0,0,0,0.05)';
Chart.defaults.font.family = "'Inter','Segoe UI',sans-serif";
Chart.defaults.color       = '#6b7280';

new Chart(document.getElementById('chartPedidos'), {
    type: 'bar',
    data: {
        labels: @json($chartDias),
        datasets: [{ label: 'Pedidos', data: @json($chartPedidos),
            backgroundColor: 'rgba(220,38,38,0.15)', borderColor: 'rgba(220,38,38,0.9)',
            borderWidth: 2, borderRadius: 6 }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor } },
            x: { grid: { display: false } }
        }
    }
});

new Chart(document.getElementById('chartEstados'), {
    type: 'doughnut',
    data: {
        labels: ['Pendientes', 'Finalizados'],
        datasets: [{ data: @json($chartEstados),
            backgroundColor: ['#fbbf24','#22c55e'], borderWidth: 0, hoverOffset: 6 }]
    },
    options: { cutout: '70%', plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('chartArticulos'), {
    type: 'bar',
    data: {
        labels: @json($chartArticulosLabels),
        datasets: [{ label: 'Kg totales', data: @json($chartArticulosData),
            backgroundColor: ['rgba(220,38,38,0.7)','rgba(234,88,12,0.7)','rgba(202,138,4,0.7)',
                              'rgba(22,163,74,0.7)','rgba(37,99,235,0.7)','rgba(124,58,237,0.7)'],
            borderRadius: 6, borderWidth: 0 }]
    },
    options: {
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, grid: { color: gridColor } },
            y: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

new Chart(document.getElementById('chartClientes'), {
    type: 'line',
    data: {
        labels: @json($chartSemanas),
        datasets: [{ label: 'Clientes', data: @json($chartClientes),
            borderColor: 'rgba(37,99,235,0.9)', backgroundColor: 'rgba(37,99,235,0.08)',
            borderWidth: 2.5, pointBackgroundColor: 'rgba(37,99,235,1)',
            pointRadius: 4, tension: 0.4, fill: true }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor } },
            x: { grid: { display: false } }
        }
    }
});
</script>
@endsection
