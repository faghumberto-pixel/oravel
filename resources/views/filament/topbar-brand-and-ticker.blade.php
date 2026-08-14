{{-- Nome do tenant + relogio + ticker de avisos no topbar -- o logo NAO
     entra mais aqui (2026-08): com a sidebar ativa (nao topNavigation()),
     o Filament ja renderiza o brand-logo nativamente no header da propria
     sidebar; incluir de novo aqui duplicava o logo (sidebar + topbar) e
     ficava desalinhado. O nome do tenant tambem saiu do header da sidebar
     (era estreito demais pra logo + nome sem cortar o botao de colapsar)
     e migrou pra ca, que sobrou livre depois que o logo saiu. --}}
@php
    $tenant = \App\Support\Tenancy::current();
    $segmentLabel = $tenant?->segment ? (\App\Models\Client::nicheLabels()[$tenant->segment] ?? null) : null;
    $segmentDotClass = match ($tenant?->segment) {
        \App\Models\Client::NICHE_EVENTOS => 'bg-violet-500',
        \App\Models\Client::NICHE_CONSTRUCAO_CIVIL => 'bg-sky-500',
        \App\Models\Client::NICHE_INDUSTRIAL_HOSPITALAR => 'bg-teal-500',
        default => 'bg-gray-500',
    };
@endphp
@if($tenant)
    <div class="flex flex-col leading-tight shrink-0">
        <span class="text-xs font-bold tracking-tight text-primary-500 truncate max-w-[16rem]">{{ $tenant->name }}</span>
        @if($segmentLabel)
            <span class="flex items-center gap-1.5 text-[11px] font-medium tracking-wide text-gray-400 truncate max-w-[16rem]">
                <span class="inline-block h-1.5 w-1.5 rounded-full {{ $segmentDotClass }} shrink-0"></span>
                {{ $segmentLabel }}
            </span>
        @endif
    </div>
@endif

<div class="flex items-center">
    <span
        x-data="{ now: new Date() }"
        x-init="setInterval(() => now = new Date(), 1000)"
        x-text="now.toLocaleString('pt-BR', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' })"
        class="text-[11px] font-medium capitalize text-gray-300"
    ></span>
</div>

@php
    // Mesma fonte/logica de resources/views/filament/announcement-banner.blade.php
    // (agora aposentado -- os avisos saem do banner de pagina inteira e
    // passam a aparecer aqui dentro do topbar).
    $announcements = auth()->check()
        ? \App\Models\Announcement::activeFor(\App\Support\Tenancy::current()?->id)
        : collect();

    $tickerItems = $announcements->map(fn ($a) => [
        'id' => $a->id,
        'title' => $a->title,
        'message' => $a->message,
    ])->values();
@endphp

@if ($tickerItems->isNotEmpty())
    <div
        x-data='{
            items: @json($tickerItems),
            current: 0,
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
        class="flex min-w-0 shrink items-center"
    >
        <template x-for="item in (active ? [active] : [])" :key="item.id">
            <div
                x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0 -translate-x-3"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 translate-x-3"
                class="flex min-w-0 items-center gap-x-2 text-xs font-medium text-amber-400"
            >
                <span class="truncate">
                    <strong x-text="item.title"></strong>: <span x-text="item.message"></span>
                </span>

                <button
                    type="button"
                    x-on:click="dismiss(item.id)"
                    class="shrink-0 font-bold opacity-60 hover:opacity-100"
                >
                    ✕
                </button>
            </div>
        </template>
    </div>
@endif
