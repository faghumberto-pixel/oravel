<x-filament-panels::page>
    <div class="max-w-5xl mx-auto space-y-8">

        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-2xl">
            Documentação técnica dos módulos do Oravel, para quem opera o SaaS — arquitetura, fluxos e mecanismos reaproveitados. Não é conteúdo visível pelos tenants.
        </p>

        {{-- Índice de artigos -- hoje so' 1, estrutura ja' pronta pra crescer --}}
        <div class="grid gap-3">
            @foreach($this->getArtigos() as $artigo)
                <a href="#{{ $artigo['slug'] }}" class="block bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 hover:border-primary-400 dark:hover:border-primary-500 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-bold text-gray-900 dark:text-white">{{ $artigo['titulo'] }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-2xl">{{ $artigo['resumo'] }}</p>
                        </div>
                        <x-heroicon-m-arrow-down class="w-4 h-4 text-gray-400 shrink-0 mt-1" />
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        @foreach($artigo['tags'] as $tag)
                            <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">{{ $tag }}</span>
                        @endforeach
                    </div>
                </a>
            @endforeach
        </div>

        {{-- ================= ARTIGO: Gestão de EPI ================= --}}
        <article id="gestao-epi" class="scroll-mt-20 space-y-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-primary-600 dark:text-primary-400">Módulo · Compliance NR-6</p>
                    <h2 class="text-2xl font-black text-gray-900 dark:text-white mt-1">Gestão de EPI</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-2xl">
                        Ciclo de vida completo do Equipamento de Proteção Individual, construído inteiramente sobre mecanismos já existentes no Oravel.
                    </p>
                </div>
                <a href="https://claude.ai/code/artifact/b17febd5-c11c-4bc9-879f-3f3178843796" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors shrink-0">
                    Versão visual completa <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5" />
                </a>
            </div>

            {{-- Ciclo de vida --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                @foreach([
                    ['n' => '01', 't' => 'Cadastro', 'd' => 'CA, fabricante, vida útil, tamanho'],
                    ['n' => '02', 't' => 'Compra', 'd' => 'Cotação → PO → Recebimento'],
                    ['n' => '03', 't' => 'Entrega', 'd' => 'Ficha + assinatura digital'],
                    ['n' => '04', 't' => 'Vencimento', 'd' => 'CA e vida útil monitorados'],
                    ['n' => '05', 't' => 'Compliance', 'd' => 'Relatório NR-6 por colaborador'],
                ] as $step)
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-3">
                        <p class="text-lg font-black text-primary-600 dark:text-primary-400 leading-none">{{ $step['n'] }}</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white mt-1">{{ $step['t'] }}</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 leading-tight">{{ $step['d'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Estágios detalhados --}}
            <div class="space-y-3">
                @foreach([
                    [
                        'titulo' => '01 · Cadastro do EPI',
                        'badge' => ['Novo', 'gray'],
                        'esquerda' => ['Onde vive', 'Cada tamanho/numeração (luva P/M/G, bota por numeração) é seu próprio <code>Material</code>, com uma linha 1:1 em <code>epi_specifications</code> carregando <code>ca_number</code>, <code>ca_manufacturer</code>, <code>ca_validade</code> e <code>estimated_lifespan_days</code> — vida útil separada da validade do CA.'],
                        'direita' => ['Onde reaproveita', 'Seção condicional dentro do <code>MaterialResource</code> existente (mesmo padrão de <code>AssetForkliftSpecification</code>); estoque por tamanho/filial via <code>MaterialLocationStock</code>, sem tabela nova.'],
                    ],
                    [
                        'titulo' => '02 · Compra / Reposição',
                        'badge' => ['100% reaproveitado', 'success'],
                        'esquerda' => ['O que acontece', 'EPI é um <code>Material</code> como qualquer outro — entra na esteira <code>MaterialRequest</code> → <code>PurchaseOrder</code> → <code>GoodsReceipt</code> sem uma linha de código nova.'],
                        'direita' => ['Alerta de estoque baixo', '<code>MaterialStockService::notifyIfLowStock()</code> já dispara pra qualquer Material abaixo do mínimo e sugere abrir a requisição.'],
                    ],
                    [
                        'titulo' => '03 · Entrega / Empréstimo',
                        'badge' => ['Novo', 'gray'],
                        'esquerda' => ['Ficha de entrega', '<code>EpiDelivery</code> registra colaborador, EPI, motivo (entrega inicial, troca por desgaste, perda, empréstimo temporário...) e modo de posse. Cada ciclo é uma linha nova — nunca reescreve histórico.'],
                        'direita' => ['Assinatura digital', 'Trait <code>HasSignatures</code> + <code>SignatureService</code> — mesmo link público sem login usado hoje por Contrato e Ordem de Serviço. Baixa/devolução via <code>MaterialStockService::consume()/receive()</code>.'],
                    ],
                    [
                        'titulo' => '04 · Vencimento e Alertas',
                        'badge' => ['Novo', 'gray'],
                        'esquerda' => ['Bloqueio em tempo real', 'Trigger de banco <code>enforce_epi_delivery_ca_block</code> (Postgres) — mesmo padrão de <code>equipment_allocations</code> — impede novo empréstimo com CA vencido, preservando a tentativa negada pra auditoria.'],
                        'direita' => ['Varredura diária', '<code>epi:check-ca-expirations</code> e <code>epi:check-lifespan-expirations</code> notificam 30/15/7 dias antes e propagam bloqueio retroativo quando um CA vence com o EPI já em uso.'],
                    ],
                    [
                        'titulo' => '05 · Compliance NR-6',
                        'badge' => ['Novo', 'gray'],
                        'esquerda' => ['Por colaborador', 'Página <code>EpiComplianceReport</code>: o que cada colaborador tem em posse hoje, status do CA e dias em uso vs. vida útil recomendada.'],
                        'direita' => ['Rastreabilidade', 'Em caso de acidente: <code>EpiDelivery::where(\'employee_id\', ...)</code> reconstrói exatamente quais EPIs o colaborador tinha e desde quando.'],
                    ],
                ] as $estagio)
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <p class="font-bold text-gray-900 dark:text-white">{{ $estagio['titulo'] }}</p>
                            <span @class([
                                'text-[10px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full',
                                'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' => $estagio['badge'][1] === 'success',
                                'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' => $estagio['badge'][1] === 'gray',
                            ])>{{ $estagio['badge'][0] }}</span>
                        </div>
                        <div class="grid md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">{{ $estagio['esquerda'][0] }}</p>
                                <p class="text-gray-600 dark:text-gray-300 leading-relaxed [&_code]:font-mono [&_code]:text-[12px] [&_code]:bg-gray-100 [&_code]:dark:bg-gray-800 [&_code]:px-1 [&_code]:rounded">{!! $estagio['esquerda'][1] !!}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">{{ $estagio['direita'][0] }}</p>
                                <p class="text-gray-600 dark:text-gray-300 leading-relaxed [&_code]:font-mono [&_code]:text-[12px] [&_code]:bg-gray-100 [&_code]:dark:bg-gray-800 [&_code]:px-1 [&_code]:rounded">{!! $estagio['direita'][1] !!}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Regras de alerta --}}
            <div>
                <p class="text-sm font-bold text-gray-900 dark:text-white mb-2">Regras de alerta e bloqueio</p>
                <div class="overflow-x-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left px-4 py-2 text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Regra</th>
                                <th class="text-left px-4 py-2 text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Mecanismo</th>
                                <th class="text-left px-4 py-2 text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Cadência</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach([
                                ['Bloqueia novo empréstimo com CA vencido', 'trigger enforce_epi_delivery_ca_block (Postgres)', 'Tempo real'],
                                ['CA vencendo (30/15/7 dias) ou vencido', 'epi:check-ca-expirations', 'Diário'],
                                ['Vida útil recomendada estourada', 'epi:check-lifespan-expirations', 'Diário'],
                                ['Devolução de empréstimo atrasada', 'filtro no EpiComplianceStats / EpiDeliveryResource', 'Tempo real'],
                                ['Estoque abaixo do mínimo por tamanho', 'MaterialStockService::notifyIfLowStock() — reaproveitado', 'A cada movimento'],
                            ] as $regra)
                                <tr>
                                    <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $regra[0] }}</td>
                                    <td class="px-4 py-2.5 font-mono text-[12px] text-gray-500 dark:text-gray-400">{{ $regra[1] }}</td>
                                    <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ $regra[2] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Telas --}}
            <div>
                <p class="text-sm font-bold text-gray-900 dark:text-white mb-2">Telas no painel admin</p>
                <div class="overflow-x-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left px-4 py-2 text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Tela</th>
                                <th class="text-left px-4 py-2 text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Rota</th>
                                <th class="text-left px-4 py-2 text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Função</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach([
                                ['Materiais → seção EPI', '/admin/materials', 'Cadastro de CA, fabricante e vida útil por tamanho'],
                                ['Entrega de EPI', '/admin/epi-deliveries', 'Ficha de entrega — gerar assinatura, registrar devolução, registrar troca'],
                                ['Colaborador → Ficha de EPI', '/admin/employees/{id}/edit', 'Histórico completo de entregas do colaborador'],
                                ['Conformidade NR-6', '/admin/epi-compliance-report', 'Relatório agrupado por colaborador — CA em dia, vida útil em dia'],
                                ['Assinaturas Eletrônicas', '/admin/document-signatures', 'Gestão genérica de assinaturas — agora inclui Entrega de EPI'],
                            ] as $tela)
                                <tr>
                                    <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $tela[0] }}</td>
                                    <td class="px-4 py-2.5 font-mono text-[12px] text-gray-500 dark:text-gray-400">{{ $tela[1] }}</td>
                                    <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ $tela[2] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </article>
    </div>
</x-filament-panels::page>
