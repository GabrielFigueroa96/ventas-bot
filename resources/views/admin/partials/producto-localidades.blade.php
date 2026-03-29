@php
$configuredLocIds = $ia->localidades->pluck('localidad_id')->toArray();
$availableLocs    = $localidades->whereNotIn('id', $configuredLocIds);
$diasLabel        = [0=>'Dom',1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb'];
@endphp

<div class="mt-3 pt-3 border-t border-gray-100">
    <div class="flex items-center justify-between mb-1.5">
        <span class="text-xs font-semibold text-blue-600">Precios y días por localidad</span>
        @if($availableLocs->isNotEmpty())
        <button type="button" onclick="toggleAddLoc('{{ $cod }}')"
            class="text-xs text-blue-600 border border-blue-200 bg-blue-50 hover:bg-blue-100 px-2 py-0.5 rounded-full transition">
            + Agregar localidad
        </button>
        @endif
    </div>

    {{-- Rows existentes --}}
    <div id="loc-list-{{ $cod }}" class="space-y-1">
        @forelse($ia->localidades as $pl)
        <div class="flex flex-wrap items-center gap-1.5 text-xs bg-white border border-gray-100 rounded-lg px-2 py-1.5">
            <span class="font-medium text-gray-700 min-w-[80px]">{{ $pl->localidad?->nombre ?? '—' }}</span>
            <div class="flex items-center gap-1">
                <span class="text-gray-400 text-xs">$</span>
                <input type="number" step="0.01" min="0"
                    placeholder="{{ number_format($ia->precio, 2, '.', '') }}"
                    value="{{ $pl->precio !== null ? number_format($pl->precio, 2, '.', '') : '' }}"
                    data-cod="{{ $cod }}" data-loc="{{ $pl->localidad_id }}"
                    class="pl-loc-precio w-20 border border-gray-200 rounded px-1.5 py-0.5 text-right tabular-nums focus:outline-none focus:ring-1 focus:ring-blue-300">
                <span class="pl-precio-ok text-green-500 hidden text-xs">✓</span>
            </div>
            <span class="text-gray-300">|</span>
            @if($pl->dias_reparto)
            <span class="text-gray-600">
                {{ collect($pl->dias_reparto)->map(fn($d) => $diasLabel[is_array($d) ? $d['dia'] : (int)$d] ?? '?')->join(', ') }}
            </span>
            @else
            <span class="text-gray-400 italic">días de localidad</span>
            @endif
            <button type="button"
                onclick="removeLoc('{{ $cod }}', {{ $pl->localidad_id }}, this)"
                class="ml-auto text-red-400 hover:text-red-600 leading-none">✕</button>
        </div>
        @empty
        <p class="text-xs text-gray-400 italic">Sin configuración por localidad.</p>
        @endforelse
    </div>

    {{-- Formulario agregar --}}
    <div id="loc-add-{{ $cod }}" class="hidden mt-2 p-2 bg-blue-50 border border-blue-200 rounded-lg space-y-2">
        <div class="flex flex-wrap gap-2 items-end">
            <div>
                <label class="block text-xs text-gray-500 mb-0.5">Localidad</label>
                <select id="loc-add-sel-{{ $cod }}"
                    onchange="onLocSelChange('{{ $cod }}')"
                    class="border border-gray-200 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-300 bg-white">
                    <option value="">— Seleccionar —</option>
                    @foreach($availableLocs as $loc)
                    <option value="{{ $loc->id }}"
                        data-dias="{{ collect($loc->diasConfig())->pluck('dia')->join(',') }}">
                        {{ $loc->nombre }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-0.5">Precio (vacío = precio base)</label>
                <input type="number" step="0.01" min="0"
                    id="loc-add-precio-{{ $cod }}"
                    placeholder="{{ number_format($ia->precio, 2, '.', '') }}"
                    class="w-28 border border-gray-200 rounded px-2 py-1 text-xs text-right tabular-nums focus:outline-none focus:ring-1 focus:ring-blue-300 bg-white">
            </div>
        </div>

        {{-- Días override --}}
        <div id="loc-add-dias-{{ $cod }}" class="hidden">
            <label class="block text-xs text-gray-500 mb-1">Días disponibles (vacío = días de localidad)</label>
            <div class="flex flex-wrap gap-3">
                @foreach([1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',0=>'Dom'] as $num => $lbl)
                <label class="flex items-center gap-1 text-xs cursor-pointer">
                    <input type="checkbox" value="{{ $num }}" class="loc-add-dia-{{ $cod }} rounded"> {{ $lbl }}
                </label>
                @endforeach
            </div>
        </div>

        <div class="flex gap-2 justify-end">
            <button type="button" onclick="saveLoc('{{ $cod }}')"
                class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-full transition">
                Guardar
            </button>
            <button type="button" onclick="toggleAddLoc('{{ $cod }}')"
                class="text-xs text-gray-500 border border-gray-200 px-3 py-1 rounded-full hover:bg-gray-50 transition">
                Cancelar
            </button>
        </div>
    </div>
</div>
