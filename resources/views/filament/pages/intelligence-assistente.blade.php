<x-filament-panels::page>
    @if ($this->podeUsarChat())
        <x-filament::section heading="Converse com os seus números" description="Pergunte em português simples. O assistente lê só a camada de inteligência da sua empresa (fotografias, indicadores, diagnóstico e histórico) e nunca altera nada.">
            <livewire:assistente.intelligence-chat :embutido="true" />
        </x-filament::section>
    @else
        <x-filament::section>
            <p style="margin:0;opacity:.8">O assistente é do cliente: entre com um usuário da empresa para conversar com os dados dela.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
