@extends('layouts.colaborador')
@section('titulo', 'Minhas OS - Oravel Colaborador')
@section('conteudo')
    <a href="{{ route('colab.home') }}" class="text-sm font-semibold text-zinc-400">← Início</a>
    <h1 class="mt-3 text-xl font-bold">Minhas ordens de serviço</h1>

    <div class="mt-4 grid grid-cols-2 gap-2 rounded-xl bg-zinc-900 p-1 text-center text-sm font-bold">
        <a href="{{ route('colab.minhas-os') }}" class="rounded-lg py-2 {{ $aba === 'abertas' ? 'bg-orange-500 text-white' : 'text-zinc-400' }}">Em aberto ({{ $totalAbertas }})</a>
        <a href="{{ route('colab.minhas-os', ['aba' => 'concluidas']) }}" class="rounded-lg py-2 {{ $aba === 'concluidas' ? 'bg-orange-500 text-white' : 'text-zinc-400' }}">Concluídas</a>
    </div>

    <div class="mt-4 space-y-2">
        @forelse($ordens as $os)
            <a href="{{ route('colab.os', ['id' => $os['id']]) }}" class="block rounded-xl bg-zinc-900 p-3 active:bg-zinc-800">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-bold">OS {{ $os['numero'] }}@if($os['prazo_fatal']) <span class="ml-1 rounded bg-red-500/20 px-1.5 py-0.5 text-[10px] font-bold text-red-400">PRAZO FATAL</span>@endif</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ \App\Support\Colaborador\SituacaoOs::classes($os['situacao']['cor']) }}">{{ $os['situacao']['rotulo'] }}</span>
                </div>
                <p class="mt-1 text-sm font-semibold text-zinc-200">{{ $os['equipamento'] ?? 'Equipamento não informado' }}</p>
                @if($os['identificacao'])<p class="text-xs text-zinc-400">{{ $os['identificacao'] }}</p>@endif
                <p class="mt-1 text-xs text-zinc-400">
                    Aberta em {{ $os['aberta_em']->format('d/m/Y') }} · <strong class="text-zinc-200">{{ $os['dias'] }} {{ $os['dias'] === 1 ? 'dia' : 'dias' }}</strong>{{ $aba === 'abertas' ? ' em aberto' : ' de duração' }}
                    @if($os['agendada'] && $aba === 'abertas') · agendada {{ $os['agendada']->format('d/m H:i') }}@endif
                </p>
                @if(filled($os['problema']))<p class="mt-1 line-clamp-2 text-xs text-zinc-500">{{ $os['problema'] }}</p>@endif
            </a>
        @empty
            <p class="rounded-xl bg-zinc-900 p-4 text-sm text-zinc-400">{{ $aba === 'abertas' ? 'Você não tem ordens de serviço em aberto.' : 'Nenhuma ordem concluída ainda.' }}</p>
        @endforelse
    </div>
@endsection
