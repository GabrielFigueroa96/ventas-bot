<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — Carnicería Bot</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="w-full max-w-sm">

        <div class="text-center mb-8">
            <div class="text-5xl mb-3">🥩</div>
            <h1 class="text-2xl font-bold text-gray-800">Carnicería Bot</h1>
            <p class="text-gray-500 text-sm mt-1">Panel de administración</p>
        </div>

        <div class="bg-white rounded-2xl shadow-md p-8">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                        class="w-full border @error('email') border-red-400 @else border-gray-300 @enderror
                               rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                    @error('email')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                    <input type="password" name="password" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm
                               focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>

                <div class="flex items-center mb-6">
                    <input type="checkbox" name="remember" id="remember" class="mr-2 accent-red-600">
                    <label for="remember" class="text-sm text-gray-600">Recordarme</label>
                </div>

                <button type="submit"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg py-2.5 text-sm transition">
                    Ingresar
                </button>
            </form>
        </div>

    </div>

</body>
</html>
