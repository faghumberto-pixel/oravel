<x-filament-panels::page>
    @php
        $carga = $this->carga();
        $listas = $carga ? $this->listas() : [];
        $nota = $carga?->diagnostico['pontuacao'] ?? null;
        $faixa = $carga?->diagnostico['faixa'] ?? null;
        $cor = fn (?string $f) => $this->corDaFaixa((string) $f);
        $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', '.'), '0'), ',');
        $card = 'rounded-xl p-4';
        $borda = 'border:1px solid rgba(128,128,128,.3)';
    @endphp

    {{-- Dados de exemplo --}}
    @if ($this->temExemplo())
        <div class="rounded-xl px-4 py-3 text-sm" style="background:rgba(245,158,11,.14);border:1px solid rgba(245,158,11,.5)" data-suprimentos-exemplo>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p><strong>Você está vendo dados de DEMONSTRAÇÃO.</strong> São fictícios e servem só para mostrar como a tela fica com dados. Limpe-os assim que for começar com os seus{{ $this->diasDoExemplo() !== null ? ' (somem sozinhos em '.$this->diasDoExemplo().' dia(s))' : '' }}.</p>
                <x-filament::button color="warning" size="sm" wire:click="limparExemplo" wire:confirm="Apagar os dados de exemplo de Suprimentos? Os seus próprios dados não são afetados.">Limpar dados de exemplo</x-filament::button>
            </div>
        </div>
    @elseif ($this->podeVerExemplo())
        <div class="text-right text-xs"><button type="button" class="underline" style="opacity:.8" wire:click="verExemplo">Ver dados de exemplo</button></div>
    @endif

    @if (! $carga)
        <div class="{{ $card }}" style="{{ $borda }}">
            <p class="text-lg font-semibold">Ainda não há planilha de Suprimentos</p>
            <p class="mt-2 text-sm">Baixe o <strong>Modelo de Suprimentos (Excel)</strong> no topo da página (é diferente do modelo geral do Intelligence), preencha e use <strong>Importar planilha de Suprimentos</strong>.</p>
            <ul class="mt-3 list-disc pl-5 text-sm space-y-1">
                <li><strong>Inventário</strong> (obrigatória): cada item com a sua categoria ({{ implode(', ', \App\Domain\Intelligence\Suprimentos\Catalogo::categorias()) }}), quantidade, estoque mínimo e custo.</li>
                <li><strong>Compras</strong> (opcional): os pedidos, com o prazo prometido e quando chegaram.</li>
                <li><strong>Movimentações</strong> (opcional): entradas e saídas, e para onde cada saída foi.</li>
            </ul>
        </div>
    @else
        {{-- Resumo --}}
        <div class="{{ $card }} flex flex-wrap items-center gap-6" style="{{ $borda }}">
            <div style="text-align:center;min-width:120px">
                <div style="font-size:44px;font-weight:700;line-height:1;color:{{ $cor($faixa) }}">{{ $nota !== null ? number_format($nota, 0) : '—' }}</div>
                <div class="text-sm font-semibold" style="color:{{ $cor($faixa) }}">{{ $faixa }}</div>
                <div class="text-xs" style="opacity:.7">Nota de Suprimentos</div>
            </div>
            <div class="text-sm space-y-1">
                <p>Fotografia de <strong>{{ $carga->data_referencia->format('d/m/Y') }}</strong> · últimos {{ $carga->periodo_dias }} dias</p>
                <p>{{ $carga->total_itens }} itens · {{ $carga->total_compras }} compras · {{ $carga->total_movimentos }} movimentações</p>
                @if ($this->anterior())<p class="text-xs" style="opacity:.7">Comparando com a fotografia de {{ $this->anterior()->data_referencia->format('d/m/Y') }}.</p>@endif
            </div>
        </div>

        {{-- Principais perguntas --}}
        <div>
            <p class="mb-2 text-lg font-semibold">As principais perguntas, respondidas</p>
            <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
                <div class="{{ $card }}" style="{{ $borda }}" data-pergunta="repor">
                    <p class="text-sm" style="opacity:.7">Quais itens precisamos repor?</p>
                    <p style="font-size:26px;font-weight:700">{{ $listas['abaixo_minimo_total'] ?? 0 }} <span class="text-sm font-normal">item(ns) abaixo do mínimo</span></p>
                    @if (($listas['abaixo_minimo_total'] ?? 0) > 0)
                        <p class="text-sm">Custo estimado para repor: <strong>{{ $brl($listas['custo_reposicao'] ?? 0) }}</strong></p>
                        <ul class="mt-2 text-sm space-y-1">
                            @foreach (array_slice($listas['abaixo_minimo'] ?? [], 0, 5) as $i)
                                <li>{{ $i['descricao'] }} <span style="opacity:.7">({{ $num($i['quantidade']) }} de {{ $num($i['estoque_minimo']) }}) · comprar {{ $num($i['sugestao_compra']) }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="{{ $card }}" style="{{ $borda }}" data-pergunta="giro">
                    <p class="text-sm" style="opacity:.7">Quais itens têm maior giro de estoque?</p>
                    @forelse (array_slice($listas['maior_giro'] ?? [], 0, 5) as $i)
                        <p class="text-sm">{{ $i['descricao'] }} <span style="opacity:.7">· {{ $num($i['giro_ano']) }}x ao ano</span></p>
                    @empty
                        <p class="text-sm" style="opacity:.7">Preencha a aba Movimentações para ver o giro por item.</p>
                    @endforelse
                    @if (count($listas['menor_giro'] ?? []))
                        <p class="mt-2 text-sm" style="opacity:.7">E os de menor giro:</p>
                        @foreach (array_slice($listas['menor_giro'], 0, 3) as $i)
                            <p class="text-sm">{{ $i['descricao'] }} <span style="opacity:.7">· {{ $num($i['giro_ano']) }}x ao ano</span></p>
                        @endforeach
                    @endif
                </div>
                <div class="{{ $card }}" style="{{ $borda }}" data-pergunta="parados">
                    <p class="text-sm" style="opacity:.7">O que está parado, e quanto dinheiro é?</p>
                    <p style="font-size:26px;font-weight:700">{{ $brl($listas['parados_valor'] ?? 0) }}</p>
                    <p class="text-sm">{{ $listas['parados_total'] ?? 0 }} item(ns) sem saída há mais de {{ config('intelligence.suprimentos.dias_parado') }} dias</p>
                    @foreach (array_slice($listas['parados'] ?? [], 0, 3) as $i)
                        <p class="text-sm">{{ $i['descricao'] }} <span style="opacity:.7">· {{ $brl($i['valor']) }}</span></p>
                    @endforeach
                </div>
                <div class="{{ $card }}" style="{{ $borda }}" data-pergunta="fornecedores">
                    <p class="text-sm" style="opacity:.7">Quais fornecedores mais atrasam?</p>
                    @php($piores = collect($listas['fornecedores'] ?? [])->filter(fn ($f) => $f['no_prazo_pct'] !== null)->sortBy('no_prazo_pct')->take(3))
                    @forelse ($piores as $f)
                        <p class="text-sm">{{ $f['fornecedor'] }} <span style="opacity:.7">· {{ $num($f['no_prazo_pct']) }}% no prazo · atraso médio {{ $num($f['atraso_medio_dias']) }} d</span></p>
                    @empty
                        <p class="text-sm" style="opacity:.7">Preencha a aba Compras (com as datas de chegada).</p>
                    @endforelse
                </div>
                <div class="{{ $card }}" style="{{ $borda }}" data-pergunta="destinos">
                    <p class="text-sm" style="opacity:.7">Para onde está indo o material?</p>
                    @forelse (array_slice($listas['destinos'] ?? [], 0, 4) as $d)
                        <p class="text-sm">{{ $d['destino'] }} <span style="opacity:.7">· {{ $brl($d['valor']) }} ({{ $num($d['pct']) }}%)</span></p>
                    @empty
                        <p class="text-sm" style="opacity:.7">Preencha a aba Movimentações com o destino de cada saída.</p>
                    @endforelse
                </div>
                <div class="{{ $card }}" style="{{ $borda }}" data-pergunta="categorias">
                    <p class="text-sm" style="opacity:.7">Onde está o dinheiro do estoque?</p>
                    @foreach (array_slice($listas['por_categoria'] ?? [], 0, 4) as $c)
                        <p class="text-sm">{{ $c['categoria'] }} <span style="opacity:.7">· {{ $brl($c['valor']) }} ({{ $num($c['pct_valor']) }}%)</span></p>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Indicadores --}}
        <div>
            <p class="mb-2 text-lg font-semibold">Indicadores</p>
            <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr))">
                @foreach ($carga->indicadores as $chave => $i)
                    @php($faixaI = isset($i['nota']) ? $this->faixaDaNota((float) $i['nota']) : null)
                    @php($v = $this->variacao($chave, $i))
                    <div class="{{ $card }}" style="{{ $borda }};border-left:5px solid {{ $faixaI ? $cor($faixaI) : 'rgba(128,128,128,.4)' }}" title="{{ \App\Domain\Intelligence\Suprimentos\Catalogo::indicadores()[$chave]['explica'] ?? '' }}">
                        <p class="text-xs" style="opacity:.7">{{ $i['titulo'] }}</p>
                        <p style="font-size:22px;font-weight:700">{{ $this->formatar((float) $i['valor'], $i['unidade']) }}</p>
                        @if ($faixaI)<p class="text-xs font-semibold" style="color:{{ $cor($faixaI) }}">{{ $faixaI }}</p>@endif
                        @if ($v)<p class="text-xs" style="color:{{ $v[1] === null ? 'inherit' : ($v[1] ? '#16a34a' : '#dc2626') }}">{{ $v[1] === null ? '' : ($v[1] ? '▲ melhorou ' : '▼ piorou ') }}{{ $v[0] }}</p>@endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Por categoria + filtro --}}
        <x-intelligence.tabela-simples titulo="Por categoria" subtitulo="Onde está o estoque, e onde estão os problemas" :linhas="$carga->listas['por_categoria'] ?? []"
            :colunas="['categoria' => ['label' => 'Categoria'], 'itens' => ['label' => 'Itens', 'fmt' => 'num'], 'valor' => ['label' => 'Valor em estoque', 'fmt' => 'brl'], 'pct_valor' => ['label' => '% do valor', 'fmt' => 'pct'], 'abaixo_minimo' => ['label' => 'Abaixo do mínimo', 'fmt' => 'num'], 'parados' => ['label' => 'Parados', 'fmt' => 'num'], 'valor_parado' => ['label' => 'Valor parado', 'fmt' => 'brl']]" />

        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm font-semibold" for="filtro-categoria">Ver as listas de uma categoria:</label>
            <select id="filtro-categoria" wire:model.live="categoria" class="rounded-lg text-sm" style="border:1px solid rgba(128,128,128,.4);padding:6px 10px;background:transparent">
                <option value="">Todas</option>
                @foreach ($this->categoriasDaCarga() as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
            </select>
            @if ($categoria !== '')<span class="text-xs" style="opacity:.7">Filtro vale para: repor, giro, parados e zerados.</span>@endif
        </div>

        <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(420px,1fr))">
            <x-intelligence.tabela-simples titulo="Itens a repor" subtitulo="Abaixo do mínimo. Sugestão: repor até {{ config('intelligence.suprimentos.alvo_x_minimo') }}x o mínimo" :linhas="$listas['abaixo_minimo'] ?? []" vazio="Nenhum item abaixo do mínimo."
                :colunas="['descricao' => ['label' => 'Item'], 'categoria' => ['label' => 'Categoria'], 'quantidade' => ['label' => 'Tem', 'fmt' => 'num'], 'estoque_minimo' => ['label' => 'Mínimo', 'fmt' => 'num'], 'sugestao_compra' => ['label' => 'Comprar', 'fmt' => 'num'], 'custo_estimado' => ['label' => 'Custo est.', 'fmt' => 'brl']]" />
            <x-intelligence.tabela-simples titulo="Maior giro de estoque" subtitulo="Quantas vezes o item se renova por ano" :linhas="$listas['maior_giro'] ?? []" vazio="Preencha a aba Movimentações."
                :colunas="['descricao' => ['label' => 'Item'], 'categoria' => ['label' => 'Categoria'], 'saiu' => ['label' => 'Saiu', 'fmt' => 'num'], 'quantidade' => ['label' => 'Tem', 'fmt' => 'num'], 'giro_ano' => ['label' => 'Giro/ano', 'fmt' => 'num']]" />
            <x-intelligence.tabela-simples titulo="Menor giro de estoque" subtitulo="Saem, mas devagar: candidatos a reduzir o estoque" :linhas="$listas['menor_giro'] ?? []" vazio="Preencha a aba Movimentações."
                :colunas="['descricao' => ['label' => 'Item'], 'categoria' => ['label' => 'Categoria'], 'saiu' => ['label' => 'Saiu', 'fmt' => 'num'], 'quantidade' => ['label' => 'Tem', 'fmt' => 'num'], 'giro_ano' => ['label' => 'Giro/ano', 'fmt' => 'num']]" />
            <x-intelligence.tabela-simples titulo="Itens parados" subtitulo="Com estoque e sem saída há mais de {{ config('intelligence.suprimentos.dias_parado') }} dias" :linhas="$listas['parados'] ?? []" vazio="Nenhum item parado."
                :colunas="['descricao' => ['label' => 'Item'], 'categoria' => ['label' => 'Categoria'], 'quantidade' => ['label' => 'Tem', 'fmt' => 'num'], 'valor' => ['label' => 'Valor', 'fmt' => 'brl'], 'dias_sem_saida' => ['label' => 'Sem saída', 'fmt' => 'dias']]" />
            <x-intelligence.tabela-simples titulo="Zerados que ainda saem" subtitulo="Estoque zero, mas com consumo no período: risco de parar" :linhas="$listas['zerados_com_saida'] ?? []" vazio="Nenhum item zerado com consumo."
                :colunas="['descricao' => ['label' => 'Item'], 'categoria' => ['label' => 'Categoria'], 'saiu' => ['label' => 'Saiu no período', 'fmt' => 'num']]" />
            <x-intelligence.tabela-simples titulo="Pedidos em atraso" subtitulo="Ainda não chegaram e já passaram do prazo" :linhas="$listas['pedidos_atrasados'] ?? []" vazio="Nenhum pedido em atraso."
                :colunas="['pedido' => ['label' => 'Pedido'], 'codigo_item' => ['label' => 'Item'], 'fornecedor' => ['label' => 'Fornecedor'], 'dias_atraso' => ['label' => 'Atraso', 'fmt' => 'dias'], 'valor' => ['label' => 'Valor', 'fmt' => 'brl']]" />
            <x-intelligence.tabela-simples titulo="Fornecedores" subtitulo="Quanto compramos de cada um e como entregam" :linhas="$listas['fornecedores'] ?? []" vazio="Preencha a aba Compras."
                :colunas="['fornecedor' => ['label' => 'Fornecedor'], 'pedidos' => ['label' => 'Pedidos', 'fmt' => 'num'], 'valor' => ['label' => 'Valor', 'fmt' => 'brl'], 'no_prazo_pct' => ['label' => 'No prazo', 'fmt' => 'pct'], 'atraso_medio_dias' => ['label' => 'Atraso médio', 'fmt' => 'dias']]" />
            <x-intelligence.tabela-simples titulo="Para onde foi o material" subtitulo="Saídas do período por destino, em valor" :linhas="$listas['destinos'] ?? []" vazio="Preencha a aba Movimentações."
                :colunas="['destino' => ['label' => 'Destino'], 'saidas' => ['label' => 'Saídas', 'fmt' => 'num'], 'valor' => ['label' => 'Valor', 'fmt' => 'brl'], 'pct' => ['label' => '%', 'fmt' => 'pct']]" />
            <x-intelligence.tabela-simples titulo="Compras emergenciais" subtitulo="Itens comprados às pressas" :linhas="$listas['emergenciais'] ?? []" vazio="Nenhuma compra emergencial no período."
                :colunas="['descricao' => ['label' => 'Item'], 'compras' => ['label' => 'Compras', 'fmt' => 'num'], 'valor' => ['label' => 'Valor', 'fmt' => 'brl']]" />
        </div>

        @if (! empty($carga->avisos))
            <div class="{{ $card }}" style="{{ $borda }}">
                <p class="font-semibold">O que faltou na planilha</p>
                <ul class="mt-1 list-disc pl-5 text-sm space-y-1">@foreach ($carga->avisos as $a)<li>{{ $a }}</li>@endforeach</ul>
            </div>
        @endif
    @endif

    {{-- Perguntas para o assistente --}}
    <div class="{{ $card }}" style="{{ $borda }}">
        <p class="font-semibold">Perguntas que o assistente de IA responde sobre Suprimentos</p>
        <ul class="mt-2 grid gap-1 text-sm" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr));list-style:disc;padding-left:20px">
            @foreach ([
                'Quais itens precisamos repor, quanto comprar e quanto custa?', 'Quais itens têm maior giro de estoque? E os de menor giro?', 'O que está parado e quanto dinheiro isso representa?',
                'Quais itens zeraram e ainda têm consumo?', 'Por quantos dias o estoque dura (cobertura)?', 'Quais categorias concentram mais dinheiro em estoque?',
                'Quais fornecedores mais atrasam e qual a dependência do maior fornecedor?', 'Quais pedidos estão atrasados agora?', 'Para onde está indo o material, e quanto sai sem destino?',
                'Quais itens compramos de emergência?', 'Como a nota de Suprimentos evoluiu desde a fotografia anterior?',
            ] as $p)<li>{{ $p }}</li>@endforeach
        </ul>
    </div>
</x-filament-panels::page>
