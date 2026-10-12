@extends('layouts.colaborador')
@section('titulo', 'Entrar - Oravel Colaborador')
@section('conteudo')
    <div class="my-auto">
        <div class="text-3xl font-black">O<span class="text-orange-500">r</span>avel</div>
        <p class="mt-1 text-sm text-zinc-400">Entre com o seu usuário do sistema.</p>

        <form method="POST" action="{{ route('colab.login.post') }}" class="mt-8 space-y-4"
              onsubmit="if (this.dataset.enviando) { return false; } this.dataset.enviando = '1'; const b = this.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Entrando…';">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase text-zinc-400" for="email">E-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus
                       class="min-h-[3rem] w-full rounded-xl border border-zinc-700 bg-zinc-900 px-4 text-base text-white">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase text-zinc-400" for="password">Senha</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="min-h-[3rem] w-full rounded-xl border border-zinc-700 bg-zinc-900 px-4 text-base text-white">
            </div>
            @error('email')
                <p class="rounded-lg bg-red-500/15 px-3 py-2 text-sm font-medium text-red-400">{{ $message }}</p>
            @enderror
            <button type="submit" class="min-h-[3rem] w-full rounded-xl bg-orange-500 text-base font-bold text-white active:bg-orange-600">Entrar</button>
        </form>
    </div>
@endsection
