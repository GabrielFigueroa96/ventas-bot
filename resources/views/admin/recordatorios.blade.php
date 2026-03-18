@extends('admin.layout')
@section('title', 'Recordatorios')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-800">Recordatorios</h1>
        <button onclick="document.getElementById('form-panel').classList.toggle('hidden')"
            class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">
            + Nuevo
        </button>
    </div>

    @if(session('ok'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">{{ session('ok') }}</div>
    @endif

    {{-- Formulario --}}
    @php $editando = $editando ?? null; @endphp
    <div id="form-panel" class="{{ $editando ? '' : 'hidden' }} bg-white rounded-xl shadow p-5 space-y-4">
        <h2 class="font-semibold text-gray-700">{{ $editando ? 'Editar recordatorio' : 'Nuevo recordatorio' }}</h2>

        <form method="POST"
              action="{{ $editando ? route('admin.recordatorios.update', $editando) : route('admin.recordatorios.store') }}"
              class="space-y-4">
            @csrf
            @if($editando) @method('PUT') @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre interno</label>
                    <input type="text" name="nombre" value="{{ old('nombre', $editando?->nombre) }}" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo</label>
                    <select name="tipo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
                        <option value="libre"          {{ old('tipo', $editando?->tipo) === 'libre'           ? 'selected' : '' }}>Libre</option>
                        <option value="recomendacion"  {{ old('tipo', $editando?->tipo) === 'recomendacion'  ? 'selected' : '' }}>Recomendación personalizada</option>
                        <option value="repetir_pedido" {{ old('tipo', $editando?->tipo) === 'repetir_pedido' ? 'selected' : '' }}>Repetir pedido anterior</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Mensaje</label>
                <p class="text-xs text-gray-400 mb-1">
                    Variables: <code>{nombre}</code>
                    — tipo recomendación: <code>{recomendaciones}</code>
                    — tipo repetir: <code>{ultimo_pedido}</code>
                </p>
                <textarea name="mensaje" rows="4" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none"
                    placeholder="Ej: ¡Hola {nombre}! 🥩 Esta semana tenemos pollo con 10% de descuento. ¿Hacemos un pedido?">{{ old('mensaje', $editando?->mensaje) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Hora de envío</label>
                    <input type="time" name="hora" value="{{ old('hora', $editando ? substr($editando->hora, 0, 5) : '09:00') }}" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Filtrar por localidad</label>
                    <select name="filtro_localidad"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
                        <option value="">Todas las localidades</option>
                        @foreach($localidades as $loc)
                            <option value="{{ $loc->nombre }}"
                                {{ old('filtro_localidad', $editando?->filtro_localidad) === $loc->nombre ? 'selected' : '' }}>
                                {{ $loc->nombre }}{{ $loc->provincia ? " ({$loc->provincia})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Filtrar por provincia</label>
                    <select name="filtro_provincia"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
                        <option value="">Todas las provincias</option>
                        @foreach($provincias as $prov)
                            <option value="{{ $prov }}"
                                {{ old('filtro_provincia', $editando?->filtro_provincia) === $prov ? 'selected' : '' }}>
                                {{ $prov }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-2">Días de envío (vacío = todos los días)</label>
                <div class="flex flex-wrap gap-2">
                    @foreach(\App\Models\Recordatorio::$DIAS_LABEL as $num => $label)
                        <label class="flex items-center gap-1 text-sm cursor-pointer">
                            <input type="checkbox" name="dias[]" value="{{ $num }}"
                                {{ in_array($num, old('dias', $editando?->dias ?? [])) ? 'checked' : '' }}
                                class="accent-red-600">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                    class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-6 py-2 rounded-lg">
                    {{ $editando ? 'Guardar cambios' : 'Crear recordatorio' }}
                </button>
                <a href="{{ route('admin.recordatorios') }}"
                    class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
            </div>
        </form>
    </div>

    {{-- Lista --}}
    <div class="space-y-3">
        @forelse($recordatorios as $rec)
        <div class="bg-white rounded-xl shadow px-5 py-4 flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0 space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-semibold text-gray-800">{{ $rec->nombre }}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full
                        {{ $rec->tipo === 'libre' ? 'bg-gray-100 text-gray-600' :
                           ($rec->tipo === 'recomendacion' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700') }}">
                        {{ ['libre'=>'Libre','recomendacion'=>'Recomendación','repetir_pedido'=>'Repetir pedido'][$rec->tipo] }}
                    </span>
                    <span class="text-xs text-gray-400">🕐 {{ substr($rec->hora,0,5) }} · {{ $rec->diasTexto() }}</span>
                    @if($rec->filtro_localidad)
                        <span class="text-xs text-purple-600">📍 {{ $rec->filtro_localidad }}</span>
                    @endif
                    @if($rec->filtro_provincia)
                        <span class="text-xs text-purple-600">🗺 {{ $rec->filtro_provincia }}</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500 truncate">{{ $rec->mensaje }}</p>
                @if($rec->ultimo_envio_at)
                    <p class="text-xs text-gray-400">Último envío: {{ $rec->ultimo_envio_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 shrink-0">
                {{-- Toggle activo --}}
                <button onclick="toggleActivo({{ $rec->id }}, this)"
                    data-activo="{{ $rec->activo ? '1' : '0' }}"
                    class="text-xs px-3 py-1 rounded-full font-medium transition
                        {{ $rec->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $rec->activo ? 'Activo' : 'Pausado' }}
                </button>
                <a href="{{ route('admin.recordatorios.edit', $rec) }}"
                    class="text-xs text-blue-600 hover:underline">Editar</a>
                <form method="POST" action="{{ route('admin.recordatorios.destroy', $rec) }}"
                    onsubmit="return confirm('¿Eliminar este recordatorio?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-red-500 hover:underline">Eliminar</button>
                </form>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl shadow px-5 py-6 text-center text-gray-400 text-sm">
            No hay recordatorios. Creá el primero con el botón "+Nuevo".
        </div>
        @endforelse
    </div>
</div>
@endsection

@section('scripts')
<script>
function toggleActivo(id, btn) {
    fetch(`/admin/recordatorios/${id}/toggle`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    })
    .then(r => r.json())
    .then(data => {
        btn.dataset.activo = data.activo ? '1' : '0';
        btn.textContent    = data.activo ? 'Activo' : 'Pausado';
        btn.className = 'text-xs px-3 py-1 rounded-full font-medium transition ' +
            (data.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500');
    });
}
</script>
@endsection
