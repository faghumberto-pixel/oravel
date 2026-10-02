@php
    $fmtTime = fn ($m) => $m >= 60 ? intdiv($m, 60).' h '.($m % 60).' min' : $m.' min';
    $nota = fn ($n) => is_null($n) ? '—' : number_format($n, 1, ',', '');
    $first = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ');
@endphp
<div>
    <section class="ac-hero">
        <div class="ac-hero-row">
            <div>
                <h1>Olá, {{ $first }}! 👋</h1>
                <p>Bem-vindo à Oravel Academy. Aprenda a usar cada módulo do sistema, ganhe pontos e conquiste certificados.</p>
                @if ($resume)
                    <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
                        <a class="ac-btn alt" href="{{ url('/academia/curso/'.$resume['slug']).'?aula='.$resume['next_lesson_id'] }}">
                            ▶ {{ $resume['status'] === 'progress' ? 'Continuar' : 'Começar' }}: {{ $resume['title'] }}
                        </a>
                    </div>
                @endif
            </div>
            <div style="min-width:230px;max-width:300px;flex:1">
                <div style="font-size:12px;opacity:.85;margin-bottom:6px">Seu progresso geral</div>
                <div style="font-size:34px;font-weight:800;line-height:1">{{ $totals['overall_percent'] }}%</div>
                <div style="height:9px;background:rgba(255,255,255,.22);border-radius:99px;margin-top:10px;overflow:hidden">
                    <div style="height:9px;width:{{ $totals['overall_percent'] }}%;background:#fff;border-radius:99px"></div>
                </div>
                <div style="font-size:12px;opacity:.85;margin-top:6px">{{ $totals['lessons_done'] }} de {{ $totals['lessons_total'] }} aulas concluídas</div>
            </div>
        </div>
    </section>

    @if ($readOnly)
        <div class="ac-alert">Você está vendo a Academia como administrador da plataforma: o progresso só é registrado para usuários de clientes.</div>
    @endif

    <div class="ac-kpis" data-testid="kpis">
        <div class="ac-kpi"><small>Pontos</small><strong>{{ number_format($totals['points'], 0, ',', '.') }}</strong><em>leitura, quiz e tempo de estudo</em></div>
        <div class="ac-kpi"><small>Cursos concluídos</small><strong>{{ $totals['courses_done'] }} de {{ $totals['courses'] }}</strong>
            <x-academy.bar :percent="$totals['courses'] ? $totals['courses_done'] / $totals['courses'] * 100 : 0" style="width:100%" /></div>
        <div class="ac-kpi"><small>Nota média nos quizzes</small><strong>{{ $nota($totals['nota']) }}</strong>
            @if (! is_null($totals['nota'])) <x-academy.bar :percent="$totals['nota'] * 10" style="width:100%" /> @else <em>nenhum quiz respondido</em> @endif</div>
        <div class="ac-kpi"><small>Em andamento · A fazer</small><strong>{{ $totals['courses_progress'] }} · {{ $totals['courses_todo'] }}</strong><em>cursos</em></div>
        <div class="ac-kpi"><small>Tempo de estudo</small><strong>{{ $fmtTime($totals['minutes']) }}</strong><em>tempo ativo nas aulas</em></div>
        <div class="ac-kpi"><small>Certificados</small><strong>{{ $totals['certificates'] }}</strong><em>conquistados</em></div>
    </div>

    <h2 class="ac-h2" style="font-size:18px">Meus cursos</h2>
    <div class="ac-pills">
        @foreach (['todos' => 'Todos ('.$all->count().')', 'andamento' => 'Em andamento ('.$totals['courses_progress'].')', 'fazer' => 'A fazer ('.$totals['courses_todo'].')', 'concluidos' => 'Concluídos ('.$totals['courses_done'].')'] as $key => $label)
            <button type="button" class="ac-pill {{ $filter === $key ? 'on' : '' }}" wire:click="$set('filter', '{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>

    <div class="ac-cards" style="margin-bottom:26px" data-testid="course-cards">
        @forelse ($courses as $c)
            <a class="ac-card ac-course-card" href="{{ url('/academia/curso/'.$c['slug']).'?aula='.$c['next_lesson_id'] }}" wire:key="card-{{ $c['id'] }}">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
                    <h3>{{ $c['title'] }}</h3>
                    <span class="ac-badge {{ $c['status'] }}">{{ ['todo' => 'A fazer', 'progress' => 'Em andamento', 'done' => 'Concluído'][$c['status']] }}</span>
                </div>
                @if ($c['description']) <p>{{ $c['description'] }}</p> @endif
                <x-academy.bar :percent="$c['percent']" :label="$c['lessons_done'].' de '.$c['lessons_total'].' aulas'" style="width:100%" :height="9" />
                <div class="ac-meta">
                    <span>@if (! is_null($c['nota'])) Nota <b style="color:{{ $c['nota'] >= 7 ? '#16a34a' : ($c['nota'] >= 5 ? '#d97706' : '#dc2626') }}">{{ $nota($c['nota']) }}</b> @elseif ($c['quiz_total']) Quiz pendente @else Sem quiz @endif</span>
                    <span>{{ $c['certificate'] ? '🎓 Certificado' : ($c['status'] === 'done' ? '⏳ Falta acertar o quiz' : '') }}</span>
                </div>
            </a>
        @empty
            <div class="ac-card" style="grid-column:1/-1;color:var(--muted)">Nenhum curso nesta lista.</div>
        @endforelse
    </div>

    <div class="ac-card" style="margin-bottom:22px">
        <h2>Estatísticas por curso</h2>
        <div class="ac-table-wrap">
            <table class="ac-table" data-testid="course-stats">
                <thead><tr><th>Curso</th><th style="min-width:150px">Aulas</th><th>Nota</th><th>Acertos (respondidas)</th><th>Tempo</th><th>Pontos</th><th>Certificado</th></tr></thead>
                <tbody>
                @forelse ($all as $c)
                    <tr wire:key="row-{{ $c['id'] }}">
                        <td><a href="{{ url('/academia/curso/'.$c['slug']) }}"><b>{{ $c['title'] }}</b></a></td>
                        <td><x-academy.bar :percent="$c['percent']" :label="$c['lessons_done'].'/'.$c['lessons_total']" style="width:100%" /></td>
                        <td>@if (! is_null($c['nota'])) <x-academy.bar :percent="$c['nota'] * 10" :label="$nota($c['nota'])" style="min-width:90px" /> @else — @endif</td>
                        <td>{{ $c['quiz_total'] ? $c['quiz_correct'].' de '.$c['quiz_answered'].' (de '.$c['quiz_total'].')' : '—' }}</td>
                        <td>{{ $fmtTime($c['minutes']) }}</td>
                        <td>{{ $c['points'] }}</td>
                        <td>{{ $c['certificate'] ? '🎓 '.$c['certificate']->code : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="color:var(--muted)">Nenhum curso liberado no seu contrato ainda.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($certificates->isNotEmpty())
        <div class="ac-card">
            <h2>🎓 Meus certificados</h2>
            @foreach ($certificates as $c)
                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:8px 0;border-bottom:1px solid #f0f3f9">
                    <span><b>{{ $c['title'] }}</b> · concluído em {{ $c['certificate']->issued_at->format('d/m/Y') }}</span>
                    <a href="{{ url('/academia/curso/'.$c['slug']) }}">Abrir e baixar ↗</a>
                </div>
            @endforeach
        </div>
    @endif
</div>
