<div class="fi-resource-relation-manager flex flex-col gap-y-6">
    <x-filament-panels::resources.tabs />

    @php($items = $this->getTimelineItems())

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
        @if ($items->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nenhum contato ou compromisso registrado ainda para este lead.
            </p>
        @else
            <ul class="relative flex flex-col gap-y-6">
                @foreach ($items as $item)
                    <li class="relative flex gap-x-4">
                        {{-- Linha vertical conectando os pontos, exceto no último item --}}
                        @if (!$loop->last)
                            <span class="absolute left-[15px] top-8 -bottom-6 w-px bg-gray-200 dark:bg-white/10"></span>
                        @endif

                        <span
                            @class([
                                'relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-900',
                                'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $item['color'] === 'gray',
                                'bg-success-100 text-success-600 dark:bg-success-500/10 dark:text-success-400' => $item['color'] === 'success',
                                'bg-info-100 text-info-600 dark:bg-info-500/10 dark:text-info-400' => $item['color'] === 'info',
                                'bg-warning-100 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400' => $item['color'] === 'warning',
                                'bg-danger-100 text-danger-600 dark:bg-danger-500/10 dark:text-danger-400' => $item['color'] === 'danger',
                            ])
                        >
                            <x-filament::icon :icon="$item['icon']" class="h-4 w-4" />
                        </span>

                        <div class="flex min-w-0 flex-1 flex-col gap-y-1 pb-1">
                            <div class="flex flex-wrap items-center gap-x-2">
                                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $item['title'] }}
                                </span>

                                <span
                                    @class([
                                        'fi-badge inline-flex items-center gap-x-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                        'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20' => $item['color'] === 'gray',
                                        'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20' => $item['color'] === 'success',
                                        'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400 dark:ring-info-500/20' => $item['color'] === 'info',
                                        'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20' => $item['color'] === 'warning',
                                        'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-500/20' => $item['color'] === 'danger',
                                    ])
                                >
                                    {{ $item['meta'] }}
                                </span>

                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ $item['kind'] === 'appointment' ? 'Compromisso' : 'Follow Up' }}
                                </span>
                            </div>

                            @if ($item['body'])
                                <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre-line">
                                    {{ $item['body'] }}
                                </p>
                            @endif

                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $item['at']?->format('d/m/Y H:i') ?? '—' }}
                                @if ($item['author'])
                                    · {{ $item['author'] }}
                                @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
