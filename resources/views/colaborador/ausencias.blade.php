@extends('layouts.colaborador')
@section('titulo', 'Faltas e ausências')
@section('conteudo')
    <header class="flex items-center gap-3">
        <a href="{{ route('colab.home') }}" class="flex h-9 w-9 items-center justify-center rounded-full border border-zinc-700 text-zinc-300 active:bg-zinc-800" aria-label="Voltar">←</a>
        <h1 class="text-lg font-black">Faltas e ausências</h1>
    </header>

    @if(session('ok'))<div class="mt-4 rounded-2xl bg-emerald-500/15 p-4 text-sm font-bold text-emerald-400">✅ {{ session('ok') }}</div>@endif

    @if($semFicha)
        <p class="mt-6 rounded-xl bg-zinc-900 p-4 text-sm text-zinc-400">Seu usuário ainda não está vinculado a um colaborador do Departamento Pessoal. Fale com o RH.</p>
    @else
        @can('create', \App\Models\Absence::class)
            <a href="{{ route('colab.ausencias.nova') }}" class="mt-4 flex min-h-[3.25rem] items-center justify-center rounded-xl bg-orange-500 text-sm font-bold text-white active:bg-orange-600">+ COMUNICAR FALTA OU AUSÊNCIA</a>
        @endcan

        <p class="mt-4 text-xs text-zinc-500">Dias de ausência em {{ now()->year }} (sem contar as rejeitadas): <span class="font-bold text-zinc-300">{{ $diasNoAno }}</span></p>

        <section class="mt-4 space-y-3">
            @forelse($faltas as $f)
                @php $cor = match($f->status) { 'aprovado' => 'text-emerald-400 bg-emerald-500/15', 'rejeitado' => 'text-red-400 bg-red-500/15', default => 'text-amber-400 bg-amber-500/15' }; @endphp
                <div class="rounded-2xl bg-zinc-900 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-bold">{{ $f->start_date->format('d/m/Y') }}@if(! $f->start_date->equalTo($f->end_date)) – {{ $f->end_date->format('d/m/Y') }}@endif <span class="text-xs font-normal text-zinc-500">· {{ $f->days }} {{ $f->days === 1 ? 'dia' : 'dias' }}</span></p>
                            <p class="text-xs text-zinc-400">{{ \App\Models\Absence::typeLabels()[$f->type] ?? $f->type }}@if($f->cid_code) · CID {{ $f->cid_code }}@endif</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $cor }}">{{ \App\Models\Absence::statusLabels()[$f->status] ?? $f->status }}</span>
                    </div>
                    <p class="mt-2 text-sm text-zinc-300">{{ $f->reason }}</p>
                    @if($f->status === 'rejeitado' && $f->review_notes)<p class="mt-2 rounded-lg bg-red-500/10 p-2 text-xs text-red-300">RH: {{ $f->review_notes }}</p>@endif
                    @foreach($f->attachmentPaths() as $i => $_)
                        <a href="{{ route('colab.ausencias.anexo', [$f->id, $i]) }}" target="_blank" class="mr-2 mt-2 inline-block rounded-lg border border-zinc-700 px-2 py-1 text-xs text-zinc-300">📎 Anexo {{ $i + 1 }}</a>
                    @endforeach
                </div>
            @empty
                <p class="rounded-xl bg-zinc-900 p-4 text-sm text-zinc-400">Nenhuma falta ou ausência registrada.</p>
            @endforelse
        </section>
    @endif
@endsection
