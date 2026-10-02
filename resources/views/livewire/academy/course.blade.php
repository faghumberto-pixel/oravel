@php
    $fmtTime = fn ($m) => $m >= 60 ? intdiv($m, 60).' h '.($m % 60).' min' : $m.' min';
    $nota = $summary['nota'];
    $embed = $current?->embedUrl();
    $isDone = $current ? $done->has($current->id) : false;
@endphp
<div>
    <section class="ac-hero sm">
        <div class="ac-crumb"><a href="{{ url('/academia') }}">Academia</a> / {{ $course->title }}</div>
        <div class="ac-hero-row">
            <div>
                <h1>{{ $course->title }}</h1>
                @if ($course->description) <p>{{ $course->description }}</p> @endif
            </div>
            <span class="ac-badge {{ $summary['status'] }}" style="font-size:13px">{{ ['todo' => 'A fazer', 'progress' => 'Em andamento', 'done' => 'Concluído'][$summary['status']] }}</span>
        </div>
    </section>

    @if ($readOnly)
        <div class="ac-alert">Você está vendo a Academia como administrador da plataforma: o progresso só é registrado para usuários de clientes.</div>
    @endif

    <div class="ac-kpis" data-testid="course-kpis">
        <div class="ac-kpi"><small>Progresso</small><strong>{{ $summary['percent'] }}%</strong>
            <x-academy.bar :percent="$summary['percent']" :label="$summary['lessons_done'].' de '.$summary['lessons_total'].' aulas'" style="width:100%" /></div>
        <div class="ac-kpi"><small>Nota</small><strong>{{ is_null($nota) ? '—' : number_format($nota, 1, ',', '') }}</strong>
            @if (! is_null($nota)) <x-academy.bar :percent="$nota * 10" :label="$summary['quiz_correct'].' de '.$summary['quiz_graded'].' certas'" style="width:100%" /><em>provas entregues: {{ $summary['quizzes_delivered'] }} de {{ $summary['quizzes_total'] }}</em> @elseif ($summary['quizzes_total']) <em>entregue as provas para ter nota ({{ $summary['quizzes_total'] }} no curso)</em> @else <em>este curso não tem prova</em> @endif</div>
        <div class="ac-kpi"><small>Tempo de estudo</small><strong>{{ $fmtTime($summary['minutes']) }}</strong><em>tempo ativo neste curso</em></div>
        <div class="ac-kpi"><small>Pontos no curso</small><strong>{{ $summary['points'] }}</strong><em>leitura, quiz, tempo e bônus</em></div>
    </div>

    @if ($certificate)
        <div class="ac-cert" data-testid="certificate">
            <span>🎓 <b>Curso concluído!</b> Código do certificado: <b>{{ $certificate->code }}</b></span>
            <button type="button" class="ac-btn" wire:click="downloadCertificate">Baixar certificado (PDF)</button>
        </div>
    @elseif ($summary['status'] === 'done' && $summary['quizzes_total'])
        <div class="ac-alert">
            @if ($summary['quizzes_delivered'] < $summary['quizzes_total'])
                Você concluiu as aulas. Para receber o certificado, entregue a prova das {{ $summary['quizzes_total'] - $summary['quizzes_delivered'] }} aula(s) que faltam. Questão em branco vale zero.
            @else
                Sua nota no curso ({{ number_format($summary['nota'], 1, ',', '') }}) ficou abaixo da nota mínima ({{ number_format(config('oravel.academy.passing_grade'), 1, ',', '') }}) para o certificado. As provas já entregues não podem ser refeitas.
            @endif
        </div>
    @endif

    <div class="ac-course-grid">
        <aside class="ac-card ac-lessons">
            <div style="padding:6px 10px 10px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)">Aulas ({{ $lessons->count() }})</div>
            @foreach ($lessons as $i => $lesson)
                <button type="button" wire:key="ls-{{ $lesson->id }}" wire:click="selectLesson('{{ $lesson->id }}')" class="ac-lesson {{ $current?->id === $lesson->id ? 'on' : '' }}">
                    <span class="ac-dot {{ $done->has($lesson->id) ? 'done' : ($current?->id === $lesson->id ? 'cur' : '') }}">{{ $done->has($lesson->id) ? '✓' : $i + 1 }}</span>
                    <span>{{ $lesson->title }}@if ($lesson->questions->isNotEmpty()) <small style="color:var(--muted)"> · {{ $submissions->has($lesson->id) ? 'prova entregue ✓' : 'prova' }}</small>@endif</span>
                </button>
            @endforeach
        </aside>

        <section class="ac-card ac-viewer" data-testid="viewer"
                 x-data="{ beat: null }"
                 x-init="beat = setInterval(() => { if (! $el.isConnected) { clearInterval(beat); return } if (document.visibilityState === 'visible') { $wire.heartbeat() } }, 30000)">
            @if ($current)
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap">
                    <div>
                        <small style="color:var(--muted)">Aula {{ $lessons->search(fn ($l) => $l->id === $current->id) + 1 }} de {{ $lessons->count() }}</small>
                        <h2>{{ $current->title }}</h2>
                    </div>
                    <button type="button" class="ac-btn {{ $isDone ? 'ghost' : 'ok' }}" wire:click="toggleDone('{{ $current->id }}')">
                        {{ $isDone ? '✓ Concluída (desmarcar)' : 'Concluir aula' }}
                    </button>
                </div>
                @if ($current->summary) <p style="color:var(--muted);margin:4px 0 0">{{ $current->summary }}</p> @endif

                @if ($embed)
                    <div class="ac-video"><iframe src="{{ $embed }}" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
                @else
                    <div class="ac-video-soon" data-testid="video-soon">
                        <div style="font-size:38px;line-height:1">🎬</div>
                        <b>Vídeo em produção</b>
                        <span>O vídeo desta aula estará disponível em breve. Enquanto isso, use o texto e o guia completo abaixo.</span>
                    </div>
                @endif

                @if ($current->body)
                    <div class="ac-body" style="margin-top:14px">{!! \App\Support\LessonHtml::clean($current->body) !!}</div>
                @endif

                <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:14px;font-size:14px">
                    @if ($current->attachment_path) <a href="{{ \Illuminate\Support\Facades\Storage::url($current->attachment_path) }}" target="_blank" rel="noopener">📎 Baixar anexo (PDF)</a> @endif
                </div>

                @if ($current->questions->isNotEmpty())
                    @php $sub = $submissions->get($current->id); @endphp
                    <div class="ac-quiz" data-testid="quiz">
                        @if ($sub)
                            {{-- PROVA ENTREGUE: gabarito. Resposta certa em verde, a escolhida errada em vermelho, em branco = zero. --}}
                            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
                                <b>Gabarito da prova</b>
                                <span class="ac-badge {{ $sub->grade() >= config('oravel.academy.passing_grade') ? 'done' : 'progress' }}" data-testid="grade">
                                    {{ $sub->correct_answers }} de {{ $sub->total_questions }} · nota {{ number_format($sub->grade(), 1, ',', '') }}
                                </span>
                            </div>
                            @foreach ($current->questions as $q)
                                @php
                                    $given = $answers->get($q->id);
                                    $chosen = $given?->selected_index;
                                    $hit = (bool) $given?->is_correct;
                                @endphp
                                <div class="ac-q" wire:key="g-{{ $q->id }}" style="margin-top:14px" data-testid="gabarito-q">
                                    <div style="font-weight:600;font-size:14.5px;margin-bottom:4px">
                                        {{ $loop->iteration }}. {{ $q->question }}
                                        <span class="{{ $hit ? 'ac-ok' : 'ac-bad' }}">{{ $hit ? '✓ acertou' : (is_null($chosen) ? '✗ em branco (zero)' : '✗ errou') }}</span>
                                    </div>
                                    @foreach ($q->optionTexts() as $i => $text)
                                        @php
                                            $isRight = $i === (int) $q->correct_index;
                                            $isMine = ! is_null($chosen) && $i === (int) $chosen;
                                            $bg = $isRight ? '#dcfce7' : ($isMine ? '#fee2e2' : 'transparent');
                                            $bd = $isRight ? '#86efac' : ($isMine ? '#fca5a5' : 'transparent');
                                        @endphp
                                        <div style="padding:7px 10px;border-radius:9px;border:1px solid {{ $bd }};background:{{ $bg }};font-size:14px;margin:3px 0">
                                            {{ $isRight ? '✓' : ($isMine ? '✗' : '○') }} {{ $text }}
                                            @if ($isRight) <small style="color:#166534"> · resposta certa</small>@endif
                                            @if ($isMine && ! $isRight) <small style="color:#991b1b"> · sua resposta</small>@endif
                                        </div>
                                    @endforeach
                                    @if ($q->explanation) <div style="font-size:12.5px;color:var(--muted);margin-top:4px">💡 {{ $q->explanation }}</div> @endif
                                </div>
                            @endforeach
                        @else
                            {{-- PROVA PENDENTE: responde tudo e entrega; só então mostra o gabarito. --}}
                            <b>Prova desta aula ({{ $current->questions->count() }} {{ $current->questions->count() == 1 ? 'pergunta' : 'perguntas' }})</b>
                            <p style="margin:4px 0 0;font-size:13px;color:var(--muted)">Responda e entregue. O gabarito aparece assim que você entregar. Questão em branco vale zero e a prova não pode ser refeita.</p>
                            @foreach ($current->questions as $q)
                                <div class="ac-q" wire:key="q-{{ $q->id }}" style="margin-top:14px">
                                    <div style="font-weight:600;font-size:14.5px;margin-bottom:4px">{{ $loop->iteration }}. {{ $q->question }}</div>
                                    @foreach ($q->optionTexts() as $i => $text)
                                        <label><input type="radio" wire:model="selected.{{ $q->id }}" value="{{ $i }}" name="q-{{ $q->id }}"> <span>{{ $text }}</span></label>
                                    @endforeach
                                </div>
                            @endforeach
                            <button type="button" class="ac-btn" style="margin-top:6px" wire:click="deliverQuiz('{{ $current->id }}')"
                                    wire:confirm="Entregar a prova? Depois de entregue não dá para refazer, e as questões em branco valem zero.">Entregar prova</button>
                        @endif
                    </div>
                @endif

                <div class="ac-nav-btns">
                    @if ($previous) <button type="button" class="ac-btn ghost" wire:click="selectLesson('{{ $previous->id }}')">← {{ \Illuminate\Support\Str::limit($previous->title, 30) }}</button> @else <span></span> @endif
                    @if ($next) <button type="button" class="ac-btn" wire:click="selectLesson('{{ $next->id }}')">{{ \Illuminate\Support\Str::limit($next->title, 30) }} →</button> @endif
                </div>
            @else
                <p style="color:var(--muted)">Este curso ainda não tem aulas liberadas para o seu contrato.</p>
            @endif
        </section>
    </div>

    @if ($toast)
        <div class="ac-toast" x-data x-init="setTimeout(() => $wire.set('toast', null), 4500)" wire:key="toast-{{ md5($toast) }}-{{ now()->timestamp }}">{{ $toast }}</div>
    @endif
</div>
