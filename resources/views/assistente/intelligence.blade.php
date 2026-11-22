@extends('layouts.assistente', ['area' => 'intelligence'])

@section('content')
    @php($t = \App\Support\Assistente\Areas::tema('intelligence'))
    @auth
        @php($u = auth()->user())
        @if ($u->isSuperAdmin())
            <div class="min-h-screen flex items-center justify-center px-6 text-center text-sm text-gray-500">
                O super admin fica na Central. Entre com um usuário de cliente para usar o Intelligence.
                <form method="POST" action="{{ route('assistente.logout') }}" class="mt-4">@csrf<input type="hidden" name="area" value="intelligence"><button class="underline">Sair</button></form>
            </div>
        @elseif (filled($u->tenant_id) && Gate::forUser($u)->allows('viewAny', \App\Domain\Intelligence\Models\IntelligenceSnapshot::class))
            <livewire:assistente.intelligence-chat :fullscreen="true" />
            <form method="POST" action="{{ route('assistente.logout') }}"
                  class="fixed top-2 right-2 z-[60]" style="top: max(0.5rem, env(safe-area-inset-top));">
                @csrf
                <input type="hidden" name="area" value="intelligence">
                <button type="submit" class="text-[11px] px-2 py-1 rounded-lg bg-gray-200/80 text-gray-700">Sair</button>
            </form>
        @else
            <div class="min-h-screen flex items-center justify-center px-6 text-center text-sm text-gray-500">
                Seu usuário não tem acesso ao Intelligence. Fale com o administrador da empresa.
                <form method="POST" action="{{ route('assistente.logout') }}" class="mt-4">@csrf<input type="hidden" name="area" value="intelligence"><button class="underline">Sair</button></form>
            </div>
        @endif
    @else
        <div class="min-h-screen flex flex-col items-center justify-center px-6 text-center">
            <img src="{{ asset($t['icone']) }}" alt="Oravel" class="w-16 h-16 rounded-2xl mb-4">
            <h1 class="text-xl font-bold mb-1">{{ $t['nome'] }}</h1>
            <p class="text-sm text-gray-500 mb-6">Entre para consultar a saúde e a evolução da sua empresa.</p>
            <a href="{{ route('assistente.login', ['area' => 'intelligence']) }}" class="w-full max-w-sm text-white font-bold text-sm py-3 rounded-xl" style="background: {{ $t['cor'] }}">Entrar</a>
            <div class="w-full max-w-sm">@include('assistente._instalar')</div>
        </div>
    @endauth
@endsection
