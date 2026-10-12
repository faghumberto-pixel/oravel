<x-filament-panels::page>
    @if($this->conexaoAutomaticaDisponivel())
        <x-filament::section heading="Conectar com a Meta" description="Autorize o seu número numa janela da Meta, sem criar app nem copiar chaves. Leva poucos minutos.">
            <div
                x-data="{
                    carregando: false,
                    dados: {},
                    iniciar() {
                        this.carregando = true;
                        window.addEventListener('message', (e) => {
                            if (!String(e.origin).endsWith('facebook.com')) return;
                            try {
                                const m = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
                                if (m.type === 'WA_EMBEDDED_SIGNUP' && m.event === 'FINISH') this.dados = m.data;
                            } catch (_) {}
                        });
                        const abrir = () => FB.login((r) => {
                            const codigo = r.authResponse?.code;
                            const esperar = (n) => {
                                if (this.dados.phone_number_id && codigo) { $wire.concluirCadastroMeta(codigo, this.dados.waba_id, this.dados.phone_number_id).then(() => this.carregando = false); }
                                else if (n > 0) { setTimeout(() => esperar(n - 1), 300); }
                                else { this.carregando = false; }
                            };
                            esperar(20);
                        }, { config_id: '{{ config('services.whatsapp.embedded_config_id') }}', response_type: 'code', override_default_response_type: true, extras: { setup: {}, sessionInfoVersion: '3' } });
                        if (window.FB) return abrir();
                        window.fbAsyncInit = () => { FB.init({ appId: '{{ config('services.whatsapp.app_id') }}', autoLogAppEvents: true, xfbml: false, version: 'v20.0' }); abrir(); };
                        const sdk = document.createElement('script'); sdk.src = 'https://connect.facebook.net/pt_BR/sdk.js'; sdk.async = true; document.body.appendChild(sdk);
                    },
                }"
            >
                <x-filament::button type="button" x-on:click="iniciar()" x-bind:disabled="carregando" icon="heroicon-o-link" color="success">
                    <span x-text="carregando ? 'Aguardando a Meta...' : 'Conectar WhatsApp'"></span>
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

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
        <x-filament::section heading="Para receber as respostas dos clientes" description="Só é necessário no cadastro manual. Com o botão Conectar WhatsApp, a Oravel cuida do webhook.">
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
