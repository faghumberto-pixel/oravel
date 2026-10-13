@extends('layouts.colaborador')
@section('titulo', 'Vencimentos - Oravel Colaborador')
@section('conteudo')
    <a href="{{ route('colab.logistica') }}" class="text-sm font-semibold text-zinc-400">← Logística</a>
    <h1 class="mt-3 text-xl font-bold">Vencimentos da documentação</h1>

    <div class="mt-4 grid grid-cols-2 gap-2 rounded-xl bg-zinc-900 p-1 text-center text-sm font-bold">
        <a href="{{ route('colab.logistica.vencimentos', array_filter(['q' => $busca])) }}" class="rounded-lg py-2 {{ $aba === 'atencao' ? 'bg-orange-500 text-white' : 'text-zinc-400' }}">Atenção ({{ $atencao }})</a>
        <a href="{{ route('colab.logistica.vencimentos', array_filter(['aba' => 'todos', 'q' => $busca])) }}" class="rounded-lg py-2 {{ $aba === 'todos' ? 'bg-orange-500 text-white' : 'text-zinc-400' }}">Todos ({{ $total }})</a>
    </div>

    <form method="GET" action="{{ route('colab.logistica.vencimentos') }}" class="mt-3">
        @if($aba === 'todos')<input type="hidden" name="aba" value="todos">@endif
        <input name="q" value="{{ $busca }}" placeholder="Buscar por placa, patrimônio ou nome" autocomplete="off" class="min-h-[3rem] w-full rounded-xl border border-zinc-700 bg-zinc-900 px-4 text-base text-white">
    </form>

    <div class="mt-4 space-y-2">
        @forelse($lista as $v)
            <div class="rounded-xl bg-zinc-900 p-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-bold">{{ $v['ativo']->placa ?? $v['ativo']->name }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $v['pior'] === 'vencido' ? 'bg-red-500/15 text-red-400' : ($v['pior'] === 'atencao' ? 'bg-amber-500/15 text-amber-400' : 'bg-emerald-500/15 text-emerald-400') }}">{{ $v['pior'] === 'vencido' ? 'Vencido' : ($v['pior'] === 'atencao' ? 'Vence em breve' : 'Em dia') }}</span>
                </div>
                <p class="text-xs text-zinc-400">{{ $v['ativo']->name }}</p>
                @forelse($v['docs'] as $d)
                    <p class="mt-1 text-sm {{ $d['situacao'] === 'vencido' ? 'text-red-400' : ($d['situacao'] === 'atencao' ? 'text-amber-400' : 'text-zinc-300') }}">
                        {{ $d['nome'] }}: <strong>{{ $d['data']->format('d/m/Y') }}</strong>
                        <span class="text-xs">· {{ $d['dias'] < 0 ? 'venceu há '.abs($d['dias']).' '.(abs($d['dias']) === 1 ? 'dia' : 'dias') : ($d['dias'] === 0 ? 'vence hoje' : 'faltam '.$d['dias'].' '.($d['dias'] === 1 ? 'dia' : 'dias')) }}</span>
                    </p>
                @empty
                    <p class="mt-1 text-xs text-zinc-500">Nenhuma data de documento cadastrada.</p>
                @endforelse
            </div>
        @empty
            <p class="rounded-xl bg-zinc-900 p-4 text-sm text-zinc-400">{{ $aba === 'atencao' ? 'Nenhum veículo com documento vencido ou perto de vencer.' : 'Nenhum veículo encontrado.' }}</p>
        @endforelse
    </div>
@endsection
