@extends('layouts.colaborador')
@section('titulo', 'Minhas OS - Oravel Colaborador')
@section('voltar', route('colab.home'))
@section('voltar_rotulo', 'Início')
@section('tela', 'Minhas ordens de serviço')
@section('conteudo')

    <div class="mt-4 grid grid-cols-2 gap-2 rounded-xl bg-white p-1 text-center text-sm font-bold border border-zinc-200 shadow-sm">
        <a href="{{ route('colab.minhas-os') }}" class="rounded-lg py-2 {{ $aba === 'abertas' ? 'bg-blue-600 text-white' : 'text-zinc-600' }}">Em aberto ({{ $totalAbertas }})</a>
        <a href="{{ route('colab.minhas-os', ['aba' => 'concluidas']) }}" class="rounded-lg py-2 {{ $aba === 'concluidas' ? 'bg-blue-600 text-white' : 'text-zinc-600' }}">Concluídas</a>
    </div>

    <div class="mt-4 space-y-2">
        @forelse($ordens as $os)
            <div class="rounded-xl bg-white p-3 border border-zinc-200 shadow-sm">
            <a href="{{ route('colab.os', ['id' => $os['id']]) }}" class="block active:opacity-70">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-bold">OS {{ $os['numero'] }}@if($os['prazo_fatal']) <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-600">PRAZO FATAL</span>@endif</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ \App\Support\Colaborador\SituacaoOs::classes($os['situacao']['cor']) }}">{{ $os['situacao']['rotulo'] }}</span>
                </div>
                <p class="mt-1 text-sm font-semibold text-zinc-800">{{ $os['equipamento'] ?? 'Equipamento não informado' }}</p>
                @if($os['identificacao'])<p class="text-xs text-zinc-600">{{ $os['identificacao'] }}</p>@endif
                <p class="mt-1 text-xs text-zinc-600">
                    Aberta em {{ $os['aberta_em']->format('d/m/Y') }} · <strong class="text-zinc-800">{{ $os['dias'] }} {{ $os['dias'] === 1 ? 'dia' : 'dias' }}</strong>{{ $aba === 'abertas' ? ' em aberto' : ' de duração' }}
                    @if($os['agendada'] && $aba === 'abertas') · agendada {{ $os['agendada']->format('d/m H:i') }}@endif
                </p>
                @if(filled($os['problema']))<p class="mt-1 line-clamp-2 text-xs text-zinc-600">{{ $os['problema'] }}</p>@endif
            </a>
            @if($os['pode_executar'])
                <a href="{{ route('colab.os.executar', ['maintenanceOrder' => $os['id']]) }}" class="mt-3 flex min-h-[3rem] items-center justify-center rounded-xl bg-blue-600 text-sm font-bold text-white active:bg-blue-600">Abrir OS</a>
            @endif
            </div>
        @empty
            <p class="rounded-xl bg-white p-4 text-sm text-zinc-600 border border-zinc-200 shadow-sm">{{ $aba === 'abertas' ? 'Você não tem ordens de serviço em aberto.' : 'Nenhuma ordem concluída ainda.' }}</p>
        @endforelse
    </div>
@endsection
