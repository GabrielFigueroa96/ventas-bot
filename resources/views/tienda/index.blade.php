@extends('tienda.layout')

@section('title', ($empresa->nombre_ia ?? 'Tienda') . ' — Catálogo')

@section('content')
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
                                    $itemEnCarrito = $item;
                                    break;
                                }
                            }
                        }
                        $cantActual = $itemEnCarrito['cantidad'] ?? '';
                    @endphp
                    <div class="producto-card bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100 flex flex-col"
                        data-grupo="{{ $grupo }}">

                        {{-- Imagen del producto --}}
                        @if($producto->imagen)
                            <div class="aspect-square bg-gray-100 overflow-hidden">
                                <img src="{{ asset($producto->imagen) }}"
                                    alt="{{ $producto->des }}"
                                    class="w-full h-full object-cover"
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
                                <p class="text-base font-bold text-red-700">
                                    ${{ number_format($producto->precio, 2, ',', '.') }}
                                    @if($esPorKilo)<span class="text-xs font-normal text-gray-400">/ kg</span>@endif
                                </p>

                                {{-- Control cantidad + agregar --}}
                                <div class="flex items-center gap-1.5">
                                    <input type="{{ $esPorKilo ? 'number' : 'number' }}"
                                        id="cant-{{ $producto->cod }}"
                                        value="{{ $cantActual }}"
                                        placeholder="{{ $esPorKilo ? 'kg' : 'u.' }}"
                                        min="{{ $esPorKilo ? '0.1' : '1' }}"
                                        step="{{ $esPorKilo ? '0.1' : '1' }}"
                                        class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-red-300">
                                    <button
                                        onclick="agregarItem('{{ $producto->cod }}', '{{ e($producto->des) }}', {{ $producto->precio }}, '{{ $producto->tipo }}')"
                                        class="flex-shrink-0 bg-red-700 hover:bg-red-800 text-white rounded-lg p-1.5 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                </div>

                                @if($itemEnCarrito)
                                    <p class="text-xs text-green-600 font-medium" id="en-carrito-{{ $producto->cod }}">
                                        En carrito: {{ $itemEnCarrito['cantidad'] }}
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
            <div class="p-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Mi carrito
                </h2>
            </div>

            <div id="sidebar-items" class="p-4 space-y-3 max-h-96 overflow-y-auto">
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
                    <p class="text-sm text-gray-400 text-center py-4" id="carrito-vacio">
                        Tu carrito está vacío
                    </p>
                @endforelse
            </div>

            <div class="p-4 border-t border-gray-100 space-y-3">
                <div class="flex justify-between font-semibold text-gray-800">
                    <span>Total</span>
                    <span id="sidebar-total">${{ number_format($carritoData['total'] ?? 0, 2, ',', '.') }}</span>
                </div>

                @if(($carritoData['count'] ?? 0) > 0)
                <a href="{{ route('tienda.checkout', ['slug' => $slug]) }}"
                    id="btn-checkout"
                    class="block w-full bg-red-700 hover:bg-red-800 text-white text-center text-sm font-semibold rounded-xl py-2.5 transition">
                    Ir al checkout
                </a>
                @else
                <a href="{{ route('tienda.checkout', ['slug' => $slug]) }}"
                    id="btn-checkout"
                    class="hidden block w-full bg-red-700 text-white text-center text-sm font-semibold rounded-xl py-2.5">
                    Ir al checkout
                </a>
                @endif
            </div>
        </div>
    </aside>

</div>
@endsection

@push('scripts')
<script>
const SLUG = '{{ $slug }}';
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const LOGIN_URL = '{{ route('tienda.login', ['slug' => $slug]) }}';
const CHECKOUT_URL = '{{ route('tienda.checkout', ['slug' => $slug]) }}';
const URL_AGREGAR = '{{ route('tienda.carrito.agregar', ['slug' => $slug]) }}';
const URL_QUITAR  = '{{ route('tienda.carrito.quitar', ['slug' => $slug]) }}';

function filtrarGrupo(grupo) {
    document.querySelectorAll('.producto-card').forEach(card => {
        if (grupo === '__todos__' || card.dataset.grupo === grupo) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });

    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.dataset.grupo === grupo) {
            btn.classList.add('bg-red-700', 'text-white');
            btn.classList.remove('bg-white', 'text-gray-700');
        } else {
            btn.classList.remove('bg-red-700', 'text-white');
            btn.classList.add('bg-white', 'text-gray-700');
        }
    });
}

async function agregarItem(cod, des, precio, tipo) {
    const inputEl = document.getElementById('cant-' + cod);
    const cantidad = parseFloat(inputEl.value);

    if (!cantidad || cantidad <= 0) {
        inputEl.focus();
        inputEl.classList.add('ring-2', 'ring-red-400');
        setTimeout(() => inputEl.classList.remove('ring-2', 'ring-red-400'), 1500);
        return;
    }

    try {
        const res = await fetch(URL_AGREGAR, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ cod, cantidad }),
        });

        if (res.status === 401) {
            const data = await res.json();
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.href = LOGIN_URL;
            }
            return;
        }

        if (!res.ok) throw new Error('Error al agregar.');

        const data = await res.json();
        actualizarUICarrito(data);

        // Mostrar indicador en card
        const esPorKilo = tipo.toLowerCase() !== 'unidad';
        const indicador = document.getElementById('en-carrito-' + cod);
        if (indicador) {
            indicador.textContent = 'En carrito: ' + cantidad + (esPorKilo ? ' kg' : ' u.');
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
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ cod }),
        });

        if (!res.ok) throw new Error('Error.');
        const data = await res.json();
        actualizarUICarrito(data);

        // Ocultar indicador
        const indicador = document.getElementById('en-carrito-' + cod);
        if (indicador) {
            indicador.textContent = '';
            indicador.classList.add('hidden');
        }

        // Quitar del sidebar
        const sidebarItem = document.getElementById('sidebar-item-' + cod);
        if (sidebarItem) sidebarItem.remove();

    } catch (e) {
        alert('Hubo un error.');
    }
}

function actualizarUICarrito(data) {
    const count = data.count ?? 0;
    const total = data.total ?? 0;

    // Badge header
    const badge = document.getElementById('carrito-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    // Sidebar total
    const sidebarTotal = document.getElementById('sidebar-total');
    if (sidebarTotal) sidebarTotal.textContent = '$' + formatNum(total);

    // Sidebar checkout btn
    const btnCheckout = document.getElementById('btn-checkout');
    if (btnCheckout) {
        if (count > 0) {
            btnCheckout.classList.remove('hidden');
        } else {
            btnCheckout.classList.add('hidden');
        }
    }

    // Vacío msg
    const vacioParagraph = document.getElementById('carrito-vacio');
    if (vacioParagraph) {
        vacioParagraph.style.display = count > 0 ? 'none' : '';
    }

    // Bottom bar
    const bar = document.getElementById('carrito-bar');
    if (bar) {
        if (count > 0) {
            bar.classList.remove('hidden');
            const barCount = document.getElementById('bar-count');
            const barTotal = document.getElementById('bar-total');
            if (barCount) barCount.textContent = count + ' producto(s)';
            if (barTotal) barTotal.textContent = '$' + formatNum(total);
        } else {
            bar.classList.add('hidden');
        }
    }

    // Rebuild sidebar items
    const sidebarItemsEl = document.getElementById('sidebar-items');
    if (sidebarItemsEl && data.items) {
        sidebarItemsEl.innerHTML = '';
        if (data.items.length === 0) {
            sidebarItemsEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-4" id="carrito-vacio">Tu carrito está vacío</p>';
        } else {
            data.items.forEach(item => {
                const esPorKilo = (item.tipo || '').toLowerCase() !== 'unidad';
                sidebarItemsEl.innerHTML += `
                <div class="flex items-start justify-between gap-2 text-sm" id="sidebar-item-${item.cod}">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-700 truncate">${item.des}</p>
                        <p class="text-gray-400 text-xs">
                            ${item.cantidad} ${esPorKilo ? 'kg' : 'u.'} × $${formatNum(item.precio)}
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="font-semibold text-gray-800">$${formatNum(item.neto)}</p>
                        <button onclick="quitarItem('${item.cod}')"
                            class="text-xs text-red-500 hover:text-red-700 transition">Quitar</button>
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
