@extends('layouts.colaborador')
@section('titulo', 'Km rodado - Oravel Colaborador')
@section('voltar', route('colab.logistica'))
@section('voltar_rotulo', 'Logística')
@section('tela', 'Km rodado e percursos')
@section('conteudo')

    <form method="GET" action="{{ route('colab.logistica.km-rodado') }}" class="mt-4">
        <select name="periodo" onchange="this.form.submit()" class="min-h-[3rem] w-full rounded-xl border border-zinc-300 bg-white px-4 text-base text-zinc-900">
            @foreach($periodos as $k => $nome)<option value="{{ $k }}" @selected($k === $chave)>{{ $nome }}</option>@endforeach
        </select>
    </form>

    <h2 class="mb-2 mt-5 text-xs font-bold uppercase tracking-wide text-zinc-600">Por veículo</h2>
    <div class="space-y-2">
        @forelse($veiculos as $v)
            <div class="rounded-xl bg-white p-3 border border-zinc-200 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-bold">{{ $v['ativo']->placa ?? $v['ativo']->name }}</span>
                    <span class="text-base font-bold text-blue-700">{{ number_format($v['total'], 0, ',', '.') }} km</span>
                </div>
                <p class="text-xs text-zinc-600">{{ $v['ativo']->name }}</p>
                <p class="mt-1 text-xs text-zinc-600">Em percursos: <strong class="text-zinc-800">{{ number_format($v['em_percursos'], 0, ',', '.') }} km</strong> · Fora de percurso: <strong class="text-zinc-800">{{ number_format($v['fora_de_percurso'], 0, ',', '.') }} km</strong> · {{ $v['viagens'] }} {{ $v['viagens'] === 1 ? 'viagem' : 'viagens' }}@if($v['custo'] !== null) · R$ {{ number_format($v['custo'], 2, ',', '.') }}@endif</p>
            </div>
        @empty
            <p class="rounded-xl bg-white p-4 text-sm text-zinc-600 border border-zinc-200 shadow-sm">Nenhum veículo cadastrado.</p>
        @endforelse
    </div>

    <h2 class="mb-2 mt-5 text-xs font-bold uppercase tracking-wide text-zinc-600">Por percurso</h2>
    <div class="space-y-2">
        @forelse($percursos as $l)
            <div class="rounded-xl bg-white p-3 border border-zinc-200 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-bold">{{ $l['percurso']->nome }}</span>
                    <span class="text-base font-bold text-blue-700">{{ number_format($l['km_total'], 0, ',', '.') }} km</span>
                </div>
                <p class="text-xs text-zinc-600">{{ $l['percurso']->trajeto() }}</p>
                <p class="mt-1 text-xs text-zinc-600">{{ $l['viagens'] }} {{ $l['viagens'] === 1 ? 'viagem' : 'viagens' }}@if($l['km_medio'] !== null) · média {{ number_format($l['km_medio'], 1, ',', '.') }} km @endif @if($l['percurso']->km_previsto) · previsto {{ number_format((float) $l['percurso']->km_previsto, 1, ',', '.') }} km @endif @if($l['desvio_pct'] !== null) · <strong class="{{ abs($l['desvio_pct']) > 20 ? 'text-red-600' : (abs($l['desvio_pct']) > 10 ? 'text-amber-700' : 'text-emerald-700') }}">{{ $l['desvio_pct'] > 0 ? '+' : '' }}{{ number_format($l['desvio_pct'], 1, ',', '.') }}%</strong>@endif</p>
            </div>
        @empty
            <p class="rounded-xl bg-white p-4 text-sm text-zinc-600 border border-zinc-200 shadow-sm">Nenhum percurso cadastrado. Crie em Logística → Frota → Percursos, no painel.</p>
        @endforelse
    </div>
@endsection
