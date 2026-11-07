<x-filament-panels::page>
    @php
        $todas = collect($this->diferencas);
        $disponiveis = $todas->whereIn('acao', ['grupo_novo', 'novo', 'atualizar']);
        $personalizados = $todas->where('acao', 'personalizado');
        $arquivados = $todas->where('acao', 'arquivado');
        $removidos = $todas->where('acao', 'removido_do_padrao');
        $conflitos = $todas->where('acao', 'conflito');
        $rotulos = ['grupo_novo' => 'Grupo novo no padrão', 'novo' => 'Item novo no padrão', 'atualizar' => 'Alterado no padrão'];
        $valor = fn ($v) => is_bool($v) ? ($v ? 'sim' : 'não') : (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (($v === null || $v === '') ? '—' : (string) $v));
    @endphp

    @if ($todas->isEmpty() || ($disponiveis->isEmpty() && $personalizados->isEmpty() && $arquivados->isEmpty() && $removidos->isEmpty() && $conflitos->isEmpty()))
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-300">Seu catálogo está de acordo com o padrão atual. Nenhuma atualização disponível.</p>
        </x-filament::section>
    @endif

    @if ($disponiveis->isNotEmpty())
        <x-filament::section heading="Atualizações disponíveis" description="Marque o que quer aceitar. Nada é aplicado sem a sua confirmação, e o que você personalizou nunca é alterado por aqui.">
            <ul class="divide-y divide-gray-200 dark:divide-white/10" data-testid="atualizacoes-disponiveis">
                @foreach ($disponiveis as $e)
                    <li class="py-3" wire:key="disp-{{ $e['chave'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <label class="flex items-start gap-3 text-sm">
                                @if ($this->podeGerenciar())
                                    <input type="checkbox" class="mt-1 rounded border-gray-300" wire:model="selecionadas" value="{{ $e['chave'] }}">
                                @endif
                                <span>
                                    <span class="font-semibold">{{ $e['rotulo'] }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">· {{ $e['tipo_rotulo'] }}{{ $e['grupo'] ? ' · grupo '.$e['grupo'] : '' }} · {{ $rotulos[$e['acao']] }}</span>
                                    @foreach ($e['campos'] as $campo => $v)
                                        <span class="block text-xs text-gray-600 dark:text-gray-300">{{ $campo }}: {{ $valor($v['atual']) }} → <strong>{{ $valor($v['padrao']) }}</strong></span>
                                    @endforeach
                                </span>
                            </label>
                            @if ($e['acao'] === 'atualizar' && $this->podeGerenciar())
                                <button type="button" class="text-xs font-semibold text-gray-500 underline" wire:click="mountAction('ignorar', @js(['chave' => $e['chave']]))">Ignorar</button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($personalizados->isNotEmpty())
        <x-filament::section heading="Personalizados com versão nova do padrão" description="Você ajustou estes itens, então eles não são alterados automaticamente. Se quiser a versão do padrão, restaure item a item.">
            <ul class="divide-y divide-gray-200 dark:divide-white/10" data-testid="personalizados">
                @foreach ($personalizados as $e)
                    <li class="flex items-start justify-between gap-3 py-3" wire:key="pers-{{ $e['chave'] }}">
                        <span class="text-sm">
                            <span class="font-semibold">{{ $e['rotulo'] }}</span>
                            <span class="text-gray-500 dark:text-gray-400">· {{ $e['tipo_rotulo'] }}{{ $e['grupo'] ? ' · grupo '.$e['grupo'] : '' }}</span>
                            @foreach ($e['campos'] as $campo => $v)
                                <span class="block text-xs text-gray-600 dark:text-gray-300">{{ $campo }}: sua versão <strong>{{ $valor($v['atual']) }}</strong> · padrão {{ $valor($v['padrao']) }}</span>
                            @endforeach
                        </span>
                        @if ($this->podeGerenciar())
                            <button type="button" class="whitespace-nowrap text-xs font-semibold text-warning-600 underline" wire:click="mountAction('restaurar', @js(['chave' => $e['chave']]))">Restaurar ao padrão</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($arquivados->isNotEmpty())
        <x-filament::section heading="Itens do padrão que você arquivou" description="Não voltam sozinhos. Restaure só se quiser reativá-los.">
            <ul class="divide-y divide-gray-200 dark:divide-white/10" data-testid="arquivados">
                @foreach ($arquivados as $e)
                    <li class="flex items-center justify-between gap-3 py-3" wire:key="arq-{{ $e['chave'] }}">
                        <span class="text-sm"><span class="font-semibold">{{ $e['rotulo'] }}</span> <span class="text-gray-500 dark:text-gray-400">· {{ $e['tipo_rotulo'] }}{{ $e['grupo'] ? ' · grupo '.$e['grupo'] : '' }}</span></span>
                        @if ($this->podeGerenciar())
                            <button type="button" class="whitespace-nowrap text-xs font-semibold text-warning-600 underline" wire:click="mountAction('restaurar', @js(['chave' => $e['chave']]))">Restaurar ao padrão</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($removidos->isNotEmpty() || $conflitos->isNotEmpty())
        <x-filament::section heading="Para sua informação">
            <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($removidos as $e)
                    <li>{{ $e['rotulo'] }} ({{ $e['tipo_rotulo'] }}) saiu do padrão. Continua no seu catálogo; remova se não precisar mais.</li>
                @endforeach
                @foreach ($conflitos as $e)
                    <li>O grupo padrão "{{ $e['rotulo'] }}" não foi instalado: você já tem uma categoria com o mesmo identificador.</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
