@extends('layouts.colaborador')
@section('titulo', 'Comunicar ausência')
@section('voltar', route('colab.ausencias'))
@section('voltar_rotulo', 'Faltas e ausências')
@section('tela', 'Comunicar ausência')
@section('conteudo')

    @if($semFicha)
        <p class="mt-6 rounded-xl bg-white p-4 text-sm text-zinc-600 border border-zinc-200 shadow-sm">Seu usuário ainda não está vinculado a um colaborador do Departamento Pessoal. Fale com o RH.</p>
    @else
        @if($errors->any())<div class="mt-4 rounded-xl bg-red-100 p-4 text-sm font-bold text-red-600">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

        <p id="aus-erro" hidden class="mt-4 rounded-xl bg-red-100 p-4 text-sm font-bold text-red-600"></p>
        <form method="POST" action="{{ route('colab.ausencias.salvar') }}" enctype="multipart/form-data" class="mt-4 space-y-3 rounded-xl bg-white p-4 border border-zinc-200 shadow-sm" data-colab-ausencia onsubmit="this.querySelector('button[type=submit]').disabled = true">
            @csrf
            <label class="block text-xs font-bold tracking-wider text-zinc-600">TIPO
                <select name="type" id="aus-tipo" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900">
                    @foreach(\App\Models\Absence::typeLabels() as $v => $r)<option value="{{ $v }}" @selected(old('type', 'atestado_medico') === $v)>{{ $r }}</option>@endforeach
                </select>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-xs font-bold tracking-wider text-zinc-600">DE
                    <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900">
                </label>
                <label class="block text-xs font-bold tracking-wider text-zinc-600">ATÉ
                    <input type="date" name="end_date" value="{{ old('end_date', now()->toDateString()) }}" required class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900">
                </label>
            </div>
            <label class="block text-xs font-bold tracking-wider text-zinc-600">MOTIVO (RESUMO)
                <input type="text" name="reason" value="{{ old('reason') }}" maxlength="191" required placeholder="Ex.: consulta médica, gripe" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900">
            </label>
            <label class="block text-xs font-bold tracking-wider text-zinc-600">CID (SE CONSTAR NO ATESTADO)
                <input type="text" name="cid_code" value="{{ old('cid_code') }}" maxlength="10" autocapitalize="characters" placeholder="Ex.: J11 ou M54.5" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base uppercase text-zinc-900">
            </label>
            <label class="block text-xs font-bold tracking-wider text-zinc-600">EXPLIQUE O QUE ACONTECEU (OPCIONAL)
                <textarea name="details" rows="4" maxlength="3000" class="mt-1 w-full rounded-xl bg-zinc-100 px-3 py-2 text-base text-zinc-900">{{ old('details') }}</textarea>
            </label>
            <label class="block text-xs font-bold tracking-wider text-zinc-600">FOTO DO ATESTADO OU DOCUMENTO
                <input type="file" name="anexos[]" id="aus-anexos" accept="image/*,application/pdf" multiple class="mt-1 w-full text-xs text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-200 file:px-3 file:py-3 file:text-xs file:font-bold file:text-zinc-900">
                <span class="mt-1 block text-[11px] font-normal normal-case tracking-normal text-zinc-600">Tire a foto na hora ou escolha da galeria. Até 5 arquivos de 8 MB. Se não tiver agora, envie sem e leve o documento ao RH.</span>
            </label>
            <button type="submit" class="flex min-h-[3.25rem] w-full items-center justify-center rounded-xl bg-blue-600 text-sm font-bold text-white active:bg-blue-600 disabled:opacity-50">ENVIAR AO RH</button>
        </form>
    @endif
@endsection
