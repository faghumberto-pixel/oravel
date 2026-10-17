<x-filament-panels::page>
    <form wire:submit="salvar" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" color="gray">Salvar</x-filament::button>
            <x-filament::button type="button" wire:click="testarEAtivar" color="success" icon="heroicon-o-check-circle">Testar conexão e ligar</x-filament::button>
            @if($this->config()?->enabled)
                <x-filament::button type="button" wire:click="desligar" color="danger" outlined>Desligar</x-filament::button>
            @endif
        </div>
    </form>

    @if($this->config()?->api_key)
        <x-filament::section heading="Para receber os pagamentos" description="No Asaas, em Integrações → Webhooks, crie um webhook com os dados abaixo e marque os eventos de cobrança (confirmada, recebida, vencida, estornada e removida).">
            <dl class="grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <dt class="font-semibold text-gray-950 dark:text-white">URL</dt>
                    <dd class="mt-1 break-all rounded-lg bg-gray-50 p-2 font-mono text-xs dark:bg-white/5">{{ $this->config()->urlDoWebhook() }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-950 dark:text-white">Token de autenticação</dt>
                    <dd class="mt-1 break-all rounded-lg bg-gray-50 p-2 font-mono text-xs dark:bg-white/5">{{ $this->config()->webhook_token }}</dd>
                </div>
            </dl>
            <div class="mt-3"><x-filament::button type="button" wire:click="novoTokenDoWebhook" color="gray" size="xs" wire:confirm="Gerar um novo token? Você precisará atualizá-lo no Asaas.">Gerar novo token</x-filament::button></div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
