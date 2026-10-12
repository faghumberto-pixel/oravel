@extends('layouts.colaborador')
@section('titulo', 'OS - '.$ativo->name.' - Oravel Colaborador')
@section('conteudo')
    <a href="{{ route('colab.os.buscar') }}" class="text-sm font-semibold text-zinc-400">← Buscar outro equipamento</a>

    <div class="mt-3 rounded-2xl bg-zinc-900 p-4">
        <h1 class="text-xl font-extrabold leading-tight">{{ $ativo->name }}</h1>
        <p class="mt-1 text-sm text-zinc-400">PAT: {{ $ativo->patrimonio ?? '—' }}@if($ativo->placa) · Placa: {{ $ativo->placa }}@endif · TAG: {{ $ativo->tag ?? '—' }}</p>
        <div class="mt-3 flex items-center justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wide text-zinc-500">Situação</span>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ \App\Support\Colaborador\SituacaoOs::classes($situacao['cor']) }}">{{ $situacao['rotulo'] }}</span>
        </div>
        <a href="{{ route('colab.dossie', ['assetId' => $ativo->id]) }}" class="mt-3 block text-xs font-bold text-orange-400">Ver dossiê do ativo →</a>
    </div>

    @php
        $grupos = ['Em aberto' => $abertas, 'Concluídas recentemente' => $fechadas];
    @endphp
    @foreach($grupos as $titulo => $lista)
        @if($lista->isNotEmpty())
            <h2 class="mb-2 mt-5 text-xs font-bold uppercase tracking-wide text-zinc-500">{{ $titulo }} ({{ $lista->count() }})</h2>
            <div class="space-y-2">
                @foreach($lista as $os)
                    <a href="{{ route('colab.os', ['id' => $os['id']]) }}" class="block rounded-xl bg-zinc-900 p-3 active:bg-zinc-800">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-bold">OS {{ $os['numero'] }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ \App\Support\Colaborador\SituacaoOs::classes($os['situacao']['cor']) }}">{{ $os['situacao']['rotulo'] }}</span>
                        </div>
                        <p class="mt-1 text-xs text-zinc-400">Aberta em {{ $os['aberta_em']->format('d/m/Y') }} · <strong class="text-zinc-200">{{ $os['dias'] }} {{ $os['dias'] === 1 ? 'dia' : 'dias' }}</strong>{{ $titulo === 'Em aberto' ? ' em aberto' : ' de duração' }}</p>
                        <p class="text-xs text-zinc-400">Técnico: {{ $os['tecnico'] ?? 'não definido' }}</p>
                        @if(filled($os['problema']))<p class="mt-1 line-clamp-2 text-xs text-zinc-500">{{ $os['problema'] }}</p>@endif
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach

    @if($abertas->isEmpty() && $fechadas->isEmpty())
        <p class="mt-5 rounded-xl bg-zinc-900 p-4 text-sm text-zinc-400">Este equipamento não tem nenhuma ordem de serviço.</p>
    @elseif($abertas->isEmpty())
        <p class="mt-5 rounded-xl bg-emerald-500/10 p-3 text-sm font-medium text-emerald-400">Nenhuma OS em aberto neste equipamento.</p>
    @endif
@endsection
