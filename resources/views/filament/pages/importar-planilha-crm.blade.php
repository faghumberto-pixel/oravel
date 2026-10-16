<x-filament-panels::page>
    @php
        $campos = $this->getCampos();
        $btn = 'px-4 py-2 text-sm font-bold rounded-lg border transition-colors';
        $btnPrimario = $btn.' bg-primary-600 text-white border-primary-600 hover:bg-primary-500';
        $btnNeutro = $btn.' border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800';
        $acaoCor = ['criado' => 'text-emerald-600', 'atualizado' => 'text-blue-600', 'ignorado' => 'text-amber-600', 'erro' => 'text-red-600'];
        $passos = [1 => 'Arquivo', 2 => 'Colunas', 3 => 'Simulação', 4 => 'Resultado'];
    @endphp

    <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-wide">
        @foreach($passos as $n => $rotulo)
            <span class="px-3 py-1 rounded-full {{ $this->passo === $n ? 'bg-primary-600 text-white' : ($this->passo > $n ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500 dark:bg-gray-800') }}">{{ $n }}. {{ $rotulo }}</span>
        @endforeach
    </div>

    {{-- Passo 1: arquivo --}}
    @if($this->passo === 1)
        <x-filament::section heading="1. Escolha o destino e envie a planilha">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">O que você vai importar?</label>
                    <select wire:model.live="destino" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 w-full max-w-md">
                        @foreach($this->getDestinosPermitidos() as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Planilha (.csv ou .xlsx, até 5 MB e 5.000 linhas)</label>
                    <input type="file" wire:model="planilha" accept=".csv,.txt,.xlsx" class="text-sm" />
                    @error('planilha') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-500 mt-1">A primeira linha deve ser o cabeçalho. Colunas em qualquer ordem: você liga cada uma ao campo certo no próximo passo.</p>
                </div>

                <div class="flex flex-wrap gap-2 items-center">
                    <button type="button" wire:click="lerPlanilha" wire:loading.attr="disabled" class="{{ $btnPrimario }}">Ler planilha</button>
                    <span class="text-xs text-gray-500">Modelo:</span>
                    @foreach($this->getDestinosPermitidos() as $valor => $rotulo)
                        <button type="button" wire:click="baixarModelo('{{ $valor }}')" class="text-xs underline text-primary-600">{{ $rotulo }}</button>
                    @endforeach
                </div>
            </div>
        </x-filament::section>

        @php $historico = $this->getHistorico(); @endphp
        @if($historico->isNotEmpty())
            <x-filament::section heading="Últimas importações" collapsible collapsed>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="py-1">Quando</th><th>Quem</th><th>Destino</th><th>Arquivo</th><th>Criados</th><th>Atualizados</th><th>Ignorados</th><th>Erros</th></tr></thead>
                    <tbody>
                        @foreach($historico as $h)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-1">{{ $h->created_at->format('d/m/Y H:i') }}</td><td>{{ $h->usuario?->name ?? '—' }}</td>
                                <td>{{ \App\Services\CrmImport\CrmImportDestinos::rotulos()[$h->destino] ?? $h->destino }}</td><td>{{ $h->arquivo }}</td>
                                <td>{{ $h->criados }}</td><td>{{ $h->atualizados }}</td><td>{{ $h->ignorados }}</td><td>{{ $h->erros }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endif
    @endif

    {{-- Passo 2: mapeamento --}}
    @if($this->passo === 2)
        <x-filament::section :heading="'2. Ligue as colunas de “'.$this->nomeArquivo.'” aos campos ('.$this->totalLinhas.' linhas)'">
            <div class="grid gap-3 md:grid-cols-2">
                @foreach($campos as $chave => $def)
                    <div wire:key="map-{{ $chave }}">
                        <label class="block text-sm font-semibold mb-1">{{ $def['rotulo'] }} @if($def['obrigatorio'])<span class="text-red-600">*</span>@endif</label>
                        <select wire:model.live="mapeamento.{{ $chave }}" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 w-full">
                            <option value="">— não importar —</option>
                            @foreach($this->cabecalhos as $i => $nome)
                                <option value="{{ $i }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-semibold mb-1">Se o registro já existir (mesmo CNPJ/CPF, e-mail ou nome)</label>
                    <select wire:model.live="opcoes.se_existir" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 w-full">
                        <option value="ignorar">Ignorar (recomendado)</option>
                        <option value="completar">Completar só os campos vazios</option>
                        <option value="sobrescrever">Sobrescrever com os valores da planilha</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Célula em branco nunca apaga dado existente.</p>
                </div>
                @if($this->destino === 'contato')
                    <div>
                        <label class="block text-sm font-semibold mb-1">Os contatos pertencem a</label>
                        <select wire:model.live="opcoes.pai" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 w-full">
                            <option value="conta">Contas</option>
                            <option value="lead">Leads</option>
                        </select>
                    </div>
                @endif
            </div>

            @if($this->previa)
                <div class="mt-5 overflow-x-auto">
                    <p class="text-xs font-bold uppercase text-gray-500 mb-1">Prévia (5 primeiras linhas)</p>
                    <table class="text-xs w-full">
                        <thead><tr>@foreach($this->cabecalhos as $nome)<th class="text-left pr-3">{{ $nome }}</th>@endforeach</tr></thead>
                        <tbody>@foreach($this->previa as $linha)<tr class="border-t border-gray-100 dark:border-gray-800">@foreach($this->cabecalhos as $i => $nome)<td class="pr-3 py-0.5">{{ $linha[$i] ?? '' }}</td>@endforeach</tr>@endforeach</tbody>
                    </table>
                </div>
            @endif

            <div class="mt-5 flex gap-2">
                <button type="button" wire:click="voltar(1)" class="{{ $btnNeutro }}">Voltar</button>
                <button type="button" wire:click="simular" wire:loading.attr="disabled" class="{{ $btnPrimario }}">Simular (não grava nada)</button>
            </div>
        </x-filament::section>
    @endif

    {{-- Passos 3 e 4: simulação / resultado --}}
    @foreach([3 => $this->simulacao, 4 => $this->resultado] as $n => $dados)
        @if($this->passo === $n && $dados)
            <x-filament::section :heading="$n === 3 ? '3. Simulação — nada foi gravado ainda' : '4. Importação concluída'">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-center">
                    @foreach(['criados' => 'Criados', 'atualizados' => 'Atualizados', 'ignorados' => 'Ignorados', 'erros' => 'Com erro', 'avisos' => 'Avisos'] as $k => $rotulo)
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                            <p class="text-2xl font-black">{{ $dados['totais'][$k] }}</p>
                            <p class="text-xs uppercase text-gray-500">{{ $rotulo }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 overflow-x-auto">
                    <p class="text-xs font-bold uppercase text-gray-500 mb-1">Linhas que pedem atenção primeiro (até 50) — o relatório completo está no botão abaixo</p>
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="py-1">Linha</th><th>Resultado</th><th>Registro</th><th>Observação</th></tr></thead>
                        <tbody>
                            @foreach($dados['linhas'] as $l)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-1">{{ $l['linha'] }}</td>
                                    <td class="font-bold {{ $acaoCor[$l['acao']] ?? '' }}">{{ ucfirst($l['acao']) }}</td>
                                    <td>{{ $l['nome'] }}</td><td>{{ $l['mensagem'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    @if($n === 3)
                        <button type="button" wire:click="voltar(2)" class="{{ $btnNeutro }}">Ajustar colunas</button>
                        <button type="button" wire:click="baixarRelatorio" class="{{ $btnNeutro }}">Baixar relatório (CSV)</button>
                        <button type="button" wire:click="importar" wire:loading.attr="disabled" wire:confirm="Gravar de verdade os registros desta simulação?" class="{{ $btnPrimario }}">Importar de verdade</button>
                    @else
                        <button type="button" wire:click="baixarRelatorio" class="{{ $btnNeutro }}">Baixar relatório (CSV)</button>
                        <button type="button" wire:click="recomecar" class="{{ $btnPrimario }}">Nova importação</button>
                    @endif
                </div>
            </x-filament::section>
        @endif
    @endforeach
</x-filament-panels::page>
