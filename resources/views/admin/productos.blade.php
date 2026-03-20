@extends('admin.layout')
@section('title', 'Productos')

@section('content')
<div class="flex items-center justify-between mb-5">
    <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Productos</h1>
    <span class="text-sm text-gray-400">{{ $productos->count() }} productos</span>
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 rounded-lg mb-4">
        {{ session('success') }}
    </div>
@endif

{{-- Buscador --}}
<form method="GET" class="mb-5">
    <input type="text" name="search" value="{{ request('search') }}"
        placeholder="Buscar producto..."
        class="w-full sm:w-80 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
</form>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    @foreach($productos as $producto)
    @php
        $ia          = $producto->iaProducto;
        $enCatalogo  = $ia !== null;
        $imgPath     = $ia?->imagen;
        $tieneImagen = $imgPath && file_exists(public_path($imgPath));
        $imgUrl      = $tieneImagen ? asset($imgPath) : null;
    @endphp
    <div class="bg-white rounded-xl shadow overflow-hidden flex flex-col {{ !$enCatalogo ? 'opacity-60' : '' }}">

        {{-- Imagen --}}
        <div class="relative bg-gray-100 aspect-square flex items-center justify-center overflow-hidden">
            @if($imgUrl)
                <img src="{{ $imgUrl }}" alt="{{ $producto->des }}" class="w-full h-full object-cover">
                @if($enCatalogo)
                <form method="POST" action="{{ route('admin.productos.imagen.delete', $producto) }}"
                      onsubmit="return confirm('¿Eliminar imagen?')"
                      class="absolute top-2 right-2">
                    @csrf @method('DELETE')
                    <button class="bg-white bg-opacity-80 hover:bg-red-100 text-red-600 rounded-full w-7 h-7 flex items-center justify-center text-xs shadow">✕</button>
                </form>
                @endif
            @else
                <span class="text-4xl opacity-20">🥩</span>
            @endif

            {{-- Badge disponible/inactivo --}}
            @if($enCatalogo)
                <span class="absolute top-2 left-2 text-xs px-2 py-0.5 rounded-full font-medium
                    {{ $ia->disponible ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' }}">
                    {{ $ia->disponible ? 'Visible' : 'Oculto' }}
                </span>
            @endif
        </div>

        {{-- Info --}}
        <div class="px-3 py-2 flex-1 flex flex-col gap-2">
            <div>
                <p class="font-medium text-gray-800 text-sm leading-tight">{{ $producto->des }}</p>
                <p class="text-xs text-gray-400 mt-0.5">
                    Precio sistema: {{ number_format($producto->PRE, 2, ',', '.') }} $
                    / {{ $producto->tipo === 'Unidad' ? 'u' : 'kg' }}
                </p>
            </div>

            @if($enCatalogo)
                {{-- Precio bot --}}
                <div class="flex items-center gap-1.5">
                    <span class="text-xs text-gray-500 shrink-0">Precio bot $</span>
                    <input type="number" step="0.01" min="0"
                        value="{{ number_format($ia->precio, 2, '.', '') }}"
                        data-url="{{ route('admin.productos.precio', $producto) }}"
                        class="precio-input w-full border border-gray-200 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-red-300">
                    <span class="precio-status text-xs text-green-500 hidden">✓</span>
                </div>

                {{-- Descripción visible al cliente --}}
                <div class="relative">
                    <textarea
                        data-url="{{ route('admin.productos.descripcion', $producto) }}"
                        placeholder="Descripción visible al cliente..."
                        maxlength="500" rows="2"
                        class="desc-input w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 resize-none focus:outline-none focus:ring-2 focus:ring-red-300 text-gray-600 placeholder-gray-300"
                    >{{ $ia->descripcion }}</textarea>
                    <span class="desc-status absolute top-1 right-1.5 text-xs text-green-500 hidden">✓</span>
                </div>

                {{-- Notas IA --}}
                <div class="relative">
                    <textarea
                        data-url="{{ route('admin.productos.notas_ia', $producto) }}"
                        data-field="notas_ia"
                        placeholder="Notas para la IA (ej: precio fijo, no multiplicar por kg...)"
                        maxlength="500" rows="2"
                        class="ia-input w-full text-xs border border-purple-200 rounded-lg px-2 py-1.5 resize-none focus:outline-none focus:ring-2 focus:ring-purple-300 text-gray-600 placeholder-gray-300 bg-purple-50"
                    >{{ $ia->notas_ia }}</textarea>
                    <span class="ia-status absolute top-1 right-1.5 text-xs text-green-500 hidden">✓</span>
                    <span class="absolute bottom-1 right-1.5 text-xs text-purple-300">🤖 solo IA</span>
                </div>

                {{-- Imagen --}}
                <form method="POST" action="{{ route('admin.productos.imagen', $producto) }}"
                      enctype="multipart/form-data">
                    @csrf
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="file" name="imagen" accept="image/*" class="hidden" onchange="this.form.submit()">
                        <span class="w-full text-center text-xs border border-dashed border-gray-300 group-hover:border-red-400 group-hover:text-red-500 text-gray-400 rounded-lg py-1.5 transition">
                            {{ $imgUrl ? '📷 Cambiar imagen' : '📷 Subir imagen' }}
                        </span>
                    </label>
                </form>

                {{-- Acciones --}}
                <div class="flex gap-2 mt-auto pt-1 border-t border-gray-100">
                    <form method="POST" action="{{ route('admin.productos.disponible', $producto) }}" class="flex-1">
                        @csrf @method('PATCH')
                        <button class="w-full text-xs py-1 rounded-lg border
                            {{ $ia->disponible ? 'border-gray-300 text-gray-500 hover:bg-gray-50' : 'border-green-400 text-green-600 hover:bg-green-50' }}">
                            {{ $ia->disponible ? 'Ocultar' : 'Mostrar' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.productos.catalogo.quitar', $producto) }}"
                          onsubmit="return confirm('¿Quitar del catálogo del bot?')">
                        @csrf @method('DELETE')
                        <button class="text-xs px-3 py-1 rounded-lg border border-red-300 text-red-500 hover:bg-red-50">
                            Quitar
                        </button>
                    </form>
                </div>

            @else
                {{-- No está en el catálogo --}}
                <p class="text-xs text-gray-400 italic">No visible para el bot</p>
                <form method="POST" action="{{ route('admin.productos.catalogo.agregar', $producto) }}" class="mt-auto">
                    @csrf
                    <button class="w-full text-xs py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white font-medium">
                        + Agregar al catálogo
                    </button>
                </form>
            @endif
        </div>

    </div>
    @endforeach
</div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name=csrf-token]').content;

function autoSaveTextarea(selector, statusSelector, bodyKey) {
    document.querySelectorAll(selector).forEach(textarea => {
        let original = textarea.value;
        textarea.addEventListener('blur', async () => {
            if (textarea.value === original) return;
            const status = textarea.parentElement.querySelector(statusSelector);
            try {
                await fetch(textarea.dataset.url, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ [bodyKey]: textarea.value }),
                });
                original = textarea.value;
                status.classList.remove('hidden');
                setTimeout(() => status.classList.add('hidden'), 2000);
            } catch (_) {}
        });
    });
}

autoSaveTextarea('.desc-input', '.desc-status', 'descripcion');
autoSaveTextarea('.ia-input',   '.ia-status',   'notas_ia');

// Auto-save precio
document.querySelectorAll('.precio-input').forEach(input => {
    let original = input.value;
    input.addEventListener('blur', async () => {
        if (input.value === original) return;
        const status = input.parentElement.querySelector('.precio-status');
        try {
            await fetch(input.dataset.url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ precio: input.value }),
            });
            original = input.value;
            status.classList.remove('hidden');
            setTimeout(() => status.classList.add('hidden'), 2000);
        } catch (_) {}
    });
});
</script>
@endsection
