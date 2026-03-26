@extends('tienda.layout')
@section('title', ($empresaNombre ?? $empresa->nombre_ia ?? 'Tienda') . ' — Catálogo')

@section('content')

{{-- Banner info / redes --}}
@if($empresa->bot_info || $empresa->tienda_facebook || $empresa->tienda_instagram || $empresa->tienda_tiktok)
<div class="mb-4 bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3 fade-up">
    @if($empresa->bot_info)
        <p class="text-sm text-gray-500 flex-1 leading-relaxed">{{ $empresa->bot_info }}</p>
    @endif
    @if($empresa->tienda_facebook || $empresa->tienda_instagram || $empresa->tienda_tiktok)
        <div class="flex items-center gap-2">
            @if($empresa->tienda_facebook)
                <a href="{{ $empresa->tienda_facebook }}" target="_blank" rel="noopener"
                    class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-50 text-blue-600 hover:bg-blue-100 transition shadow-sm" title="Facebook">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
            @endif
            @if($empresa->tienda_instagram)
                <a href="{{ $empresa->tienda_instagram }}" target="_blank" rel="noopener"
                    class="w-8 h-8 flex items-center justify-center rounded-full bg-pink-50 text-pink-600 hover:bg-pink-100 transition shadow-sm" title="Instagram">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                </a>
            @endif
            @if($empresa->tienda_tiktok)
                <a href="{{ $empresa->tienda_tiktok }}" target="_blank" rel="noopener"
                    class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-800 hover:bg-gray-200 transition shadow-sm" title="TikTok">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.76a4.85 4.85 0 01-1.01-.07z"/></svg>
                </a>
            @endif
        </div>
    @endif
</div>
@endif

{{-- Banner cliente logueado --}}
@if($cliente)
<a href="{{ route('tienda.mis_pedidos', ['slug' => $slug]) }}"
    class="mb-4 flex items-center gap-3 bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3 hover:border-brand-100 hover:shadow-md transition group fade-up">
    <div class="w-9 h-9 rounded-xl bg-brand-50 flex items-center justify-center flex-shrink-0 group-hover:bg-brand-100 transition">
        <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-800">Mis pedidos</p>
        <p class="text-xs text-gray-400">Ver el historial de mis compras</p>
    </div>
    <svg class="w-4 h-4 text-gray-300 group-hover:text-brand transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
    </svg>
</a>
@endif

{{-- Banner no logueado --}}
@if(!$cliente)
<div class="mb-4 bg-gradient-to-r from-brand-50 to-white border border-brand-100 rounded-2xl px-4 py-3.5 flex items-center gap-3 shadow-sm fade-up">
    <div class="w-9 h-9 rounded-xl bg-white flex items-center justify-center flex-shrink-0 shadow-sm">
        <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-800">Ver mis pedidos</p>
        <p class="text-xs text-gray-400 mt-0.5">Iniciá sesión para acceder a tu historial</p>
    </div>
    <a href="{{ route('tienda.login', ['slug' => $slug]) }}"
        class="flex-shrink-0 bg-brand hover:bg-brand-dark text-white text-xs font-semibold px-4 py-2 rounded-lg transition shadow-sm">
        Ingresar
    </a>
</div>
@endif

{{-- Filtro por grupo --}}
@if($grupos->count() > 1)
<div class="sticky top-14 z-30 bg-gray-50/95 backdrop-blur-sm -mx-4 px-4 py-2 mb-4 border-b border-gray-100">
    <div class="overflow-x-auto scrollbar-hide">
        <div class="flex gap-2 min-w-max" id="tabs-grupos">
            <button onclick="filtrarGrupo('__todos__')" data-grupo="__todos__"
                class="tab-btn px-4 py-1.5 rounded-full text-xs font-semibold border transition whitespace-nowrap bg-brand text-white border-brand shadow-sm">
                Todos
            </button>
            @foreach($grupos as $grupo => $prods)
                <button onclick="filtrarGrupo('{{ e($grupo) }}')" data-grupo="{{ $grupo }}"
                    class="tab-btn px-4 py-1.5 rounded-full text-xs font-semibold border border-gray-200 bg-white text-gray-600 hover:border-brand hover:text-brand transition whitespace-nowrap">
                    {{ $grupo ?: 'General' }}
                    <span class="ml-1 opacity-50 font-normal">{{ $prods->count() }}</span>
                </button>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Grid de productos --}}
@if($grupos->isNotEmpty())
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3" id="grid-productos">
    @foreach($grupos as $grupo => $productos)
        @foreach($productos as $i => $producto)
            @php $esPorKilo = strtolower($producto->tipo ?? '') !== 'unidad'; @endphp
            <div class="producto-card bg-white rounded-2xl border border-gray-100 overflow-hidden flex flex-col group cursor-default
                        transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 hover:border-gray-200 fade-up"
                style="animation-delay: {{ ($i % 10) * 30 }}ms"
                data-grupo="{{ $grupo }}">

                {{-- Imagen --}}
                <div class="relative overflow-hidden" style="aspect-ratio: 4/3;">
                    @if($producto->imagen)
                        <img src="{{ asset($producto->imagen) }}?v={{ $producto->imagen_updated_at ? strtotime($producto->imagen_updated_at) : 0 }}"
                            alt="{{ $producto->des }}"
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            loading="lazy">
                    @else
                        <div class="w-full h-full bg-gradient-to-br from-gray-50 via-red-50/30 to-brand-50 flex items-center justify-center">
                            <svg class="w-10 h-10 text-brand/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    @endif

                    {{-- Badge tipo --}}
                    <div class="absolute top-2 left-2">
                        @if($esPorKilo)
                            <span class="text-[10px] font-semibold bg-black/60 text-white px-1.5 py-0.5 rounded-md backdrop-blur-sm">
                                /kg
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Info --}}
                <div class="p-3 flex flex-col flex-1">
                    <h3 class="text-xs sm:text-sm font-semibold text-gray-800 line-clamp-2 leading-snug">
                        {{ $producto->des }}
                    </h3>
                    @if($producto->descripcion && $producto->descripcion !== 'sinimagen.webp')
                        <p class="text-xs text-gray-400 line-clamp-2 mt-1 leading-snug">{{ $producto->descripcion }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    @endforeach
</div>
@else
    <div class="text-center py-24 fade-up">
        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        </div>
        <p class="text-gray-500 font-medium">No hay productos disponibles</p>
        <p class="text-sm text-gray-400 mt-1">Volvé a intentarlo más tarde.</p>
    </div>
@endif

@endsection

@push('scripts')
<script>
function filtrarGrupo(grupo) {
    document.querySelectorAll('.producto-card').forEach(card => {
        const visible = grupo === '__todos__' || card.dataset.grupo === grupo;
        card.style.display = visible ? '' : 'none';
        if (visible) {
            card.classList.remove('fade-up');
            void card.offsetWidth;
            card.classList.add('fade-up');
        }
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        const activo = btn.dataset.grupo === grupo;
        btn.className = btn.className
            .replace(/bg-brand\s*|text-white\s*|border-brand\s*|shadow-sm\s*|bg-white\s*|text-gray-600\s*|border-gray-200\s*/g, '').trim();
        if (activo) {
            btn.classList.add('bg-brand', 'text-white', 'border-brand', 'shadow-sm');
        } else {
            btn.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
        }
    });
}
</script>
@endpush
