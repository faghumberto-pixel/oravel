{{-- Resumo + atividade + cursos, compartilhado entre a tela do admin do cliente e a da Central. --}}
@php
    $minutes = $summary['minutes'];
    $time = $minutes >= 60 ? intdiv($minutes, 60).' h '.($minutes % 60).' min' : $minutes.' min';
    // [rótulo, valor grande, dica, percentual da barra ou null]
    $cards = [
        ['Colaboradores ativos', $summary['active'].' de '.$summary['people'], 'com atividade no período', $summary['active_percent']],
        ['Conteúdo concluído', $summary['completion'].'%', 'das aulas liberadas no contrato', $summary['completion']],
        ['Acerto nos quizzes', is_null($summary['quiz_rate']) ? '—' : $summary['quiz_rate'].'%', 'respostas certas', $summary['quiz_rate']],
        ['Equipe certificada', $summary['certified_percent'].'%', $summary['certificates'].' certificado(s) no período', $summary['certified_percent']],
        ['Aulas concluídas', number_format($summary['lessons'], 0, ',', '.'), 'no período', null],
        ['Pontos', number_format($summary['points'], 0, ',', '.'), 'leitura, quiz e tempo de estudo', null],
        ['Tempo de estudo', $time, 'tempo ativo nas aulas', null],
    ];
    $max = max(1, max($weekly ?: [0]));
@endphp

<div class="grid grid-cols-2 gap-3 md:grid-cols-4" data-testid="participation-cards">
    @foreach ($cards as [$label, $value, $hint, $percent])
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs text-gray-500">{{ $label }}</p>
            <p class="mt-1 text-xl font-semibold">{{ $value }}</p>
            @if (! is_null($percent))
                <x-academy.bar :percent="$percent" class="mt-2" />
            @endif
            <p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>
        </div>
    @endforeach
</div>

<div class="grid gap-4 lg:grid-cols-2">
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <p class="mb-3 text-sm font-semibold">Aulas concluídas por semana</p>
        <div style="display:flex;align-items:flex-end;gap:8px;height:130px" data-testid="weekly-chart">
            @foreach ($weekly as $label => $n)
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:4px">
                    <span style="font-size:11px;color:#6b7280">{{ $n }}</span>
                    <div style="width:100%;border-radius:4px 4px 0 0;background:#2563eb;height:{{ max(2, (int) round($n / $max * 80)) }}px"></div>
                    <span style="font-size:10px;color:#9ca3af">{{ $label }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <p class="mb-3 text-sm font-semibold">Participação por curso</p>
        @forelse ($courses as $c)
            <div style="margin-bottom:12px">
                <x-academy.bar :percent="$c['percent']" :label="$c['title'].' · '.$c['started'].' '.($c['started'] == 1 ? 'pessoa' : 'pessoas').' começaram · '.$c['certified'].' '.($c['certified'] == 1 ? 'certificado' : 'certificados').' ('.$c['certified_percent'].'% da equipe)'" style="width:100%" />
            </div>
        @empty
            <p class="text-sm text-gray-500">Ninguém estudou ainda neste período.</p>
        @endforelse
    </div>
</div>
