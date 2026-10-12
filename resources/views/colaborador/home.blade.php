@extends('layouts.colaborador')
@section('conteudo')
    <header class="flex items-center justify-between">
        <div>
            <div class="text-xl font-black">O<span class="text-orange-500">r</span>avel</div>
            <p class="text-sm text-zinc-400">Olá, {{ auth()->user()->name }}</p>
        </div>
        <form method="POST" action="{{ route('colab.logout') }}">
            @csrf
            <button type="submit" class="rounded-lg border border-zinc-700 px-3 py-2 text-xs font-bold text-zinc-300 active:bg-zinc-800">Sair</button>
        </form>
    </header>

    @forelse($grupos as $grupo => $modulos)
        <section class="mt-6">
            <h2 class="mb-2 text-xs font-bold uppercase tracking-wide text-zinc-500">{{ $grupo }}</h2>
            <div class="space-y-3">
                @foreach($modulos as $m)
                    <a href="{{ route($m['rota']) }}" class="flex items-center gap-4 rounded-2xl bg-zinc-900 p-4 active:bg-zinc-800">
                        <span class="text-3xl">{{ $m['icone'] }}</span>
                        <span>
                            <span class="block text-base font-bold">{{ $m['titulo'] }}</span>
                            <span class="block text-xs text-zinc-400">{{ $m['descricao'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <p class="mt-10 rounded-xl bg-zinc-900 p-4 text-sm text-zinc-400">Nenhuma função disponível para o seu perfil. Fale com o administrador da sua empresa.</p>
    @endforelse
@endsection
