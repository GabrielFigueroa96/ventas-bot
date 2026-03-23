@extends('tienda.layout')
@section('title', ($empresaNombre ?? $empresa->nombre_ia ?? 'Tienda') . ' — Catálogo')

@section('content')

{{-- Banner info del negocio --}}
@if($empresa->bot_info || $empresa->tienda_facebook || $empresa->tienda_instagram || $empresa->tienda_tiktok)
<div class="mb-5 bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        @if($empresa->bot_info)
            <p class="text-sm text-gray-500 flex-1">{{ $empresa->bot_info }}</p>
        @endif
        @if($empresa->tienda_facebook || $empresa->tienda_instagram || $empresa->tienda_tiktok)
            <div class="flex items-center gap-2">
                @if($empresa->tienda_facebook)
                    <a href="{{ $empresa->tienda_facebook }}" target="_blank" rel="noopener"
                        class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="Facebook">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                @endif
                @if($empresa->tienda_instagram)
                    <a href="{{ $empresa->tienda_instagram }}" target="_blank" rel="noopener"
                        class="w-8 h-8 flex items-center justify-center rounded-full bg-pink-50 text-pink-600 hover:bg-pink-100 transition" title="Instagram">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                @endif
                @if($empresa->tienda_tiktok)
                    <a href="{{ $empresa->tienda_tiktok }}" target="_blank" rel="noopener"
                        class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-800 hover:bg-gray-200 transition" title="TikTok">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.76a4.85 4.85 0 01-1.01-.07z"/></svg>
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
@endif

{{-- Banner: pedidos del cliente --}}
@if($cliente)
<a href="{{ route('tienda.mis_pedidos', ['slug' => $slug]) }}"
    class="mb-5 flex items-center gap-3 bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3 hover:border-red-200 transition group">
    <div class="w-9 h-9 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0 group-hover:bg-red-100 transition">
        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
    </div>
    <div class="flex-1">
        <p class="text-sm font-semibold text-gray-800">Mis pedidos</p>
        <p class="text-xs text-gray-400">Ver el historial de mis compras</p>
    </div>
    <svg class="w-4 h-4 text-gray-300 group-hover:text-red-400 transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
    </svg>
</a>
@endif

{{-- Banner: no logueado --}}
@if(!$cliente)
<div class="mb-5 bg-white border border-gray-100 rounded-2xl px-5 py-4 flex items-center gap-4 shadow-sm">
    <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-800">Ver precios y mis pedidos</p>
        <p class="text-xs text-gray-400 mt-0.5">Iniciá sesión para ver tu historial.</p>
    </div>
    <a href="{{ route('tienda.login', ['slug' => $slug]) }}"
        class="flex-shrink-0 bg-red-700 hover:bg-red-800 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
        Ingresar
    </a>
</div>
@endif

{{-- Filtro por grupo --}}
@if($grupos->count() > 1)
<div class="mb-4 overflow-x-auto scrollbar-hide pb-1 -mx-1 px-1">
    <div class="flex gap-2 min-w-max" id="tabs-grupos">
        <button onclick="filtrarGrupo('__todos__')" data-grupo="__todos__"
            class="tab-btn px-4 py-1.5 rounded-full text-xs font-semibold border transition whitespace-nowrap bg-red-700 text-white border-red-700">
            Todos
        </button>
        @foreach($grupos as $grupo => $prods)
            <button onclick="filtrarGrupo('{{ e($grupo) }}')" data-grupo="{{ $grupo }}"
                class="tab-btn px-4 py-1.5 rounded-full text-xs font-semibold border border-gray-200 bg-white text-gray-600 hover:border-red-300 hover:text-red-700 transition whitespace-nowrap">
                {{ $grupo ?: 'General' }}
                <span class="ml-1 text-gray-400 font-normal">({{ $prods->count() }})</span>
            </button>
        @endforeach
    </div>
</div>
@endif

{{-- Grid de productos --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3" id="grid-productos">
    @foreach($grupos as $grupo => $productos)
        @foreach($productos as $producto)
            @php $esPorKilo = strtolower($producto->tipo ?? '') !== 'unidad'; @endphp
            <div class="producto-card bg-white rounded-2xl border border-gray-100 overflow-hidden flex flex-col transition hover:shadow-md hover:-translate-y-0.5 duration-200"
                data-grupo="{{ $grupo }}">

                {{-- Imagen --}}
                @if($producto->imagen)
                    <div class="aspect-square bg-gray-50 overflow-hidden">
                        <img src="{{ asset($producto->imagen) }}" alt="{{ $producto->des }}"
                            class="w-full h-full object-cover transition-transform duration-300 hover:scale-105"
                            loading="lazy">
                    </div>
                @else
                    <div class="aspect-square bg-gradient-to-br from-gray-50 to-red-50 flex items-center justify-center">
                        <svg class="w-10 h-10 text-red-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                @endif

                {{-- Info --}}
                <div class="p-3 flex flex-col flex-1">
                    <h3 class="text-xs sm:text-sm font-semibold text-gray-800 line-clamp-2 leading-tight">
                        {{ $producto->des }}
                    </h3>

                    @if($producto->descripcion)
                        <p class="text-xs text-gray-400 line-clamp-2 mt-1">{{ $producto->descripcion }}</p>
                    @endif

                </div>
            </div>
        @endforeach
    @endforeach
</div>

@if($grupos->isEmpty())
    <div class="text-center py-20">
        <svg class="w-14 h-14 mx-auto text-gray-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
        <p class="text-gray-400 text-sm">No hay productos disponibles.</p>
    </div>
@endif

@endsection

@push('scripts')
<script>
function filtrarGrupo(grupo) {
    document.querySelectorAll('.producto-card').forEach(card => {
        card.style.display = (grupo === '__todos__' || card.dataset.grupo === grupo) ? '' : 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        const activo = btn.dataset.grupo === grupo;
        btn.classList.toggle('bg-red-700', activo);
        btn.classList.toggle('text-white', activo);
        btn.classList.toggle('border-red-700', activo);
        btn.classList.toggle('bg-white', !activo);
        btn.classList.toggle('text-gray-600', !activo);
        btn.classList.toggle('border-gray-200', !activo);
    });
}
</script>
@endpush
