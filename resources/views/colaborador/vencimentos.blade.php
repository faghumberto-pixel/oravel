@extends('layouts.colaborador')
@section('titulo', 'Vencimentos - Oravel Colaborador')
@section('voltar', route('colab.logistica'))
@section('voltar_rotulo', 'Logística')
@section('tela', 'Vencimentos da documentação')
@section('conteudo')

    <div class="mt-4 grid grid-cols-2 gap-2 rounded-xl bg-white p-1 text-center text-sm font-bold border border-zinc-200 shadow-sm">
        <a href="{{ route('colab.logistica.vencimentos', array_filter(['q' => $busca])) }}" class="rounded-lg py-2 {{ $aba === 'atencao' ? 'bg-blue-600 text-white' : 'text-zinc-600' }}">Atenção ({{ $atencao }})</a>
        <a href="{{ route('colab.logistica.vencimentos', array_filter(['aba' => 'todos', 'q' => $busca])) }}" class="rounded-lg py-2 {{ $aba === 'todos' ? 'bg-blue-600 text-white' : 'text-zinc-600' }}">Todos ({{ $total }})</a>
    </div>

    <form method="GET" action="{{ route('colab.logistica.vencimentos') }}" class="mt-3">
        @if($aba === 'todos')<input type="hidden" name="aba" value="todos">@endif
        <input name="q" value="{{ $busca }}" placeholder="Buscar por placa, patrimônio ou nome" autocomplete="off" class="min-h-[3rem] w-full rounded-xl border border-zinc-300 bg-white px-4 text-base text-zinc-900">
    </form>

    <div class="mt-4 space-y-2">
        @forelse($lista as $v)
            <div class="rounded-xl bg-white p-3 border border-zinc-200 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-bold">{{ $v['ativo']->placa ?? $v['ativo']->name }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $v['pior'] === 'vencido' ? 'bg-red-100 text-red-600' : ($v['pior'] === 'atencao' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">{{ $v['pior'] === 'vencido' ? 'Vencido' : ($v['pior'] === 'atencao' ? 'Vence em breve' : 'Em dia') }}</span>
                </div>
                <p class="text-xs text-zinc-600">{{ $v['ativo']->name }}</p>
                @forelse($v['docs'] as $d)
                    <p class="mt-1 text-sm {{ $d['situacao'] === 'vencido' ? 'text-red-600' : ($d['situacao'] === 'atencao' ? 'text-amber-700' : 'text-zinc-700') }}">
                        {{ $d['nome'] }}: <strong>{{ $d['data']->format('d/m/Y') }}</strong>
                        <span class="text-xs">· {{ $d['dias'] < 0 ? 'venceu há '.abs($d['dias']).' '.(abs($d['dias']) === 1 ? 'dia' : 'dias') : ($d['dias'] === 0 ? 'vence hoje' : 'faltam '.$d['dias'].' '.($d['dias'] === 1 ? 'dia' : 'dias')) }}</span>
                    </p>
                @empty
                    <p class="mt-1 text-xs text-zinc-600">Nenhuma data de documento cadastrada.</p>
                @endforelse
            </div>
        @empty
            <p class="rounded-xl bg-white p-4 text-sm text-zinc-600 border border-zinc-200 shadow-sm">{{ $aba === 'atencao' ? 'Nenhum veículo com documento vencido ou perto de vencer.' : 'Nenhum veículo encontrado.' }}</p>
        @endforelse
    </div>
@endsection
