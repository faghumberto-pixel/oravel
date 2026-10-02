<x-filament-panels::page>
    @php
        $lessons = $this->lessons();
        $done = $this->doneIds();
        $total = $lessons->count();
        $count = count($done);
        $pct = $total ? (int) round($count / $total * 100) : 0;
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <span class="rounded-full bg-primary-50 px-3 py-1 font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-300" data-testid="points">
            ⭐ {{ number_format($this->points(), 0, ',', '.') }} pontos
        </span>
        <a href="{{ \App\Filament\Resources\CourseResource::getUrl('ranking') }}" class="text-primary-600 underline">Ranking da empresa</a>
    </div>

    @if ($record->description)
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $record->description }}</p>
    @endif

    <div>
        <div class="mb-1 flex justify-between text-sm">
            <span class="font-medium">Seu progresso</span>
            <span data-testid="progress-label">{{ $count }} de {{ $total }} aulas ({{ $pct }}%)</span>
        </div>
        <x-academy.bar :percent="$pct" :height="10" />
    </div>

    @if ($certificate = $this->certificate())
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;padding:12px;font-size:14px" data-testid="certificate">
            <span>🎓 Você concluiu este curso. Código do certificado: <strong>{{ $certificate->code }}</strong></span>
            <x-filament::button size="sm" wire:click="downloadCertificate">Baixar certificado (PDF)</x-filament::button>
        </div>
    @endif

    @if ($total > 0 && $count >= $total)
        <div style="border-radius:8px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px;font-size:14px" data-testid="course-done">
            ✓ Curso concluído. Você pode rever qualquer aula quando quiser — o conteúdo continua aberto.
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($lessons as $i => $lesson)
            @php $isDone = isset($done[$lesson->id]); @endphp
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" wire:key="lesson-{{ $lesson->id }}">
                <div class="flex items-center gap-3 p-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold {{ $isDone ? 'bg-success-600 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">
                        {{ $isDone ? '✓' : $i + 1 }}
                    </span>
                    <button type="button" wire:click="open('{{ $lesson->id }}')" class="flex-1 text-left">
                        <span class="block font-medium">{{ $lesson->title }}</span>
                        @if ($lesson->summary)
                            <span class="block text-sm text-gray-500">{{ $lesson->summary }}</span>
                        @endif
                    </button>
                    <x-filament::button size="sm" :color="$isDone ? 'gray' : 'primary'" wire:click="toggleDone('{{ $lesson->id }}')">
                        {{ $isDone ? 'Desmarcar' : 'Concluir aula' }}
                    </x-filament::button>
                </div>

                @if ($this->openLesson === $lesson->id)
                    <div class="space-y-4 border-t border-gray-200 p-4 dark:border-gray-700"
                         x-data="{ beat: null }"
                         x-init="beat = setInterval(() => { if (! $el.isConnected) { clearInterval(beat); return } if (document.visibilityState === 'visible') { $wire.heartbeat('{{ $lesson->id }}') } }, 30000)">
                        @if ($embed = $lesson->embedUrl())
                            <div class="aspect-video w-full overflow-hidden rounded-lg">
                                <iframe src="{{ $embed }}" class="h-full w-full" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                            </div>
                        @endif

                        @if ($lesson->body)
                            <div class="prose max-w-none dark:prose-invert">{!! \Illuminate\Support\Str::of($lesson->body)->sanitizeHtml() !!}</div>
                        @endif

                        @php $questions = $lesson->questions; $answered = $this->answered(); @endphp
                        @if ($questions->isNotEmpty())
                            <div class="space-y-4 rounded-lg bg-gray-50 p-4 dark:bg-gray-800" data-testid="quiz">
                                <p class="font-semibold">Teste o que aprendeu</p>
                                @foreach ($questions as $q)
                                    @php
                                        $fb = $this->feedback[$q->id] ?? null;
                                        $already = array_key_exists($q->id, $answered);
                                    @endphp
                                    <div class="space-y-2" wire:key="q-{{ $q->id }}">
                                        <p class="text-sm font-medium">{{ $loop->iteration }}. {{ $q->question }}</p>
                                        @foreach ($q->optionTexts() as $i => $text)
                                            <label class="flex items-center gap-2 text-sm">
                                                <input type="radio" wire:model="selected.{{ $q->id }}" value="{{ $i }}" name="q-{{ $q->id }}">
                                                <span>{{ $text }}</span>
                                            </label>
                                        @endforeach
                                        <div class="flex items-center gap-3">
                                            <x-filament::button size="xs" color="gray" wire:click="answerQuestion('{{ $q->id }}')">Responder</x-filament::button>
                                            @if ($fb)
                                                <span class="text-sm {{ $fb['correct'] ? 'text-success-600' : 'text-danger-600' }}">
                                                    {{ $fb['correct'] ? '✓ Correto!'.($fb['gained'] ? " (+{$fb['gained']} pontos)" : '') : '✗ Não foi dessa vez, tente de novo.' }}
                                                </span>
                                            @elseif ($already && ($answered[$q->id] ?? false))
                                                <span class="text-sm text-success-600">✓ Você já acertou esta.</span>
                                            @endif
                                        </div>
                                        @if ($fb && $fb['correct'] && $q->explanation)
                                            <p class="text-xs text-gray-500">{{ $q->explanation }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-3 text-sm">
                            @if ($lesson->page_url)
                                <a href="{{ $lesson->page_url }}" target="_blank" rel="noopener" class="text-primary-600 underline">Ler o guia completo ↗</a>
                            @endif
                            @if ($lesson->attachment_path)
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($lesson->attachment_path) }}" target="_blank" rel="noopener" class="text-primary-600 underline">Baixar anexo (PDF)</a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500">Este curso ainda não tem aulas.</p>
        @endforelse
    </div>
</x-filament-panels::page>
