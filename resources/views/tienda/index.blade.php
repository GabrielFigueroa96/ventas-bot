@extends('tienda.layout')
@section('title', ($empresaNombre ?? $empresa->nombre_ia ?? 'Tienda') . ' — Catálogo')

@section('content')

{{-- Info del negocio (banner si tiene datos) --}}
@if($empresa->bot_info || $empresa->tienda_facebook || $empresa->tienda_instagram || $empresa->tienda_tiktok)
<div class="mb-5 bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex flex-col sm:flex-row sm:items-center gap-3">
    @if($empresa->bot_info)
        <p class="text-sm text-gray-500 flex-1 line-clamp-2">{{ $empresa->bot_info }}</p>
    @endif
    @if($empresa->tienda_facebook || $empresa->tienda_instagram || $empresa->tienda_tiktok)
        <div class="flex items-center gap-3 flex-shrink-0">
            @if($empresa->tienda_facebook)
                <a href="{{ $empresa->tienda_facebook }}" target="_blank" rel="noopener"
                    class="text-blue-600 hover:text-blue-700 transition" title="Facebook">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                </a>
            @endif
            @if($empresa->tienda_instagram)
                <a href="{{ $empresa->tienda_instagram }}" target="_blank" rel="noopener"
                    class="text-pink-500 hover:text-pink-600 transition" title="Instagram">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                </a>
            @endif
            @if($empresa->tienda_tiktok)
                <a href="{{ $empresa->tienda_tiktok }}" target="_blank" rel="noopener"
                    class="text-gray-800 hover:text-black transition" title="TikTok">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.76a4.85 4.85 0 01-1.01-.07z"/>
                    </svg>
                </a>
            @endif
        </div>
    @endif
</div>
@endif

{{-- Aviso pedido mínimo --}}
@if($pedidoMinimo > 0)
<div class="mb-4 flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-800 text-xs px-4 py-2.5 rounded-xl">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    Pedido mínimo: <strong>${{ number_format($pedidoMinimo, 2, ',', '.') }}</strong>
</div>
@endif

{{-- Sugeridos --}}
@if($sugeridos->isNotEmpty())
<div class="mb-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
        </svg>
        Tus favoritos
    </h2>
    <div class="flex gap-3 overflow-x-auto pb-2 -mx-1 px-1">
        @foreach($sugeridos as $producto)
            @php
                $esPorKilo = strtolower($producto->tipo ?? '') !== 'unidad';
                $itemEnCarrito = null;
                foreach (($carrito?->items ?? []) as $item) {
                    if ((string) $item['cod'] === (string) $producto->cod) {
                        $itemEnCarrito = $item; break;
                    }
                }
            @endphp
            <div class="flex-shrink-0 w-36 bg-white rounded-xl shadow-sm border border-red-100 overflow-hidden flex flex-col">
                @if($producto->imagen)
                    <div class="aspect-square bg-gray-100 overflow-hidden">
                        <img src="{{ asset($producto->imagen) }}" alt="{{ $producto->des }}"
                            class="w-full h-full object-cover" loading="lazy">
                    </div>
                @else
                    <div class="aspect-square bg-gradient-to-br from-red-50 to-red-100 flex items-center justify-center">
                        <svg class="w-10 h-10 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                @endif
                <div class="p-2 flex flex-col flex-1">
                    <p class="text-xs font-semibold text-gray-700 line-clamp-2 leading-tight mb-1">{{ $producto->des }}</p>
                    @if($mostrarPrecios)
                        <p class="text-sm font-bold text-red-700 mb-1.5">
                            ${{ number_format($producto->precio, 2, ',', '.') }}
                            @if($esPorKilo)<span class="text-xs font-normal text-gray-400">/kg</span>@endif
                        </p>
                    @endif
                    <div class="flex gap-1 mt-auto">
                        <input type="number"
                            id="cant-sug-{{ $producto->cod }}"
                            value="{{ $itemEnCarrito['cantidad'] ?? '' }}"
                            placeholder="{{ $esPorKilo ? 'kg' : 'u.' }}"
                            min="{{ $esPorKilo ? '0.5' : '1' }}"
                            step="{{ $esPorKilo ? '0.5' : '1' }}"
                            @if(!$esPorKilo) oninput="this.value=Math.round(this.value)" @endif
                            class="w-full border border-gray-200 rounded-lg px-1.5 py-1 text-xs text-center focus:outline-none focus:ring-2 focus:ring-red-300">
                        <button onclick="agregarItem('{{ $producto->cod }}', '{{ e($producto->des) }}', {{ $producto->precio }}, '{{ $producto->tipo }}', 'sug-')"
                            class="flex-shrink-0 bg-red-700 hover:bg-red-800 text-white rounded-lg p-1 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

<div class="flex gap-6">

    {{-- Catálogo --}}
    <div class="flex-1 min-w-0">

        {{-- Filtro por grupo --}}
        @if($grupos->count() > 1)
        <div class="mb-5 overflow-x-auto pb-1">
            <div class="flex gap-2 min-w-max" id="tabs-grupos">
                <button onclick="filtrarGrupo('__todos__')" data-grupo="__todos__"
                    class="tab-btn px-4 py-2 rounded-full text-sm font-medium border border-gray-200 bg-red-700 text-white transition whitespace-nowrap">
                    Todos
                </button>
                @foreach($grupos as $grupo => $prods)
                    <button onclick="filtrarGrupo('{{ e($grupo) }}')" data-grupo="{{ $grupo }}"
                        class="tab-btn px-4 py-2 rounded-full text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-red-50 transition whitespace-nowrap">
                        {{ $grupo ?: 'General' }}
                    </button>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Aviso ocultar precios --}}
        @if(!$mostrarPrecios)
        <div class="mb-4 flex items-center gap-2 bg-gray-50 border border-gray-200 text-gray-500 text-xs px-4 py-2.5 rounded-xl">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
            </svg>
            <span>
                <a href="{{ route('tienda.login', ['slug' => $slug]) }}" class="text-red-600 font-medium hover:underline">Iniciá sesión</a>
                para ver los precios.
            </span>
        </div>
        @endif

        {{-- Grid de productos --}}
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 md:gap-4" id="grid-productos">
            @foreach($grupos as $grupo => $productos)
                @foreach($productos as $producto)
                    @php
                        $esPorKilo = strtolower($producto->tipo ?? '') !== 'unidad';
                        $itemEnCarrito = null;
                        if ($carrito) {
                            foreach (($carrito->items ?? []) as $item) {
                                if ((string) $item['cod'] === (string) $producto->cod) {
                                    $itemEnCarrito = $item; break;
                                }
                            }
                        }
                        $cantActual = $itemEnCarrito['cantidad'] ?? $itemEnCarrito['cant'] ?? '';
                    @endphp
                    <div class="producto-card bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100 flex flex-col transition hover:shadow-md"
                        data-grupo="{{ $grupo }}">

                        {{-- Imagen del producto --}}
                        @if($producto->imagen)
                            <div class="aspect-square bg-gray-100 overflow-hidden">
                                <img src="{{ asset($producto->imagen) }}"
                                    alt="{{ $producto->des }}"
                                    class="w-full h-full object-cover transition hover:scale-105"
                                    loading="lazy">
                            </div>
                        @else
                            <div class="aspect-square bg-gradient-to-br from-red-50 to-red-100 flex items-center justify-center">
                                <svg class="w-12 h-12 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        @endif

                        {{-- Info --}}
                        <div class="p-3 flex flex-col flex-1">
                            <h3 class="text-sm font-semibold text-gray-800 line-clamp-2 leading-tight mb-1">
                                {{ $producto->des }}
                            </h3>

                            @if($producto->descripcion)
                                <p class="text-xs text-gray-400 line-clamp-2 mb-2">{{ $producto->descripcion }}</p>
                            @endif

                            <div class="mt-auto space-y-2">
                                @if($mostrarPrecios)
                                    <p class="text-base font-bold text-red-700">
                                        ${{ number_format($producto->precio, 2, ',', '.') }}
                                        @if($esPorKilo)<span class="text-xs font-normal text-gray-400">/ kg</span>@endif
                                    </p>
                                @else
                                    <p class="text-xs text-gray-400 italic">Iniciá sesión para ver precios</p>
                                @endif

                                {{-- Control cantidad --}}
                                <div class="flex items-center gap-1.5">
                                    <input type="number"
                                        id="cant-{{ $producto->cod }}"
                                        value="{{ $cantActual }}"
                                        placeholder="{{ $esPorKilo ? 'kg' : 'u.' }}"
                                        min="{{ $esPorKilo ? '0.5' : '1' }}"
                                        step="{{ $esPorKilo ? '0.5' : '1' }}"
                                        @if(!$esPorKilo) oninput="this.value=this.value.replace(/[^0-9]/g,'')" @endif
                                        class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-red-300">
                                    <button
                                        onclick="agregarItem('{{ $producto->cod }}', '{{ e($producto->des) }}', {{ $producto->precio }}, '{{ $producto->tipo }}')"
                                        class="flex-shrink-0 bg-red-700 hover:bg-red-800 active:bg-red-900 text-white rounded-lg p-1.5 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                </div>

                                @if($itemEnCarrito)
                                    <p class="text-xs text-green-600 font-medium" id="en-carrito-{{ $producto->cod }}">
                                        ✓ En carrito: {{ $itemEnCarrito['cantidad'] ?? $itemEnCarrito['cant'] ?? 0 }}
                                        {{ $esPorKilo ? 'kg' : 'u.' }}
                                    </p>
                                @else
                                    <p class="text-xs text-green-600 font-medium hidden" id="en-carrito-{{ $producto->cod }}"></p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>

        @if($grupos->isEmpty())
            <div class="text-center py-20 text-gray-400">
                <svg class="w-16 h-16 mx-auto mb-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <p>No hay productos disponibles.</p>
            </div>
        @endif
    </div>

    {{-- Sidebar carrito (desktop) --}}
    <aside class="hidden lg:block w-72 flex-shrink-0">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 sticky top-20" id="sidebar-carrito">
            <div class="p-4 border-b border-gray-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <h2 class="font-semibold text-gray-800">Mi carrito</h2>
            </div>

            <div id="sidebar-items" class="p-4 space-y-3 max-h-80 overflow-y-auto">
                @forelse(($carritoData['items'] ?? []) as $item)
                    <div class="flex items-start justify-between gap-2 text-sm" id="sidebar-item-{{ $item['cod'] }}">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-700 truncate">{{ $item['des'] }}</p>
                            <p class="text-gray-400 text-xs">
                                {{ $item['cantidad'] }}
                                {{ strtolower($item['tipo'] ?? '') === 'unidad' ? 'u.' : 'kg' }}
                                × ${{ number_format($item['precio'], 2, ',', '.') }}
                            </p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="font-semibold text-gray-800">${{ number_format($item['neto'], 2, ',', '.') }}</p>
                            <button onclick="quitarItem('{{ $item['cod'] }}')"
                                class="text-xs text-red-500 hover:text-red-700 transition">Quitar</button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 text-center py-6" id="carrito-vacio">Tu carrito está vacío</p>
                @endforelse
            </div>

            <div class="p-4 border-t border-gray-100 space-y-3">
                <div class="flex justify-between font-semibold text-gray-800">
                    <span>Total</span>
                    <span id="sidebar-total">${{ number_format($carritoData['total'] ?? 0, 2, ',', '.') }}</span>
                </div>

                @if($pedidoMinimo > 0)
                <p class="text-xs text-gray-400" id="sidebar-minimo">
                    Mínimo: ${{ number_format($pedidoMinimo, 2, ',', '.') }}
                </p>
                @endif

                <a href="{{ route('tienda.checkout', ['slug' => $slug]) }}"
                    id="btn-checkout"
                    class="{{ ($carritoData['count'] ?? 0) > 0 ? '' : 'hidden' }} block w-full bg-red-700 hover:bg-red-800 text-white text-center text-sm font-semibold rounded-xl py-2.5 transition">
                    Ir al checkout
                </a>
            </div>
        </div>
    </aside>

</div>
@endsection

@push('scripts')
<script>
const SLUG        = '{{ $slug }}';
const CSRF        = document.querySelector('meta[name="csrf-token"]').content;
const LOGIN_URL   = '{{ route('tienda.login', ['slug' => $slug]) }}';
const URL_AGREGAR = '{{ route('tienda.carrito.agregar', ['slug' => $slug]) }}';
const URL_QUITAR  = '{{ route('tienda.carrito.quitar', ['slug' => $slug]) }}';
const PEDIDO_MIN  = {{ $pedidoMinimo }};

function filtrarGrupo(grupo) {
    document.querySelectorAll('.producto-card').forEach(card => {
        card.style.display = (grupo === '__todos__' || card.dataset.grupo === grupo) ? '' : 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        const activo = btn.dataset.grupo === grupo;
        btn.classList.toggle('bg-red-700', activo);
        btn.classList.toggle('text-white', activo);
        btn.classList.toggle('bg-white', !activo);
        btn.classList.toggle('text-gray-700', !activo);
    });
}

async function agregarItem(cod, des, precio, tipo, prefix = '') {
    const inputEl = document.getElementById((prefix ? prefix : '') + 'cant-' + cod);
    if (!inputEl) return;

    const esPorKilo = tipo.toLowerCase() !== 'unidad';
    let cantidad = esPorKilo ? parseFloat(inputEl.value) : parseInt(inputEl.value, 10);

    if (!cantidad || cantidad <= 0) {
        inputEl.focus();
        inputEl.classList.add('ring-2', 'ring-red-400');
        setTimeout(() => inputEl.classList.remove('ring-2', 'ring-red-400'), 1500);
        return;
    }

    // Redondear peso a múltiplos de 0.5
    if (esPorKilo) {
        cantidad = Math.round(cantidad * 2) / 2;
        inputEl.value = cantidad;
    }

    try {
        const res = await fetch(URL_AGREGAR, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ cod, cantidad }),
        });

        if (res.status === 401) {
            const data = await res.json();
            window.location.href = data.redirect || LOGIN_URL;
            return;
        }
        if (!res.ok) {
            const err = await res.json();
            alert(err.error || 'Error al agregar.');
            return;
        }

        const data = await res.json();
        actualizarUICarrito(data);

        const indicador = document.getElementById('en-carrito-' + cod);
        if (indicador) {
            indicador.textContent = '✓ En carrito: ' + cantidad + (esPorKilo ? ' kg' : ' u.');
            indicador.classList.remove('hidden');
        }
    } catch (e) {
        alert('Hubo un error. Intentá de nuevo.');
    }
}

async function quitarItem(cod) {
    try {
        const res = await fetch(URL_QUITAR, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ cod }),
        });
        if (!res.ok) throw new Error();
        const data = await res.json();
        actualizarUICarrito(data);

        const indicador = document.getElementById('en-carrito-' + cod);
        if (indicador) { indicador.textContent = ''; indicador.classList.add('hidden'); }

        const sidebarItem = document.getElementById('sidebar-item-' + cod);
        if (sidebarItem) sidebarItem.remove();
    } catch (e) {
        alert('Hubo un error.');
    }
}

function actualizarUICarrito(data) {
    const count = data.count ?? 0;
    const total = data.total ?? 0;

    const badge = document.getElementById('carrito-badge');
    if (badge) {
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
    }

    const sidebarTotal = document.getElementById('sidebar-total');
    if (sidebarTotal) sidebarTotal.textContent = '$' + formatNum(total);

    const btnCheckout = document.getElementById('btn-checkout');
    if (btnCheckout) btnCheckout.classList.toggle('hidden', count === 0);

    const vacioParagraph = document.getElementById('carrito-vacio');
    if (vacioParagraph) vacioParagraph.style.display = count > 0 ? 'none' : '';

    const bar = document.getElementById('carrito-bar');
    if (bar) {
        bar.classList.toggle('hidden', count === 0);
        const barCount = document.getElementById('bar-count');
        const barTotal = document.getElementById('bar-total');
        if (barCount) barCount.textContent = count + ' producto(s)';
        if (barTotal) barTotal.textContent = '$' + formatNum(total);
    }

    const sidebarItemsEl = document.getElementById('sidebar-items');
    if (sidebarItemsEl && data.items) {
        sidebarItemsEl.innerHTML = '';
        if (data.items.length === 0) {
            sidebarItemsEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-6" id="carrito-vacio">Tu carrito está vacío</p>';
        } else {
            data.items.forEach(item => {
                const esPorKilo = (item.tipo || '').toLowerCase() !== 'unidad';
                sidebarItemsEl.innerHTML += `
                <div class="flex items-start justify-between gap-2 text-sm" id="sidebar-item-${item.cod}">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-700 truncate">${item.des}</p>
                        <p class="text-gray-400 text-xs">${item.cantidad} ${esPorKilo ? 'kg' : 'u.'} × $${formatNum(item.precio)}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="font-semibold text-gray-800">$${formatNum(item.neto)}</p>
                        <button onclick="quitarItem('${item.cod}')" class="text-xs text-red-500 hover:text-red-700 transition">Quitar</button>
                    </div>
                </div>`;
            });
        }
    }
}

function formatNum(n) {
    return parseFloat(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
@endpush
