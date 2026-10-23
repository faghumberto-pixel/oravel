@extends('layouts.colaborador')
@section('titulo', 'OS do equipamento - Oravel Colaborador')
@section('voltar', route('colab.home'))
@section('voltar_rotulo', 'Início')
@section('tela', 'OS do equipamento')
@section('tela_sub', 'Encontre o equipamento e veja as ordens de serviço dele.')
@section('conteudo')

    <a href="{{ route('colab.escanear') }}" class="mt-4 flex min-h-[3rem] items-center justify-center rounded-xl bg-blue-600 text-base font-bold text-white active:bg-blue-600">📷 Ler QR do equipamento</a>

    <form method="GET" action="{{ route('colab.os.buscar') }}" class="mt-5">
        <label class="mb-1 block text-xs font-semibold uppercase text-zinc-600" for="q">Ou digite patrimônio, placa, TAG ou nome</label>
        <input id="q" name="q" value="{{ $termo }}" autocomplete="off" placeholder="Ex.: PAT-0001 ou ABC1D23"
               class="min-h-[3rem] w-full rounded-xl border border-zinc-300 bg-white px-4 text-base uppercase text-zinc-900 placeholder:normal-case">
        <button type="submit" class="mt-3 min-h-[3rem] w-full rounded-xl bg-zinc-100 text-base font-bold text-zinc-900 active:bg-zinc-200">Buscar equipamento</button>
    </form>

    @if($erro)<p class="mt-4 rounded-lg bg-red-100 px-3 py-2 text-sm font-medium text-red-600">{{ $erro }}</p>@endif

    <div class="mt-4 space-y-2">
        @foreach($ativos as $a)
            <a href="{{ route('colab.os.ativo', ['id' => $a->id]) }}" class="block rounded-xl bg-white p-3 active:bg-zinc-100 border border-zinc-200 shadow-sm">
                <span class="block text-sm font-bold">{{ $a->name }}</span>
                <span class="block text-xs text-zinc-600">Patrimônio: {{ $a->patrimonio ?? '—' }}@if($a->placa) · Placa: {{ $a->placa }}@endif</span>
            </a>
        @endforeach
    </div>
@endsection
