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

    // Cor de fundo por severidade (pedido do usuario 2026-09-25, revertendo
    // a decisao de 23/09 de fundo cinza-escuro fixo pros 3 niveis): VERDE
    // pra Aviso/info, AMARELO pra Atencao/warning, VERMELHO pra Critico --
    // cores solidas (nao so' o texto), pra dar pra reconhecer a severidade
    // so' de relance, sem precisar ler o titulo.
    $levelClasses = [
        \App\Models\Announcement::LEVEL_CRITICAL => ['border-red-800', 'bg-red-600', 'text-white'],
        \App\Models\Announcement::LEVEL_WARNING => ['border-yellow-600', 'bg-yellow-400', 'text-gray-900'],
        \App\Models\Announcement::LEVEL_INFO => ['border-green-800', 'bg-green-600', 'text-white'],
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
