@php
    // BODY_START roda em toda pagina do painel, inclusive a de login (sem
    // usuario autenticado) -- sem essa checagem, um aviso global (target
    // nulo) aparecia pra visitante nao logado, o que nao faz sentido.
    $announcements = auth()->check()
        ? \App\Models\Announcement::activeFor(\App\Support\Tenancy::current()?->id)
        : collect();

    $items = $announcements->map(fn ($a) => [
        'id' => $a->id,
        'title' => $a->title,
        'message' => $a->message,
        'level' => $a->level ?? 'info',
    ])->values();
@endphp

@if ($items->isNotEmpty())
    <div
        x-data='{
            items: @json($items),
            current: 0,
            // Dispensar nao persiste (sem localStorage/sessao) de proposito:
            // avisos da gestao do SaaS devem voltar a aparecer a cada reload
            // ate serem desativados na Central.
            dismissedIds: [],
            get visible() {
                return this.items.filter(item => ! this.dismissedIds.includes(item.id));
            },
            get active() {
                return this.visible[this.current] ?? null;
            },
            dismiss(id) {
                this.dismissedIds.push(id);
                if (this.current >= this.visible.length) {
                    this.current = 0;
                }
            },
            next() {
                if (this.visible.length > 1) {
                    this.current = (this.current + 1) % this.visible.length;
                }
            },
            init() {
                setInterval(() => this.next(), 7000);
            },
        }'
        x-show="active"
        class="relative w-full overflow-hidden"
    >
        <template x-for="item in (active ? [active] : [])" :key="item.id">
            <div
                x-transition:enter="transition ease-out duration-500 delay-200"
                x-transition:enter-start="opacity-0 -translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 translate-x-6"
                class="flex w-full items-start justify-center gap-x-4 border-b px-4 py-2 text-center text-sm"
                :class="{
                    'border-danger-300 bg-danger-50 text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-300': item.level === 'critical',
                    'border-warning-300 bg-warning-50 text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300': item.level === 'warning',
                    'border-info-300 bg-info-50 text-info-800 dark:border-info-500/30 dark:bg-info-500/10 dark:text-info-300': item.level !== 'critical' && item.level !== 'warning',
                }"
            >
                <div>
                    <strong x-text="item.title"></strong>
                    <span x-text="item.message"></span>
                </div>

                <button
                    type="button"
                    x-on:click="dismiss(item.id)"
                    class="shrink-0 font-bold opacity-60 hover:opacity-100"
                >
                    ✕
                </button>
            </div>
        </template>

        <div x-show="visible.length > 1" class="flex items-center justify-center gap-x-1.5 pb-1.5">
            <template x-for="(item, index) in visible" :key="'dot-' + item.id">
                <span
                    class="h-1.5 w-1.5 rounded-full bg-current transition-opacity"
                    :class="index === current ? 'opacity-80' : 'opacity-30'"
                ></span>
            </template>
        </div>
    </div>
@endif
