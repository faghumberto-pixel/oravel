@extends('layouts.colaborador')
@section('titulo', 'Sem conexão - Oravel Colaborador')
@section('conteudo')
    @php $modo = $modo ?? 'fallback'; @endphp
    <div id="colab-app"></div>
    <div id="colab-estatico" class="my-auto text-center" @if($modo === 'app') style="display:none" @endif>
        <div class="text-5xl">📡</div>
        <h1 class="mt-4 text-xl font-bold">Sem conexão</h1>
        <p class="mt-2 text-sm text-zinc-400">Esta tela ainda não foi aberta neste aparelho. O app abre sem internet as telas que você já visitou, e o registro de ponto continua funcionando e sincroniza quando a conexão voltar.</p>
        <div class="mt-6 space-y-3">
            <a href="{{ route('colab.home') }}" class="flex min-h-[3rem] items-center justify-center rounded-xl bg-orange-500 text-base font-bold text-white active:bg-orange-600">Ir para o início</a>
            <a href="{{ route('colab.ponto') }}" class="flex min-h-[3rem] items-center justify-center rounded-xl bg-zinc-800 text-base font-bold text-white active:bg-zinc-700">Registro de ponto</a>
            <button type="button" onclick="location.reload()" class="min-h-[3rem] w-full rounded-xl border border-zinc-700 text-base font-bold text-zinc-300 active:bg-zinc-800">Tentar de novo</button>
        </div>
    </div>
@endsection
