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
        $imgUrl = $producto->imagen ? asset('storage/' . $producto->imagen) : null;
    @endphp
    <div class="bg-white rounded-xl shadow overflow-hidden flex flex-col">

        {{-- Imagen --}}
        <div class="relative bg-gray-100 aspect-square flex items-center justify-center overflow-hidden">
            @if($imgUrl)
                <img src="{{ $imgUrl }}" alt="{{ $producto->des }}"
                     class="w-full h-full object-cover">
                <form method="POST"
                      action="{{ route('admin.productos.imagen.delete', $producto) }}"
                      onsubmit="return confirm('¿Eliminar imagen?')"
                      class="absolute top-2 right-2">
                    @csrf
                    @method('DELETE')
                    <button class="bg-white bg-opacity-80 hover:bg-red-100 text-red-600 rounded-full w-7 h-7 flex items-center justify-center text-xs shadow">
                        ✕
                    </button>
                </form>
            @else
                <span class="text-4xl opacity-20">🥩</span>
            @endif
        </div>

        {{-- Info --}}
        <div class="px-3 py-2 flex-1 flex flex-col gap-2">
            <div>
                <p class="font-medium text-gray-800 text-sm leading-tight">{{ $producto->des }}</p>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ number_format($producto->PRE, 2, ',', '.') }} $
                    / {{ $producto->tipo === 'Unidad' ? 'u' : 'kg' }}
                </p>
            </div>

            {{-- Descripción para ChatGPT --}}
            <div class="relative">
                <textarea
                    data-url="{{ route('admin.productos.descripcion', $producto) }}"
                    placeholder="Descripción para el bot (ej: corte tierno ideal para asado, se vende entero...)"
                    maxlength="255"
                    rows="2"
                    class="desc-input w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 resize-none focus:outline-none focus:ring-2 focus:ring-red-300 text-gray-600 placeholder-gray-300"
                >{{ $producto->descripcion }}</textarea>
                <span class="desc-status absolute top-1 right-1.5 text-xs text-green-500 hidden">✓</span>
            </div>

            {{-- Upload --}}
            <form method="POST"
                  action="{{ route('admin.productos.imagen', $producto) }}"
                  enctype="multipart/form-data"
                  class="mt-auto">
                @csrf
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="file" name="imagen" accept="image/*" class="hidden"
                           onchange="this.form.submit()">
                    <span class="w-full text-center text-xs border border-dashed border-gray-300 group-hover:border-red-400 group-hover:text-red-500 text-gray-400 rounded-lg py-1.5 transition">
                        {{ $imgUrl ? '📷 Cambiar imagen' : '📷 Subir imagen' }}
                    </span>
                </label>
            </form>
        </div>

    </div>
    @endforeach
</div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name=csrf-token]').content;

document.querySelectorAll('.desc-input').forEach(textarea => {
    let original = textarea.value;

    textarea.addEventListener('blur', async () => {
        if (textarea.value === original) return;

        const status = textarea.parentElement.querySelector('.desc-status');
        try {
            await fetch(textarea.dataset.url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ descripcion: textarea.value }),
            });
            original = textarea.value;
            status.classList.remove('hidden');
            setTimeout(() => status.classList.add('hidden'), 2000);
        } catch (_) {}
    });
});
</script>
@endsection
