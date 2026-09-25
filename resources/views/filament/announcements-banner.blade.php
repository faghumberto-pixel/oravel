{{-- Avisos criados no painel Central (2026-09-18, pedido do usuario): banner
     de largura total logo abaixo do topbar, mesmo padrao visual do banner
     "Nenhum tenant selecionado" (acting-tenant-banner.blade.php) -- antes
     apareciam como texto pequeno centralizado no topbar
     (topbar-announcements-ticker.blade.php, agora aposentado), pedido
     explicito foi ficar "como a de cima" (o banner amarelo).

     Um de cada vez (2026-09-18, pedido do usuario -- "nao deve ter uma em
     cima da outra"): com 2+ avisos ativos, empilhavam um banner por baixo
     do outro. Agora e' 1 unico container que alterna item a cada 6s
     (mesmo intervalo do ticker antigo no topbar), como um carrossel.

     Dispensa e' so' de estado local (NAO Alpine $persist/localStorage) --
     precisa reaparecer a cada refresh ou troca de tela. Dispensar o item
     atual so' pula pro proximo (ou esconde o banner inteiro se so' sobrar
     1). --}}
@php
    $announcements = auth()->check()
        ? \App\Models\Announcement::activeFor(\App\Support\Tenancy::current()?->id)
        : collect();

    // Fundo cinza-escuro fixo (correção 2026-09-25: o pedido era só sobre a
    // COR DA FONTE, o fundo continua como estava desde 23/09, não ligado ao
    // tema claro/escuro do painel). Cor do texto por severidade: VERDE pra
    // Aviso/info, AMARELO pra Atenção/warning, VERMELHO pra Crítico.
    $levelClasses = [
        \App\Models\Announcement::LEVEL_CRITICAL => ['border-gray-700', 'bg-gray-800', 'text-red-400'],
        \App\Models\Announcement::LEVEL_WARNING => ['border-gray-700', 'bg-gray-800', 'text-yellow-400'],
        \App\Models\Announcement::LEVEL_INFO => ['border-gray-700', 'bg-gray-800', 'text-green-400'],
    ];

    $items = $announcements->map(fn ($a) => [
        'id' => $a->id,
        'title' => $a->title,
        'message' => $a->message,
        'classes' => implode(' ', $levelClasses[$a->level] ?? $levelClasses[\App\Models\Announcement::LEVEL_INFO]),
    ])->values();
@endphp

@if ($items->isNotEmpty())
    <div
        x-data='{
            items: @json($items),
            current: 0,
            dismissedIds: [],
            get visible() {
                return this.items.filter(item => ! this.dismissedIds.includes(item.id));
            },
            get active() {
                return this.visible[this.current % this.visible.length] ?? null;
            },
            dismiss(id) {
                this.dismissedIds.push(id);
                this.current = 0;
            },
            next() {
                if (this.visible.length > 1) {
                    this.current = (this.current + 1) % this.visible.length;
                }
            },
            init() {
                setInterval(() => this.next(), 6000);
            },
        }'
        x-show="active"
    >
        <template x-if="active">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-bind:class="active.classes"
                class="fi-oravel-announcement-banner w-full border-b px-4 py-2 text-center text-sm"
            >
                <strong x-text="active.title"></strong>
                <span x-text="active.message"></span>
                <button
                    type="button"
                    x-on:click="dismiss(active.id)"
                    class="ms-2 font-semibold underline"
                >
                    Dispensar
                </button>
            </div>
        </template>
    </div>
@endif
