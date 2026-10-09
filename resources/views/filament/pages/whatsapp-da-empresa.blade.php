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

    @if($this->config())
        <x-filament::section heading="Para receber as respostas dos clientes" description="Cadastre estes dois dados no webhook do seu app, no painel da Meta (WhatsApp → Configuração → Webhook), e assine o campo “messages”.">
            <dl class="grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <dt class="font-semibold text-gray-950 dark:text-white">URL de retorno de chamada</dt>
                    <dd class="mt-1 break-all rounded-lg bg-gray-50 p-2 font-mono text-xs dark:bg-white/5">{{ $this->urlDoWebhook() }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-950 dark:text-white">Token de verificação</dt>
                    <dd class="mt-1 break-all rounded-lg bg-gray-50 p-2 font-mono text-xs dark:bg-white/5">{{ $this->config()->verify_token }}</dd>
                </div>
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
