@extends('layouts.colaborador')
@section('titulo', 'OS do equipamento - Oravel Colaborador')
@section('conteudo')
    <a href="{{ route('colab.home') }}" class="text-sm font-semibold text-zinc-400">← Início</a>
    <h1 class="mt-3 text-xl font-bold">OS do equipamento</h1>
    <p class="mt-1 text-sm text-zinc-400">Encontre o equipamento e veja as ordens de serviço dele.</p>

    <a href="{{ route('colab.escanear') }}" class="mt-4 flex min-h-[3rem] items-center justify-center rounded-xl bg-orange-500 text-base font-bold text-white active:bg-orange-600">📷 Ler QR do equipamento</a>

    <form method="GET" action="{{ route('colab.os.buscar') }}" class="mt-5">
        <label class="mb-1 block text-xs font-semibold uppercase text-zinc-400" for="q">Ou digite patrimônio, placa, TAG ou nome</label>
        <input id="q" name="q" value="{{ $termo }}" autocomplete="off" placeholder="Ex.: PAT-0001 ou ABC1D23"
               class="min-h-[3rem] w-full rounded-xl border border-zinc-700 bg-zinc-900 px-4 text-base uppercase text-white placeholder:normal-case">
        <button type="submit" class="mt-3 min-h-[3rem] w-full rounded-xl bg-zinc-800 text-base font-bold text-white active:bg-zinc-700">Buscar equipamento</button>
    </form>

    @if($erro)<p class="mt-4 rounded-lg bg-red-500/15 px-3 py-2 text-sm font-medium text-red-400">{{ $erro }}</p>@endif

    <div class="mt-4 space-y-2">
        @foreach($ativos as $a)
            <a href="{{ route('colab.os.ativo', ['id' => $a->id]) }}" class="block rounded-xl bg-zinc-900 p-3 active:bg-zinc-800">
                <span class="block text-sm font-bold">{{ $a->name }}</span>
                <span class="block text-xs text-zinc-400">Patrimônio: {{ $a->patrimonio ?? '—' }}@if($a->placa) · Placa: {{ $a->placa }}@endif</span>
            </a>
        @endforeach
    </div>
@endsection
