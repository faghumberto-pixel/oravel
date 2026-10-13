@extends('layouts.colaborador')
@section('titulo', 'Comunicar ausência')
@section('conteudo')
    <header class="flex items-center gap-3">
        <a href="{{ route('colab.ausencias') }}" class="flex h-9 w-9 items-center justify-center rounded-full border border-zinc-700 text-zinc-300 active:bg-zinc-800" aria-label="Voltar">←</a>
        <h1 class="text-lg font-black">Comunicar ausência</h1>
    </header>

    @if($semFicha)
        <p class="mt-6 rounded-xl bg-zinc-900 p-4 text-sm text-zinc-400">Seu usuário ainda não está vinculado a um colaborador do Departamento Pessoal. Fale com o RH.</p>
    @else
        @if($errors->any())<div class="mt-4 rounded-2xl bg-red-500/15 p-4 text-sm font-bold text-red-400">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

        <form method="POST" action="{{ route('colab.ausencias.salvar') }}" enctype="multipart/form-data" class="mt-4 space-y-3 rounded-2xl bg-zinc-900 p-4" onsubmit="this.querySelector('button[type=submit]').disabled = true">
            @csrf
            <label class="block text-xs font-bold tracking-wider text-zinc-400">TIPO
                <select name="type" id="aus-tipo" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
                    @foreach(\App\Models\Absence::typeLabels() as $v => $r)<option value="{{ $v }}" @selected(old('type', 'atestado_medico') === $v)>{{ $r }}</option>@endforeach
                </select>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-xs font-bold tracking-wider text-zinc-400">DE
                    <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-400">ATÉ
                    <input type="date" name="end_date" value="{{ old('end_date', now()->toDateString()) }}" required class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
                </label>
            </div>
            <label class="block text-xs font-bold tracking-wider text-zinc-400">MOTIVO (RESUMO)
                <input type="text" name="reason" value="{{ old('reason') }}" maxlength="191" required placeholder="Ex.: consulta médica, gripe" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
            </label>
            <label class="block text-xs font-bold tracking-wider text-zinc-400">CID (SE CONSTAR NO ATESTADO)
                <input type="text" name="cid_code" value="{{ old('cid_code') }}" maxlength="10" autocapitalize="characters" placeholder="Ex.: J11 ou M54.5" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base uppercase text-white">
            </label>
            <label class="block text-xs font-bold tracking-wider text-zinc-400">EXPLIQUE O QUE ACONTECEU (OPCIONAL)
                <textarea name="details" rows="4" maxlength="3000" class="mt-1 w-full rounded-xl bg-zinc-800 px-3 py-2 text-base text-white">{{ old('details') }}</textarea>
            </label>
            <label class="block text-xs font-bold tracking-wider text-zinc-400">FOTO DO ATESTADO OU DOCUMENTO
                <input type="file" name="anexos[]" id="aus-anexos" accept="image/*,application/pdf" multiple class="mt-1 w-full text-xs text-zinc-300 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-700 file:px-3 file:py-3 file:text-xs file:font-bold file:text-white">
                <span class="mt-1 block text-[11px] font-normal normal-case tracking-normal text-zinc-500">Tire a foto na hora ou escolha da galeria. Até 5 arquivos de 8 MB. Se não tiver agora, envie sem e leve o documento ao RH.</span>
            </label>
            <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-orange-500 text-sm font-bold text-white active:bg-orange-600 disabled:opacity-50">ENVIAR AO RH</button>
        </form>
    @endif
@endsection
