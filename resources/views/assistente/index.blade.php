@extends('layouts.assistente', ['area' => 'prospects'])

@section('content')
    @php($t = \App\Support\Assistente\Areas::tema('prospects'))
    @auth
        @if (auth()->user()->isSuperAdmin())
            <livewire:prospect-chat :fullscreen="true" />
            <form method="POST" action="{{ route('assistente.logout') }}"
                  class="fixed top-2 right-2 z-[60]" style="top: max(0.5rem, env(safe-area-inset-top));">
                @csrf
                <button type="submit" class="text-[11px] px-2 py-1 rounded-lg bg-gray-200/80 dark:bg-gray-700/80 text-gray-700 dark:text-gray-200">Sair</button>
            </form>
        @else
            <div class="min-h-screen flex items-center justify-center px-6 text-center text-sm text-gray-500">
                Acesso restrito.
            </div>
        @endif
    @else
        <div class="min-h-screen flex flex-col items-center justify-center px-6 text-center">
            <img src="{{ asset($t['icone']) }}" alt="Oravel" class="w-16 h-16 rounded-2xl mb-4">
            <h1 class="text-xl font-bold mb-1">Oravel Mobile</h1>
            <p class="text-sm text-gray-500 mb-6">Entre para usar o assistente de prospecção.</p>
            <a href="{{ route('assistente.login') }}" class="w-full max-w-sm text-white font-bold text-sm py-3 rounded-xl" style="background: {{ $t['cor'] }}">Entrar</a>
            <div class="w-full max-w-sm">@include('assistente._instalar')</div>
        </div>
    @endauth
@endsection
