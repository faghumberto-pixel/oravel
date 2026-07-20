<div class="fi-resource-relation-manager flex flex-col gap-y-6">
    <x-filament-panels::resources.tabs />

    {{--
        Registro rapido: digita e aperta Enter, sem abrir modal, sem
        preencher canal/data manualmente -- data/hora fica automatica
        (now(), no metodo addQuickNote()). Pedido explicito do usuario.
        Shift+Enter continua permitindo quebra de linha normal.
    --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
        <label class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2 block">
            Registro Rápido de Follow Up
        </label>
        <textarea
            wire:model="quickNote"
            wire:keydown.enter.prevent="addQuickNote"
            rows="4"
            placeholder="Descreva como foi o contato e aperte Enter pra registrar (data e hora ficam automáticas)... Shift+Enter pra quebrar linha."
            class="fi-input block w-full rounded-lg border-none bg-white text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:focus:ring-primary-500 p-3"
        ></textarea>
        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1.5">Enter registra agora · Shift+Enter quebra linha</p>
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_RELATION_MANAGER_BEFORE, scopes: $this->getRenderHookScopes()) }}

    {{ $this->table }}

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_RELATION_MANAGER_AFTER, scopes: $this->getRenderHookScopes()) }}

    <x-filament-panels::unsaved-action-changes-alert />
</div>
