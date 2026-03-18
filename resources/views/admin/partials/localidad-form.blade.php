@php $diasLabel = \App\Models\Empresa::DIAS_LABEL; @endphp
<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Localidad</label>
            <input type="text" name="nombre" value="{{ old('nombre', $loc?->nombre) }}" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none"
                placeholder="Ej: Rosario">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Provincia</label>
            <input type="text" name="provincia" value="{{ old('provincia', $loc?->provincia) }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none"
                placeholder="Ej: Santa Fe">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Recargo por zona ($)</label>
            <input type="number" name="costo_extra" min="0" step="0.01"
                value="{{ old('costo_extra', $loc?->costo_extra ?? 0) }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none">
            <p class="text-xs text-gray-400 mt-1">Dejá 0 por ahora, se usará para el cálculo futuro.</p>
        </div>
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-2">Días de reparto (vacío = usa los días globales)</label>
        <div class="flex flex-wrap gap-3">
            @foreach($diasLabel as $num => $label)
                <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                    <input type="checkbox" name="dias_reparto[]" value="{{ $num }}"
                        {{ in_array($num, old('dias_reparto', $loc?->dias_reparto ?? [])) ? 'checked' : '' }}
                        class="accent-red-600">
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="activo" value="1"
                {{ old('activo', $loc?->activo ?? true) ? 'checked' : '' }}
                class="accent-red-600">
            Activa
        </label>
        <button type="submit"
            class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-5 py-2 rounded-lg">
            {{ $loc ? 'Guardar' : 'Crear' }}
        </button>
    </div>
</form>
