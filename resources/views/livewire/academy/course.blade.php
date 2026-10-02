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
            @if (! is_null($nota)) <x-academy.bar :percent="$nota * 10" :label="$summary['quiz_correct'].' de '.$summary['quiz_answered'].' certas'" style="width:100%" /> @elseif ($summary['quiz_total']) <em>responda o quiz para ter nota</em> @else <em>este curso não tem quiz</em> @endif</div>
        <div class="ac-kpi"><small>Tempo de estudo</small><strong>{{ $fmtTime($summary['minutes']) }}</strong><em>tempo ativo neste curso</em></div>
        <div class="ac-kpi"><small>Pontos no curso</small><strong>{{ $summary['points'] }}</strong><em>leitura, quiz, tempo e bônus</em></div>
    </div>

    @if ($certificate)
        <div class="ac-cert" data-testid="certificate">
            <span>🎓 <b>Curso concluído!</b> Código do certificado: <b>{{ $certificate->code }}</b></span>
            <button type="button" class="ac-btn" wire:click="downloadCertificate">Baixar certificado (PDF)</button>
        </div>
    @elseif ($summary['status'] === 'done' && $summary['quiz_total'])
        <div class="ac-alert">Você concluiu todas as aulas. Para receber o certificado, acerte todas as perguntas do quiz.</div>
    @endif

    <div class="ac-course-grid">
        <aside class="ac-card ac-lessons">
            <div style="padding:6px 10px 10px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)">Aulas ({{ $lessons->count() }})</div>
            @foreach ($lessons as $i => $lesson)
                <button type="button" wire:key="ls-{{ $lesson->id }}" wire:click="selectLesson('{{ $lesson->id }}')" class="ac-lesson {{ $current?->id === $lesson->id ? 'on' : '' }}">
                    <span class="ac-dot {{ $done->has($lesson->id) ? 'done' : ($current?->id === $lesson->id ? 'cur' : '') }}">{{ $done->has($lesson->id) ? '✓' : $i + 1 }}</span>
                    <span>{{ $lesson->title }}@if ($lesson->questions->isNotEmpty()) <small style="color:var(--muted)"> · quiz</small>@endif</span>
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
                    <div class="ac-body" style="margin-top:14px">{!! \Illuminate\Support\Str::of($current->body)->sanitizeHtml() !!}</div>
                @endif

                <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:14px;font-size:14px">
                    @if ($current->page_url) <a href="{{ $current->page_url }}" target="_blank" rel="noopener">📖 Ler o guia completo ↗</a> @endif
                    @if ($current->attachment_path) <a href="{{ \Illuminate\Support\Facades\Storage::url($current->attachment_path) }}" target="_blank" rel="noopener">📎 Baixar anexo (PDF)</a> @endif
                </div>

                @if ($current->questions->isNotEmpty())
                    <div class="ac-quiz" data-testid="quiz">
                        <b>Teste o que aprendeu</b>
                        @foreach ($current->questions as $q)
                            @php $fb = $feedback[$q->id] ?? null; @endphp
                            <div class="ac-q" wire:key="q-{{ $q->id }}" style="margin-top:14px">
                                <div style="font-weight:600;font-size:14.5px;margin-bottom:4px">{{ $loop->iteration }}. {{ $q->question }}</div>
                                @foreach ($q->optionTexts() as $i => $text)
                                    <label><input type="radio" wire:model="selected.{{ $q->id }}" value="{{ $i }}" name="q-{{ $q->id }}"> <span>{{ $text }}</span></label>
                                @endforeach
                                <div style="display:flex;gap:12px;align-items:center;margin-top:6px">
                                    <button type="button" class="ac-btn ghost" style="padding:7px 14px" wire:click="answer('{{ $q->id }}')">Responder</button>
                                    @if ($fb)
                                        <span class="{{ $fb['correct'] ? 'ac-ok' : 'ac-bad' }}">{{ $fb['correct'] ? '✓ Correto!'.($fb['gained'] ? " (+{$fb['gained']} pontos)" : '') : '✗ Não foi dessa vez, tente de novo.' }}</span>
                                    @elseif ($answered->get($q->id))
                                        <span class="ac-ok">✓ Você já acertou esta.</span>
                                    @endif
                                </div>
                                @if ($fb && $fb['correct'] && $q->explanation) <div style="font-size:12.5px;color:var(--muted);margin-top:4px">{{ $q->explanation }}</div> @endif
                            </div>
                        @endforeach
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
