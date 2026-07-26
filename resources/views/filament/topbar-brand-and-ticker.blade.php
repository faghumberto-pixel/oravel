{{-- Conteudo da linha 1 (topbar de verdade) -- o wrapper flex/sticky fica
     no template que sobrescreve o topbar do Filament, ver
     resources/views/vendor/filament-panels/components/topbar/index.blade.php.
     Nao precisa de JS nem de wrapper proprio aqui: por nao ser sticky,
     "some ao rolar pra baixo" e' scroll comum. Espacamento entre logo/
     relogio/avisos vem do "gap" em CSS puro no .fi-oravel-topbar-row1
     (brand-header-background.blade.php), nao de classes Tailwind tipo
     "me-10" que nao existem no CSS compilado deste ambiente. --}}
<a href="{{ filament()->getUrl() }}" class="flex items-center">
    @include('filament.brand-logo')
</a>

<div class="flex items-center">
    <span
        x-data="{ now: new Date() }"
        x-init="setInterval(() => now = new Date(), 1000)"
        x-text="now.toLocaleString('pt-BR', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' })"
        class="text-sm font-medium capitalize text-gray-300"
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
                class="flex min-w-0 items-center gap-x-2 text-sm font-medium text-amber-400"
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
