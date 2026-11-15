@extends('layouts.assistente')

@section('content')
    <div class="min-h-screen flex flex-col items-center justify-center px-6">
        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center mb-8">
                <img src="{{ asset('icon.png') }}" alt="Oravel" class="w-16 h-16 rounded-2xl mb-4">
                <h1 class="text-xl font-bold">Oravel Mobile</h1>
                <p class="text-sm text-gray-500 mt-1">Entre para usar o assistente de prospecção</p>
            </div>

            <form method="POST" action="{{ route('assistente.login') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-semibold text-gray-500 mb-1.5">E-mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="w-full bg-white dark:bg-gray-800 text-base px-3.5 py-3 rounded-xl border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none">
                    @error('email')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="block text-xs font-semibold text-gray-500 mb-1.5">Senha</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                           class="w-full bg-white dark:bg-gray-800 text-base px-3.5 py-3 rounded-xl border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none">
                    @error('password')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-sm py-3 rounded-xl">Entrar</button>
                <p class="text-center text-[11px] text-gray-500 pt-2">Você continuará conectado neste aparelho até sair.</p>
            </form>
        </div>
    </div>
@endsection
