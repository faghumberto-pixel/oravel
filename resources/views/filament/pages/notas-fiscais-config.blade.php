<x-filament-panels::page>
    <form wire:submit="salvar" class="space-y-6">
        {{ $this->form }}

        <x-filament::section heading="Certificado digital A1" description="Envie o arquivo do certificado (.pfx ou .p12) e a senha. Eles seguem direto para o emissor fiscal e não ficam guardados no sistema: aqui só guardamos a validade.">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-950 dark:text-white">Arquivo do certificado</label>
                    <input type="file" wire:model="certificado" accept=".pfx,.p12" class="mt-1 block w-full text-sm">
                    @error('certificado') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-950 dark:text-white">Senha do certificado</label>
                    <input type="password" wire:model="senhaCertificado" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                    @error('senhaCertificado') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-3">
                <x-filament::button type="button" wire:click="enviarCertificado" color="gray" icon="heroicon-o-arrow-up-tray">Enviar certificado ao emissor</x-filament::button>
            </div>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Prefere ajuda? A equipe Oravel faz a configuração com você (taxa de configuração opcional). Prefere configurar sozinho? Veja o treinamento na Academia.</p>
        </x-filament::section>

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" color="gray">Salvar</x-filament::button>
            <x-filament::button type="button" wire:click="testarEAtivar" color="success" icon="heroicon-o-check-circle">Testar conexão e ligar</x-filament::button>
            @if($this->config()?->enabled)
                <x-filament::button type="button" wire:click="desligar" color="danger" outlined>Desligar</x-filament::button>
            @endif
        </div>
    </form>

    @if($this->config()?->provider_company_id)
        <x-filament::section heading="Retorno das notas (avançado)" description="O sistema confere as notas em processamento sozinho. Se o suporte pedir, use estes dados no gatilho do emissor.">
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
        </x-filament::section>
    @endif
</x-filament-panels::page>
