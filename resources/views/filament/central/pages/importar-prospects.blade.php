<x-filament-panels::page>
    @php
        $campos = $this->getCampos();
        $btn = 'px-4 py-2 text-sm font-bold rounded-lg border transition-colors';
        $btnPrimario = $btn.' bg-primary-600 text-white border-primary-600 hover:bg-primary-500';
        $btnNeutro = $btn.' border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800';
        $acaoCor = ['criado' => 'text-emerald-600', 'atualizado' => 'text-blue-600', 'ignorado' => 'text-amber-600', 'erro' => 'text-red-600'];
        $passos = [1 => 'Arquivo', 2 => 'Colunas', 3 => 'Simulação', 4 => 'Resultado'];
        $select = 'text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 w-full';
    @endphp

    <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-wide">
        @foreach($passos as $n => $rotulo)
            <span class="px-3 py-1 rounded-full {{ $this->passo === $n ? 'bg-primary-600 text-white' : ($this->passo > $n ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500 dark:bg-gray-800') }}">{{ $n }}. {{ $rotulo }}</span>
        @endforeach
        <a href="{{ \App\Filament\Central\Resources\ProspectResource::getUrl() }}" class="ms-auto text-xs normal-case font-semibold underline text-primary-600">Voltar para os prospects</a>
    </div>

    {{-- Passo 1: arquivo --}}
    @if($this->passo === 1)
        <x-filament::section heading="1. Envie a lista de empresas">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Arquivo (.csv, .xlsx ou .json, até 5 MB e 5.000 empresas)</label>
                    <input type="file" wire:model="arquivo" accept=".csv,.txt,.xlsx,.json" class="text-sm" />
                    <div wire:loading wire:target="arquivo" class="text-xs text-gray-500 mt-1">Enviando…</div>
                    @error('arquivo') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-500 mt-1">Em CSV/Excel a primeira linha é o cabeçalho; em JSON, uma lista de empresas. As colunas podem vir em qualquer ordem: no próximo passo você confere a ligação de cada uma.</p>
                </div>

                <div class="rounded-lg bg-gray-50 dark:bg-white/5 p-3 text-sm text-gray-600 dark:text-gray-300 space-y-1">
                    <p><strong>Como funciona:</strong> a empresa é identificada pelo <strong>CNPJ</strong>. Quem já está na lista não é duplicado e <strong>nada que você anotou</strong> (situação, resultado, observações, próximos contatos) é alterado.</p>
                    <p><strong>Para completar o CNAE e a data de abertura</strong> de quem já está na lista, envie o arquivo de novo e escolha “Completar só os campos vazios”.</p>
                </div>

                <div class="flex flex-wrap gap-2 items-center">
                    <button type="button" wire:click="lerArquivo" wire:loading.attr="disabled" wire:target="lerArquivo,arquivo" class="{{ $btnPrimario }}">Ler arquivo</button>
                    <button type="button" wire:click="baixarModelo" class="text-xs underline text-primary-600">Baixar modelo (CSV)</button>
                </div>
            </div>
        </x-filament::section>

        @php $historico = $this->getHistorico(); @endphp
        @if($historico->isNotEmpty())
            <x-filament::section heading="Últimas importações" collapsible collapsed>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="py-1">Quando</th><th>Quem</th><th>Arquivo</th><th>Origem</th><th>Criados</th><th>Atualizados</th><th>Ignorados</th><th>Erros</th></tr></thead>
                        <tbody>
                            @foreach($historico as $h)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-1 whitespace-nowrap">{{ $h->created_at->format('d/m/Y H:i') }}</td><td>{{ $h->usuario?->name ?? '—' }}</td>
                                    <td>{{ $h->arquivo }}</td><td>{{ $h->origem ?? '—' }}</td>
                                    <td>{{ $h->criados }}</td><td>{{ $h->atualizados }}</td><td>{{ $h->ignorados }}</td><td>{{ $h->erros }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @endif

    {{-- Passo 2: colunas e opcoes --}}
    @if($this->passo === 2)
        <x-filament::section :heading="'2. Ligue as colunas de “'.$this->nomeArquivo.'” aos campos ('.$this->totalLinhas.' empresas)'">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($campos as $chave => $def)
                    <div wire:key="map-{{ $chave }}">
                        <label class="block text-sm font-semibold mb-1">{{ $def['rotulo'] }} @if($def['obrigatorio'])<span class="text-red-600">*</span>@endif</label>
                        <select wire:model.live="mapeamento.{{ $chave }}" class="{{ $select }}">
                            <option value="">— não importar —</option>
                            @foreach($this->cabecalhos as $i => $nome)
                                <option value="{{ $i }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mt-2">Precisa ligar o <strong>CNPJ</strong> e o <strong>nome</strong> (ou a razão social). Os demais campos são opcionais.</p>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div>
                    <label class="block text-sm font-semibold mb-1">Se a empresa já estiver na lista (mesmo CNPJ)</label>
                    <select wire:model.live="opcoes.se_existir" class="{{ $select }}">
                        <option value="completar">Completar só os campos vazios (recomendado)</option>
                        <option value="ignorar">Ignorar (não mexer)</option>
                        <option value="atualizar">Atualizar com os valores do arquivo</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Célula em branco nunca apaga dado existente.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Origem (só para empresas novas)</label>
                    <input type="text" wire:model.live.debounce.500ms="opcoes.origem" maxlength="120" class="{{ $select }}" placeholder="Ex.: Lista Oravel SP" />
                    <p class="text-xs text-gray-500 mt-1">Fica gravada em cada empresa criada agora.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Lote (opcional)</label>
                    <input type="number" min="1" wire:model.live.debounce.500ms="opcoes.lote" class="{{ $select }}" placeholder="Ex.: 3" />
                    <p class="text-xs text-gray-500 mt-1">Marca as empresas deste arquivo com esse número de lote.</p>
                </div>
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

    {{-- Passos 3 e 4: simulacao / resultado --}}
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
                        <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="py-1">Linha</th><th>Resultado</th><th>Empresa</th><th>Observação</th></tr></thead>
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
                        <button type="button" wire:click="importar" wire:loading.attr="disabled" wire:confirm="Gravar de verdade as empresas desta simulação?" class="{{ $btnPrimario }}">Importar de verdade</button>
                    @else
                        <a href="{{ \App\Filament\Central\Resources\ProspectResource::getUrl() }}" class="{{ $btnPrimario }}">Ver os prospects</a>
                        <button type="button" wire:click="baixarRelatorio" class="{{ $btnNeutro }}">Baixar relatório (CSV)</button>
                        <button type="button" wire:click="recomecar" class="{{ $btnNeutro }}">Nova importação</button>
                    @endif
                </div>
            </x-filament::section>
        @endif
    @endforeach
</x-filament-panels::page>
