<x-filament-panels::page>
    @php
        $btn = 'px-4 py-2 text-sm font-bold rounded-lg border transition-colors';
        $btnPrimario = $btn.' bg-primary-600 text-white border-primary-600 hover:bg-primary-500';
        $btnNeutro = $btn.' border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800';
        $campo = 'text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800';
    @endphp

    @if($this->tokenNovo)
        <x-filament::section heading="Copie o token agora — ele não será mostrado de novo">
            <div class="space-y-3">
                <input type="text" readonly value="{{ $this->tokenNovo }}" onclick="this.select()" class="w-full font-mono text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                <p class="text-xs text-gray-500">Cole este token na extensão do WhatsApp (ou na sua integração). Quem tem o token age como você no CRM: guarde com cuidado e revogue se perder.</p>
                <button type="button" wire:click="fecharToken" class="{{ $btnPrimario }}">Já copiei</button>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section heading="Extensão do WhatsApp (Chrome)" description="Mostra no WhatsApp Web quem é o contato no CRM e deixa criar lead e registrar a conversa com um clique.">
        <div class="space-y-3 text-sm">
            <ol class="list-decimal pl-5 space-y-1">
                <li>Gere um token abaixo (com “Consultar” e “Criar e registrar”) e copie.</li>
                <li>Baixe o pacote, descompacte e, em <code>chrome://extensions</code>, ative o “Modo do desenvolvedor” e use “Carregar sem compactação”.</li>
                <li>Nas opções da extensão, informe o endereço do sistema e cole o token.</li>
            </ol>
            <p class="text-xs text-gray-500">
                Privacidade: a extensão só lê o contato aberto e só envia algo ao CRM quando você clica. Nada é enviado ou lido em massa, e o texto das mensagens passa por você antes de ser salvo.
                O WhatsApp não autoriza automação do WhatsApp Web; por isso a extensão é assistida e não envia mensagens por você.
            </p>
            @if(\App\Http\Controllers\CrmExtensaoDownloadController::arquivo())
                <a href="{{ route('crm.extensao.download') }}" class="{{ $btnPrimario }} inline-block">Baixar extensão (.zip)</a>
            @else
                <p class="text-xs text-gray-500">O pacote da extensão ainda não foi gerado neste ambiente.</p>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section heading="Novo token">
        <div class="grid gap-3 md:grid-cols-3 items-end">
            <div>
                <label class="block text-sm font-semibold mb-1">Nome (ex.: Extensão Chrome — Notebook do João)</label>
                <input type="text" wire:model="nome" class="{{ $campo }} w-full" />
                @error('nome') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Validade</label>
                <select wire:model="validadeDias" class="{{ $campo }} w-full">
                    @foreach(\App\Filament\Pages\TokensApiCrm::validades() as $dias => $rotulo)
                        <option value="{{ $dias }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-1"><input type="checkbox" wire:model="podeLer" /> Consultar</label>
                <label class="flex items-center gap-1"><input type="checkbox" wire:model="podeEscrever" /> Criar e registrar</label>
            </div>
        </div>
        <div class="mt-4"><button type="button" wire:click="criar" class="{{ $btnPrimario }}">Gerar token</button></div>
    </x-filament::section>

    <x-filament::section heading="Tokens ativos">
        @php $tokens = $this->tokensVisiveis(); @endphp
        @if($tokens->isEmpty())
            <p class="text-sm text-gray-500">Nenhum token gerado ainda.</p>
        @else
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="py-1">Nome</th><th>Usuário</th><th>Permissões</th><th>Último uso</th><th>Expira em</th><th></th></tr></thead>
                <tbody>
                    @foreach($tokens as $t)
                        <tr class="border-t border-gray-100 dark:border-gray-800" wire:key="token-{{ $t->id }}">
                            <td class="py-1">{{ $t->name }}</td>
                            <td>{{ $this->nomeDoDono($t->tokenable_id) }}</td>
                            <td>{{ collect($t->abilities)->map(fn ($a) => $a === 'crm:read' ? 'consultar' : 'criar/registrar')->implode(', ') }}</td>
                            <td>{{ $t->last_used_at?->format('d/m/Y H:i') ?? 'nunca' }}</td>
                            <td>{{ $t->expires_at?->format('d/m/Y') ?? '—' }}</td>
                            <td><button type="button" wire:click="revogar({{ $t->id }})" wire:confirm="Revogar este token? Quem o usa perde o acesso agora." class="text-red-600 underline text-xs">Revogar</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
