@php
    $fmtTime = fn ($m) => $m >= 60 ? intdiv((int) $m, 60).' h '.((int) $m % 60).' min' : (int) $m.' min';
    $cards = [
        ['Colaboradores ativos', $summary['active'].' de '.$summary['people'], 'com atividade no período', $summary['active_percent']],
        ['Conteúdo concluído', $summary['completion'].'%', 'das aulas liberadas no contrato', $summary['completion']],
        ['Acerto nos quizzes', is_null($summary['quiz_rate']) ? '—' : $summary['quiz_rate'].'%', 'respostas certas', $summary['quiz_rate']],
        ['Equipe certificada', $summary['certified_percent'].'%', $summary['certificates'].' certificado(s) no período', $summary['certified_percent']],
        ['Aulas concluídas', number_format($summary['lessons'], 0, ',', '.'), 'no período', null],
        ['Pontos', number_format($summary['points'], 0, ',', '.'), 'leitura, quiz e tempo de estudo', null],
        ['Tempo de estudo', $fmtTime($summary['minutes']), 'tempo ativo nas aulas', null],
    ];
    $max = max(1, max($weekly ?: [0]));
    $th = fn ($col, $label) => '<th class="s" wire:click="sortBy(\''.$col.'\')">'.$label.($sort === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '').'</th>';
@endphp
<div>
    <section class="ac-hero sm">
        <div class="ac-crumb"><a href="{{ url('/academia') }}">Academia</a> / Participação da equipe</div>
        <h1>📊 Participação da equipe</h1>
        <p>Acompanhe quem da sua empresa está estudando, o quanto já concluiu e como foi nos quizzes.</p>
    </section>

    <div class="ac-inputs">
        <select wire:model.live="period" data-testid="period">
            @foreach ($periods as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
        </select>
    </div>

    <div class="ac-kpis" data-testid="participation-cards">
        @foreach ($cards as [$label, $value, $hint, $percent])
            <div class="ac-kpi">
                <small>{{ $label }}</small><strong>{{ $value }}</strong>
                @if (! is_null($percent)) <x-academy.bar :percent="$percent" style="width:100%" /> @endif
                <em>{{ $hint }}</em>
            </div>
        @endforeach
    </div>

    <div class="ac-grid2">
        <div class="ac-card">
            <h2>Aulas concluídas por semana</h2>
            <div style="display:flex;align-items:flex-end;gap:8px;height:140px" data-testid="weekly-chart">
                @foreach ($weekly as $label => $n)
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:4px">
                        <span style="font-size:11px;color:#6b7280">{{ $n }}</span>
                        <div style="width:100%;border-radius:5px 5px 0 0;background:linear-gradient(180deg,#2563eb,#0ea5a4);height:{{ max(3, (int) round($n / $max * 90)) }}px"></div>
                        <span style="font-size:10px;color:#9ca3af">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="ac-card">
            <h2>Participação por curso</h2>
            @forelse ($courses as $c)
                <div style="margin-bottom:12px">
                    <x-academy.bar :percent="$c['percent']" :label="$c['title'].' · '.$c['started'].' '.($c['started'] == 1 ? 'pessoa' : 'pessoas').' começaram · '.$c['certified'].' '.($c['certified'] == 1 ? 'certificado' : 'certificados').' ('.$c['certified_percent'].'% da equipe)'" style="width:100%" />
                </div>
            @empty
                <p style="color:var(--muted);font-size:14px">Ninguém estudou ainda neste período.</p>
            @endforelse
        </div>
    </div>

    <div class="ac-card">
        <div class="ac-inputs">
            <input type="search" placeholder="Buscar colaborador…" wire:model.live.debounce.300ms="search" data-testid="search">
            <select wire:model.live="status" data-testid="status">
                <option value="">Todos</option>
                <option value="ativos">Estudando (com atividade no período)</option>
                <option value="inativos">Sem atividade no período</option>
                <option value="certificados">Já têm certificado</option>
            </select>
        </div>
        <div class="ac-table-wrap">
            <table class="ac-table" data-testid="team-table">
                <thead><tr>
                    {!! $th('name', 'Colaborador') !!}{!! $th('completion', 'Conteúdo concluído') !!}{!! $th('quiz_rate', 'Acerto no quiz') !!}
                    {!! $th('points', 'Pontos') !!}{!! $th('certificates', 'Certificados') !!}{!! $th('minutes', 'Tempo') !!}{!! $th('last_activity', 'Última atividade') !!}
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr wire:key="u-{{ $r->id }}">
                        <td><b>{{ $r->name }}</b></td>
                        <td style="min-width:150px"><x-academy.bar :percent="$r->lessons_available ? $r->lessons_done_all / $r->lessons_available * 100 : 0" :label="$r->lessons_done_all.' de '.$r->lessons_available.' aulas'" style="width:100%" /></td>
                        <td style="min-width:130px">@if ($r->quiz_total) <x-academy.bar :percent="$r->quiz_correct / $r->quiz_total * 100" :label="$r->quiz_correct.' de '.$r->quiz_total.' certas'" style="width:100%" /> @else — @endif</td>
                        <td>{{ number_format($r->points, 0, ',', '.') }}</td>
                        <td>{{ $r->certificates }}</td>
                        <td>{{ $fmtTime($r->minutes) }}</td>
                        <td style="{{ $r->last_activity ? '' : 'color:#dc2626' }}">{{ $r->last_activity ? \Illuminate\Support\Carbon::parse($r->last_activity)->diffForHumans() : 'Nunca' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="color:var(--muted);text-align:center;padding:22px">Nenhum colaborador encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($pages > 1)
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:13px;color:var(--muted)">
                <span>{{ $total }} colaboradores · página {{ $page }} de {{ $pages }}</span>
                <span style="display:flex;gap:8px">
                    <button type="button" class="ac-btn ghost" style="padding:6px 14px" wire:click="go({{ $page - 1 }})" @disabled($page <= 1)>← Anterior</button>
                    <button type="button" class="ac-btn ghost" style="padding:6px 14px" wire:click="go({{ $page + 1 }})" @disabled($page >= $pages)>Próxima →</button>
                </span>
            </div>
        @endif
    </div>
</div>
