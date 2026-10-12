@extends('layouts.colaborador')
@section('titulo', 'OS '.$os->os_number.' - Oravel Colaborador')
@section('conteudo')
    <a href="{{ $os->asset ? route('colab.dossie', ['assetId' => $os->asset_id]) : route('colab.os.buscar') }}" class="text-sm font-semibold text-zinc-400">← {{ $os->asset ? 'Dossiê do ativo' : 'Buscar OS' }}</a>

    <div class="mt-3 flex items-start justify-between gap-3">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-zinc-500">Dossiê da OS</p>
            <h1 class="text-2xl font-extrabold">OS {{ $os->os_number }}</h1>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-bold {{ \App\Support\Colaborador\SituacaoOs::classes($situacao['cor']) }}">{{ $situacao['rotulo'] }}</span>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3">
        <div class="rounded-2xl bg-zinc-900 p-3">
            <p class="text-[11px] uppercase text-zinc-500">Aberta em</p>
            <p class="text-sm font-bold">{{ $os->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div class="rounded-2xl bg-zinc-900 p-3">
            <p class="text-[11px] uppercase text-zinc-500">{{ $fechada ? 'Duração' : 'Em aberto há' }}</p>
            <p class="text-sm font-bold">{{ $dias }} {{ $dias === 1 ? 'dia' : 'dias' }}</p>
        </div>
    </div>

    <dl class="mt-3 divide-y divide-zinc-800 rounded-2xl bg-zinc-900 px-4 text-sm">
        @php
            $linhas = [
                'Equipamento' => $os->asset?->name,
                'Patrimônio' => $os->asset?->patrimonio,
                'Cliente' => $os->client?->name,
                'Técnico' => $os->technician?->name ?? 'não definido',
                'Tipo' => $os->maintenance_type ?: $os->service_type,
                'Prioridade' => $os->priority,
                'Agendada para' => $os->scheduled_at?->format('d/m/Y H:i'),
                'Iniciada em' => $os->started_at?->format('d/m/Y H:i'),
                'Concluída em' => $os->finished_at?->format('d/m/Y H:i'),
                'Horas gastas' => filled($os->hours_spent) ? $os->hours_spent.' h' : null,
            ];
        @endphp
        @foreach(array_filter($linhas, fn ($v) => filled($v)) as $rotulo => $valor)
            <div class="flex justify-between gap-4 py-2.5"><dt class="text-zinc-400">{{ $rotulo }}</dt><dd class="text-right font-semibold">{{ $valor }}</dd></div>
        @endforeach
    </dl>

    @foreach(['Problema relatado' => $os->reportedProblem?->description ?: $os->description, 'Solução' => $os->solution, 'Notas técnicas' => $os->technical_notes] as $titulo => $texto)
        @if(filled($texto))
            <section class="mt-3 rounded-2xl bg-zinc-900 p-4">
                <h2 class="text-xs font-bold uppercase tracking-wide text-zinc-500">{{ $titulo }}</h2>
                <p class="mt-1 whitespace-pre-line text-sm text-zinc-200">{{ $texto }}</p>
            </section>
        @endif
    @endforeach

    @if($os->pendencias->isNotEmpty())
        <section class="mt-3 rounded-2xl bg-zinc-900 p-4">
            <h2 class="text-xs font-bold uppercase tracking-wide text-zinc-500">Pendências ({{ $os->pendencias->count() }})</h2>
            <ul class="mt-2 space-y-2">
                @foreach($os->pendencias as $p)
                    <li class="text-sm"><span class="font-semibold">{{ $p->description }}</span> <span class="text-xs text-zinc-500">· {{ $p->status }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
