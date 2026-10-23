@extends('layouts.colaborador')
@section('titulo', 'Faltas e ausências')
@section('voltar', route('colab.home'))
@section('voltar_rotulo', 'Início')
@section('tela', 'Faltas e ausências')
@section('conteudo')

    @if(session('ok'))<div class="mt-4 rounded-xl bg-emerald-100 p-4 text-sm font-bold text-emerald-700">✅ {{ session('ok') }}</div>@endif

    @if($semFicha)
        <p class="mt-6 rounded-xl bg-white p-4 text-sm text-zinc-600 border border-zinc-200 shadow-sm">Seu usuário ainda não está vinculado a um colaborador do Departamento Pessoal. Fale com o RH.</p>
    @else
        @can('create', \App\Models\Absence::class)
            <a href="{{ route('colab.ausencias.nova') }}" class="mt-4 flex min-h-[3.25rem] items-center justify-center rounded-xl bg-blue-600 text-sm font-bold text-white active:bg-blue-600">+ COMUNICAR FALTA OU AUSÊNCIA</a>
        @endcan

        <p class="mt-4 text-xs text-zinc-600">Dias de ausência em {{ now()->year }} (sem contar as rejeitadas): <span class="font-bold text-zinc-700">{{ $diasNoAno }}</span></p>

        <section class="mt-4 space-y-3">
            <div id="aus-fila"></div>
            @forelse($faltas as $f)
                @php $cor = match($f->status) { 'aprovado' => 'text-emerald-700 bg-emerald-100', 'rejeitado' => 'text-red-600 bg-red-100', default => 'text-amber-700 bg-amber-100' }; @endphp
                <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-bold">{{ $f->start_date->format('d/m/Y') }}@if(! $f->start_date->equalTo($f->end_date)) – {{ $f->end_date->format('d/m/Y') }}@endif <span class="text-xs font-normal text-zinc-600">· {{ $f->days }} {{ $f->days === 1 ? 'dia' : 'dias' }}</span></p>
                            <p class="text-xs text-zinc-600">{{ \App\Models\Absence::typeLabels()[$f->type] ?? $f->type }}@if($f->cid_code) · CID {{ $f->cid_code }}@endif</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $cor }}">{{ \App\Models\Absence::statusLabels()[$f->status] ?? $f->status }}</span>
                    </div>
                    <p class="mt-2 text-sm text-zinc-700">{{ $f->reason }}</p>
                    @if($f->status === 'rejeitado' && $f->review_notes)<p class="mt-2 rounded-lg bg-red-100 p-2 text-xs text-red-600">RH: {{ $f->review_notes }}</p>@endif
                    @foreach($f->attachmentPaths() as $i => $_)
                        <a href="{{ route('colab.ausencias.anexo', [$f->id, $i]) }}" target="_blank" class="mr-2 mt-2 inline-block rounded-lg border border-zinc-300 px-2 py-1 text-xs text-zinc-700">📎 Anexo {{ $i + 1 }}</a>
                    @endforeach
                </div>
            @empty
                <p class="rounded-xl bg-white p-4 text-sm text-zinc-600 border border-zinc-200 shadow-sm">Nenhuma falta ou ausência registrada.</p>
            @endforelse
        </section>
    @endif
@endsection
