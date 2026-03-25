@extends('admin.layout')
@section('title', 'Mi cuenta')

@section('content')
<div class="max-w-md mx-auto space-y-5">
    <h1 class="text-xl font-bold text-gray-800">Mi cuenta</h1>

    @if(session('ok'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
            {{ session('ok') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
        <div>
            <h2 class="text-sm font-semibold text-gray-700 mb-0.5">Cambiar contraseña</h2>
            <p class="text-xs text-gray-400">La nueva contraseña debe tener al menos 8 caracteres.</p>
        </div>

        <form method="POST" action="{{ route('admin.cuenta.password') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Contraseña actual</label>
                <input type="password" name="password_actual" autocomplete="current-password"
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300
                           {{ $errors->has('password_actual') ? 'border-red-400' : 'border-gray-300' }}">
                @error('password_actual')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Nueva contraseña</label>
                <input type="password" name="password_nuevo" autocomplete="new-password"
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300
                           {{ $errors->has('password_nuevo') ? 'border-red-400' : 'border-gray-300' }}">
                @error('password_nuevo')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Repetir nueva contraseña</label>
                <input type="password" name="password_nuevo_confirmation" autocomplete="new-password"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
            </div>

            <div class="flex justify-end pt-1">
                <button type="submit"
                    class="bg-red-700 hover:bg-red-800 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
