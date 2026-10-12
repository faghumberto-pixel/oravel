@extends('layouts.colaborador')
@section('titulo', 'Dossiê da OS - Oravel Colaborador')
@section('conteudo')
    <a href="{{ route('colab.home') }}" class="text-sm font-semibold text-zinc-400">← Início</a>
    <h1 class="mt-3 text-xl font-bold">Dossiê da OS</h1>

    <form method="GET" action="{{ route('colab.os.buscar') }}" class="mt-4">
        <label class="mb-1 block text-xs font-semibold uppercase text-zinc-400" for="numero">Número da ordem de serviço</label>
        <input id="numero" name="numero" value="{{ request('numero') }}" autocomplete="off" autofocus placeholder="Ex.: 00123"
               class="min-h-[3rem] w-full rounded-xl border border-zinc-700 bg-zinc-900 px-4 text-base text-white">
        <button type="submit" class="mt-3 min-h-[3rem] w-full rounded-xl bg-orange-500 text-base font-bold text-white active:bg-orange-600">Buscar</button>
    </form>

    @if($erro)<p class="mt-4 rounded-lg bg-red-500/15 px-3 py-2 text-sm font-medium text-red-400">{{ $erro }}</p>@endif

    <div class="mt-4 space-y-2">
        @foreach($resultados as $r)
            <a href="{{ route('colab.os', ['id' => $r->id]) }}" class="block rounded-xl bg-zinc-900 p-3 active:bg-zinc-800">
                <span class="block text-sm font-bold">OS {{ $r->os_number }}</span>
                <span class="block text-xs text-zinc-400">Aberta em {{ $r->created_at->format('d/m/Y') }} · {{ \App\Support\Colaborador\SituacaoOs::daOs($r)['rotulo'] }}</span>
            </a>
        @endforeach
    </div>
@endsection
