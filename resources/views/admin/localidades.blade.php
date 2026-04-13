@extends('admin.layout')
@section('title', 'Localidades')

@section('content')
@php $diasLabel = \App\Models\IaEmpresa::DIAS_LABEL; @endphp
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-800">Localidades de reparto</h1>
        <button onclick="document.getElementById('form-nueva').classList.toggle('hidden')"
            class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">+ Nueva</button>
    </div>

    @if(session('ok'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">{{ session('ok') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">{{ $errors->first() }}</div>
    @endif

    {{-- Formulario nueva --}}
    <div id="form-nueva" class="hidden bg-white rounded-xl shadow p-5">
        <h2 class="font-semibold text-gray-700 mb-4">Nueva localidad</h2>
        @include('admin.partials.localidad-form', ['action' => route('admin.localidades.store'), 'method' => 'POST', 'loc' => null])
    </div>

    {{-- Lista --}}
    <div class="space-y-3">
        @forelse($localidades as $loc)
        <div class="bg-white rounded-xl shadow" x-data="{ editando: false }">
            <div class="flex items-center justify-between px-5 py-4 gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-gray-800">{{ $loc->nombre }}</span>
                        <span class="text-xs text-gray-400">{{ $loc->diasTexto() }}</span>
                        @if($loc->rec_apertura || $loc->rec_cierre)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-600">
                                📬 {{ collect(['apertura' => $loc->rec_apertura, 'cierre' => $loc->rec_cierre])->filter()->keys()->map(fn($k) => ucfirst($k))->implode(' + ') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $loc->clientes()->count() }} cliente(s) vinculados</p>
                </div>
                <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                    <button onclick="toggleLoc({{ $loc->id }}, this)"
                        data-activo="{{ $loc->activo ? '1' : '0' }}"
                        class="text-xs px-3 py-1 rounded-full font-medium {{ $loc->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $loc->activo ? 'Activa' : 'Inactiva' }}
                    </button>
                    @if($loc->rec_apertura || $loc->rec_cierre)
                    <button onclick="abrirProbar({{ $loc->id }}, '{{ addslashes($loc->nombre) }}', {{ json_encode(collect($loc->diasConfig())->map(fn($d) => ['dia' => $d['dia'], 'label' => \App\Models\IaEmpresa::DIAS_LABEL[$d['dia']] ?? $d['dia']])->values()) }})"
                        class="text-xs text-indigo-600 hover:underline">Probar</button>
                    @endif
                    <a href="{{ route('admin.localidades.precios', $loc->id) }}"
                        class="text-xs text-green-600 hover:underline">Precios</a>
                    <button onclick="this.closest('.bg-white').querySelector('.edit-form').classList.toggle('hidden')"
                        class="text-xs text-blue-600 hover:underline">Editar</button>
                    <form method="POST" action="{{ route('admin.localidades.destroy', $loc) }}"
                        onsubmit="return confirm('¿Eliminar {{ $loc->nombre }}?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-500 hover:underline">Eliminar</button>
                    </form>
                </div>
            </div>

            <div class="edit-form hidden border-t px-5 py-4">
                @include('admin.partials.localidad-form', [
                    'action' => route('admin.localidades.update', $loc),
                    'method' => 'PUT',
                    'loc'    => $loc,
                ])
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl shadow px-5 py-6 text-center text-gray-400 text-sm">
            No hay localidades. Creá la primera con "+ Nueva".
        </div>
        @endforelse
    </div>
</div>

{{-- Modal probar recordatorio automático --}}
<div id="modal-probar-loc" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-5 space-y-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">Probar recordatorio</h3>
            <p id="modal-loc-nombre" class="text-xs text-gray-400 mt-0.5"></p>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                <select id="probar-loc-tipo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="apertura">Apertura</option>
                    <option value="cierre">Cierre</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Día de reparto</label>
                <select id="probar-loc-dia" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></select>
            </div>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Teléfono</label>
            <input type="tel" id="probar-loc-phone" placeholder="5491123456789"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div id="probar-loc-preview" class="hidden bg-gray-50 rounded-lg px-3 py-2 text-xs text-gray-600 whitespace-pre-wrap border border-gray-200 max-h-40 overflow-y-auto"></div>
        <div class="flex gap-2 justify-end">
            <button onclick="cerrarProbarLoc()" class="text-sm text-gray-500 hover:underline px-3 py-1.5">Cancelar</button>
            <button id="probar-loc-btn" onclick="enviarPruebaLoc()"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-1.5 rounded-lg">
                Enviar prueba
            </button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let probarLocId = null;

function toggleLoc(id, btn) {
    fetch(`/admin/localidades/${id}/toggle`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(data => {
        btn.dataset.activo = data.activo ? '1' : '0';
        btn.textContent    = data.activo ? 'Activa' : 'Inactiva';
        btn.className = btn.className
            .replace(/bg-\w+-100 text-\w+-\d+/, '')
            .trim() + ' ' + (data.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500');
    });
}

function abrirProbar(id, nombre, dias) {
    probarLocId = id;
    document.getElementById('modal-loc-nombre').textContent = nombre;
    document.getElementById('probar-loc-phone').value = '';
    document.getElementById('probar-loc-preview').classList.add('hidden');
    const sel = document.getElementById('probar-loc-dia');
    sel.innerHTML = dias.map(d => `<option value="${d.dia}">${d.label}</option>`).join('');
    document.getElementById('modal-probar-loc').classList.remove('hidden');
    setTimeout(() => document.getElementById('probar-loc-phone').focus(), 50);
}

function cerrarProbarLoc() {
    document.getElementById('modal-probar-loc').classList.add('hidden');
}

function enviarPruebaLoc() {
    const phone = document.getElementById('probar-loc-phone').value.trim();
    if (!phone) return;
    const tipo = document.getElementById('probar-loc-tipo').value;
    const dia  = document.getElementById('probar-loc-dia').value;
    const btn  = document.getElementById('probar-loc-btn');
    btn.disabled = true; btn.textContent = 'Enviando...';

    fetch(`/admin/localidades/${probarLocId}/probar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        },
        body: JSON.stringify({ phone, tipo, dia }),
    })
    .then(r => r.json())
    .then(data => {
        const prev = document.getElementById('probar-loc-preview');
        prev.classList.remove('hidden');
        if (data.ok) {
            prev.textContent = data.mensaje;
        } else {
            prev.textContent = 'Error: ' + data.error;
        }
    })
    .finally(() => { btn.disabled = false; btn.textContent = 'Enviar prueba'; });
}

document.getElementById('modal-probar-loc').addEventListener('click', function(e) {
    if (e.target === this) cerrarProbarLoc();
});
</script>
@endsection
