@extends('layouts.assistente', ['area' => request('area') === 'suprimentos' ? 'suprimentos' : 'prospects'])

@section('content')
    @php($t = \App\Support\Assistente\Areas::tema(request('area') === 'suprimentos' ? 'suprimentos' : 'prospects'))
    <div class="min-h-screen flex flex-col items-center justify-center px-6">
        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center mb-8">
                <img src="{{ asset($t['icone']) }}" alt="Oravel" class="w-16 h-16 rounded-2xl mb-4">
                <h1 class="text-xl font-bold">{{ $t['nome'] }}</h1>
                <p class="text-sm text-gray-500 mt-1">Entre para usar o assistente de prospecção</p>
            </div>

            <form method="POST" action="{{ route('assistente.login') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="area" value="{{ request('area', old('area')) }}">
                <div>
                    <label for="email" class="block text-xs font-semibold text-gray-500 mb-1.5">E-mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="w-full bg-white text-gray-900 placeholder-gray-400 text-base px-3.5 py-3 rounded-xl border border-gray-300 focus:ring-2 outline-none">
                    @error('email')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="block text-xs font-semibold text-gray-500 mb-1.5">Senha</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                           class="w-full bg-white text-gray-900 placeholder-gray-400 text-base px-3.5 py-3 rounded-xl border border-gray-300 focus:ring-2 outline-none">
                    @error('password')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full text-white font-bold text-sm py-3 rounded-xl" style="background: {{ $t['cor'] }}">Entrar</button>
                <p class="text-center text-[11px] text-gray-500 pt-2">Você continuará conectado neste aparelho até sair.</p>
            </form>

            @include('assistente._instalar')
        </div>
    </div>

<script>
    // Voltar pela seta do navegador mostra a tela de login guardada, com o codigo de
    // seguranca ja vencido (causa do erro 419): recarrega pra pegar um novo.
    window.addEventListener('pageshow', function (e) { if (e.persisted) location.reload(); });
</script>
@endsection
