<x-filament-panels::page>
    @php
        $lessons = $this->lessons();
        $done = $this->doneIds();
        $total = $lessons->count();
        $count = count($done);
        $pct = $total ? (int) round($count / $total * 100) : 0;
    @endphp

    @if ($record->description)
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $record->description }}</p>
    @endif

    <div>
        <div class="mb-1 flex justify-between text-sm">
            <span class="font-medium">Seu progresso</span>
            <span data-testid="progress-label">{{ $count }} de {{ $total }} aulas ({{ $pct }}%)</span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div class="h-2 rounded-full bg-primary-600" style="width: {{ $pct }}%"></div>
        </div>
    </div>

    @if ($total > 0 && $count >= $total)
        <div class="rounded-lg bg-success-50 p-3 text-sm text-success-700 dark:bg-success-950 dark:text-success-300" data-testid="course-done">
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
                    <div class="space-y-4 border-t border-gray-200 p-4 dark:border-gray-700">
                        @if ($embed = $lesson->embedUrl())
                            <div class="aspect-video w-full overflow-hidden rounded-lg">
                                <iframe src="{{ $embed }}" class="h-full w-full" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                            </div>
                        @endif

                        @if ($lesson->body)
                            <div class="prose max-w-none dark:prose-invert">{!! \Illuminate\Support\Str::of($lesson->body)->sanitizeHtml() !!}</div>
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
