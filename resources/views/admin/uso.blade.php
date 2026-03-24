@extends('admin.layout')
@section('title', 'Uso IA')

@section('content')

<h1 class="text-xl sm:text-2xl font-bold text-gray-800 mb-6">Uso IA</h1>

{{-- Stats resumen mes --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-6">

    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex items-center gap-3">
        <div class="bg-purple-100 text-purple-600 rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-lg shrink-0">🤖</div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase font-medium">Tokens este mes</p>
            <p class="text-2xl sm:text-3xl font-bold text-gray-800">{{ number_format($totalTokens) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex items-center gap-3">
        <div class="bg-blue-100 text-blue-600 rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-lg shrink-0">📥</div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase font-medium">Input</p>
            <p class="text-2xl sm:text-3xl font-bold text-blue-600">{{ number_format($inputTokens) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex items-center gap-3">
        <div class="bg-indigo-100 text-indigo-600 rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-lg shrink-0">📤</div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase font-medium">Output</p>
            <p class="text-2xl sm:text-3xl font-bold text-indigo-600">{{ number_format($outputTokens) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex items-center gap-3">
        <div class="bg-green-100 text-green-600 rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-lg shrink-0">💬</div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase font-medium">Conv. WA este mes</p>
            <p class="text-2xl sm:text-3xl font-bold text-green-600">{{ number_format($waConvMes) }}</p>
        </div>
    </div>

</div>

{{-- Costos estimados --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-6">

    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex items-center gap-3">
        <div class="bg-purple-100 text-purple-600 rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-lg shrink-0">🧠</div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase font-medium">Costo IA este mes</p>
            <p class="text-2xl sm:text-3xl font-bold text-purple-700">U$S {{ number_format($costoIaMes, 4) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex items-center gap-3">
        <div class="bg-green-100 text-green-600 rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-lg shrink-0">📲</div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase font-medium">Costo WA estimado</p>
            <p class="text-2xl sm:text-3xl font-bold text-green-700">U$S {{ number_format($costoWaMes, 2) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ number_format($templatesMes) }} templates × U$S 0.05</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-4 sm:p-5 flex items-center gap-3">
        <div class="bg-red-100 text-red-600 rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center text-lg shrink-0">💰</div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase font-medium">Costo total estimado</p>
            <p class="text-2xl sm:text-3xl font-bold text-red-700">U$S {{ number_format($costoIaMes + $costoWaMes, 2) }}</p>
        </div>
    </div>

</div>

{{-- Detalle por modelo --}}
@if($filasMes->isNotEmpty())
<div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b">
        <h2 class="font-semibold text-gray-700">Tokens por modelo — este mes</h2>
    </div>
    <div class="divide-y">
        @foreach($filasMes as $fila)
        <div class="px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
            <span class="font-medium text-gray-700 text-sm">{{ $fila->modelo }}</span>
            <div class="flex gap-6 text-sm text-right">
                <div>
                    <p class="text-xs text-gray-400">Input</p>
                    <p class="font-semibold text-gray-700">{{ number_format((int)$fila->input) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Output</p>
                    <p class="font-semibold text-gray-700">{{ number_format((int)$fila->output) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Total</p>
                    <p class="font-semibold text-purple-600">{{ number_format((int)$fila->total) }}</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Gráficos --}}
<div class="grid md:grid-cols-2 gap-4 sm:gap-6">

    <div class="bg-white rounded-xl shadow p-4 sm:p-5">
        <h2 class="font-semibold text-gray-700 mb-4">Tokens — últimos 14 días</h2>
        <canvas id="chartTokens" height="160"></canvas>
    </div>

    <div class="bg-white rounded-xl shadow p-4 sm:p-5">
        <h2 class="font-semibold text-gray-700 mb-4">Mensajes WA enviados — últimos 14 días</h2>
        <canvas id="chartWa" height="160"></canvas>
    </div>

</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const gridColor = 'rgba(0,0,0,0.05)';
Chart.defaults.font.family = "'Inter','Segoe UI',sans-serif";
Chart.defaults.color       = '#6b7280';

new Chart(document.getElementById('chartTokens'), {
    type: 'bar',
    data: {
        labels: @json($chartDias),
        datasets: [{ label: 'Tokens', data: @json($chartTokens),
            backgroundColor: 'rgba(124,58,237,0.15)', borderColor: 'rgba(124,58,237,0.8)',
            borderWidth: 2, borderRadius: 6 }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: gridColor } },
            x: { grid: { display: false } }
        }
    }
});

new Chart(document.getElementById('chartWa'), {
    type: 'bar',
    data: {
        labels: @json($chartWaDias),
        datasets: [{ label: 'Mensajes', data: @json($chartWa),
            backgroundColor: 'rgba(22,163,74,0.15)', borderColor: 'rgba(22,163,74,0.8)',
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
</script>
@endsection
