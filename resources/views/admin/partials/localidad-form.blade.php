@php
    $diasLabel = \App\Models\IaEmpresa::DIAS_LABEL;
    $diasConfig = collect($loc?->diasConfig() ?? []);
    $diasMap    = $diasConfig->keyBy('dia');
@endphp
<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
    </div>

    {{-- Días de reparto con config por día --}}
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-2">
            Días de reparto
            <span class="font-normal text-gray-400">(vacío = usa los días globales)</span>
        </label>

        <div class="space-y-2" id="dias-rows-{{ $loc?->id ?? 'new' }}">
            @foreach($diasLabel as $num => $label)
            @php
                $cfg           = old("dias[{$num}]", $diasMap->get($num));
                $checked       = !empty($cfg);
                $desdeDay      = $cfg['desde_dia']      ?? '';
                $desdeHora     = $cfg['desde_hora']     ?? '';
                $hastaDay      = $cfg['hasta_dia']      ?? '';
                $hastaHora     = $cfg['hasta_hora']     ?? '';
                $horarioReparto = $cfg['horario_reparto'] ?? '';
            @endphp
            <div class="dia-row border border-gray-100 rounded-lg px-3 py-2 bg-gray-50">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" name="dias_activos[]" value="{{ $num }}"
                        {{ $checked ? 'checked' : '' }}
                        onchange="toggleDiaConfig(this)"
                        class="accent-red-600 shrink-0">
                    <span class="text-sm font-medium text-gray-700 w-20">{{ $label }}</span>
                </label>

                <div class="dia-config mt-2 ml-6 {{ $checked ? '' : 'hidden' }}">
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="col-span-2">
                            <p class="text-gray-400 mb-1 font-medium">Horario de reparto <span class="font-normal">(se informa al cliente)</span></p>
                            <input type="text" name="dias[{{ $num }}][horario_reparto]"
                                value="{{ $horarioReparto }}"
                                placeholder="Ej: de 9 a 13hs"
                                class="w-full border border-gray-200 rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-red-300">
                        </div>
                        <div>
                            <p class="text-gray-400 mb-1 font-medium">Apertura de pedidos</p>
                            <div class="flex gap-2">
                                <select name="dias[{{ $num }}][desde_dia]"
                                    class="flex-1 border border-gray-200 rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-red-300">
                                    <option value="">— día —</option>
                                    @foreach($diasLabel as $d => $dl)
                                        <option value="{{ $d }}" {{ (string)$desdeDay === (string)$d ? 'selected' : '' }}>{{ $dl }}</option>
                                    @endforeach
                                </select>
                                <input type="time" name="dias[{{ $num }}][desde_hora]"
                                    value="{{ $desdeHora }}"
                                    class="w-24 border border-gray-200 rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-red-300">
                            </div>
                        </div>
                        <div>
                            <p class="text-gray-400 mb-1 font-medium">Cierre de pedidos</p>
                            <div class="flex gap-2">
                                <select name="dias[{{ $num }}][hasta_dia]"
                                    class="flex-1 border border-gray-200 rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-red-300">
                                    <option value="">— día —</option>
                                    @foreach($diasLabel as $d => $dl)
                                        <option value="{{ $d }}" {{ (string)$hastaDay === (string)$d ? 'selected' : '' }}>{{ $dl }}</option>
                                    @endforeach
                                </select>
                                <input type="time" name="dias[{{ $num }}][hasta_hora]"
                                    value="{{ $hastaHora }}"
                                    class="w-24 border border-gray-200 rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-red-300">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Recordatorios automáticos --}}
    <div class="border border-gray-200 rounded-xl p-4 space-y-4">
        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Recordatorios automáticos</p>
        <p class="text-xs text-gray-400">Variables: <code>{nombre}</code>, <code>{dia_reparto}</code>, <code>{horas}</code> (solo cierre). Se envían junto con el catálogo del día automáticamente.</p>

        {{-- Apertura --}}
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-medium cursor-pointer">
                <input type="checkbox" name="rec_apertura" value="1"
                    {{ old('rec_apertura', $loc?->rec_apertura) ? 'checked' : '' }}
                    class="accent-red-600"
                    onchange="document.getElementById('rec_apertura_fields_{{ $loc?->id ?? 'new' }}').classList.toggle('hidden', !this.checked)">
                Aviso de apertura de pedidos
            </label>
            <div id="rec_apertura_fields_{{ $loc?->id ?? 'new' }}" class="{{ old('rec_apertura', $loc?->rec_apertura) ? '' : 'hidden' }} pl-5 space-y-2">
                <textarea name="rec_apertura_mensaje" rows="2"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none"
                    placeholder="¡Hola {nombre}! Ya podés pedir para el {dia_reparto} 🥩">{{ old('rec_apertura_mensaje', $loc?->rec_apertura_mensaje) }}</textarea>
                <input type="text" name="rec_apertura_template" value="{{ old('rec_apertura_template', $loc?->rec_apertura_template) }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none"
                    placeholder="Template de WhatsApp (recomendado para clientes fuera de 24hs)">
            </div>
        </div>

        {{-- Cierre --}}
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-medium cursor-pointer">
                <input type="checkbox" name="rec_cierre" value="1"
                    {{ old('rec_cierre', $loc?->rec_cierre) ? 'checked' : '' }}
                    class="accent-red-600"
                    onchange="document.getElementById('rec_cierre_fields_{{ $loc?->id ?? 'new' }}').classList.toggle('hidden', !this.checked)">
                Aviso de cierre de pedidos
            </label>
            <div id="rec_cierre_fields_{{ $loc?->id ?? 'new' }}" class="{{ old('rec_cierre', $loc?->rec_cierre) ? '' : 'hidden' }} pl-5 space-y-2">
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-gray-500">Enviar</span>
                    <input type="number" name="rec_cierre_horas" min="1" max="48"
                        value="{{ old('rec_cierre_horas', $loc?->rec_cierre_horas ?? 2) }}"
                        class="w-16 border border-gray-300 rounded-lg px-2 py-1 text-sm text-center focus:ring-2 focus:ring-red-300 focus:outline-none">
                    <span class="text-gray-500">horas antes del cierre</span>
                </div>
                <textarea name="rec_cierre_mensaje" rows="2"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none"
                    placeholder="¡{nombre}! Cerramos pedidos para el {dia_reparto} en {horas}hs. Acá la lista 👇">{{ old('rec_cierre_mensaje', $loc?->rec_cierre_mensaje) }}</textarea>
                <input type="text" name="rec_cierre_template" value="{{ old('rec_cierre_template', $loc?->rec_cierre_template) }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:outline-none"
                    placeholder="Template de WhatsApp (opcional)">
            </div>
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

<script>
function toggleDiaConfig(cb) {
    cb.closest('.dia-row').querySelector('.dia-config').classList.toggle('hidden', !cb.checked);
}
</script>
