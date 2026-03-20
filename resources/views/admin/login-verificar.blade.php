<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación — Carnicería Bot</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="w-full max-w-sm">

        <div class="text-center mb-8">
            <div class="text-5xl mb-3">🔐</div>
            <h1 class="text-2xl font-bold text-gray-800">Verificación</h1>
            <p class="text-gray-500 text-sm mt-1">Te enviamos un código por WhatsApp al teléfono del negocio</p>
        @if(session('pending_2fa_debug'))
        @php $dbg = session('pending_2fa_debug'); @endphp
        <div class="mt-2 text-xs bg-yellow-50 border border-yellow-200 rounded p-2 text-left text-yellow-800">
            📱 Enviando a: <b>{{ $dbg['telefono'] }}</b><br>
            🔑 Código: <b>{{ $dbg['codigo'] }}</b><br>
            📡 API status: <b>{{ $dbg['api_status'] ?? 'sin respuesta' }}</b><br>
            💬 Respuesta: {{ $dbg['api_body'] }}
        </div>
        @endif
        </div>

        <div class="bg-white rounded-2xl shadow-md p-8">
            <form method="POST" action="{{ route('login.verificar.post') }}">
                @csrf

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2 text-center">Código de 6 dígitos</label>
                    <input type="text" name="codigo" inputmode="numeric" pattern="[0-9]{6}"
                           maxlength="6" autofocus autocomplete="one-time-code"
                           class="w-full border @error('codigo') border-red-400 @else border-gray-300 @enderror
                                  rounded-lg px-4 py-3 text-center text-2xl tracking-widest font-mono
                                  focus:outline-none focus:ring-2 focus:ring-red-400">
                    @error('codigo')
                        <p class="text-red-500 text-xs mt-1 text-center">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg py-2.5 text-sm transition">
                    Verificar
                </button>

                <div class="mt-4 text-center">
                    <a href="{{ route('login') }}" class="text-sm text-gray-400 hover:text-red-500">
                        ← Volver al inicio
                    </a>
                </div>
            </form>
        </div>

    </div>

</body>
</html>
