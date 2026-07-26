<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Dias estimados até esgotar são calculados a partir do consumo médio dos últimos 90 dias — materiais sem histórico de saída recente aparecem sem estimativa.
    </p>

    @if ($analysis)
        @if ($analysis->status === \App\Models\AIAnalysis::STATUS_CONCLUIDA)
            <div class="fi-section rounded-xl border p-6">
                <h2 class="text-lg font-bold">Resumo</h2>
                <p class="mt-2 text-sm">{{ $analysis->response['resumo_geral'] ?? '' }}</p>

                @if (! empty($analysis->response['prioridades_compra']))
                    <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">Prioridades de compra</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($analysis->response['prioridades_compra'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif

                @if (! empty($analysis->response['recomendacoes_estoque_parado']))
                    <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">Estoque parado</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($analysis->response['recomendacoes_estoque_parado'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if (! empty($analysis->response['materiais_criticos']))
                <div class="fi-section rounded-xl border p-6">
                    <h3 class="font-bold text-danger-600 dark:text-danger-400">Materiais críticos</h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400">
                                    <th class="pb-2 pr-4">Material</th>
                                    <th class="pb-2 pr-4">Atual / Mínimo</th>
                                    <th class="pb-2 pr-4">Consumo médio/mês</th>
                                    <th class="pb-2 pr-4">Dias até esgotar</th>
                                    <th class="pb-2 pr-4">Fornecedor</th>
                                    <th class="pb-2">Pedido em aberto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analysis->response['materiais_criticos'] as $item)
                                    <tr class="border-t border-gray-100 dark:border-gray-700">
                                        <td class="py-2 pr-4 font-medium">{{ $item['nome'] }}</td>
                                        <td class="py-2 pr-4">{{ $item['estoque_atual'] }} / {{ $item['estoque_minimo'] }}</td>
                                        <td class="py-2 pr-4">{{ $item['consumo_medio_mensal'] }}</td>
                                        <td class="py-2 pr-4">
                                            {{ $item['dias_estimados_para_esgotar'] !== null ? $item['dias_estimados_para_esgotar'].' dias' : '—' }}
                                        </td>
                                        <td class="py-2 pr-4">{{ $item['fornecedor'] ?? '—' }}</td>
                                        <td class="py-2">
                                            @if ($item['tem_pedido_compra_aberto'])
                                                <span class="fi-badge inline-flex items-center rounded-md bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Sim</span>
                                            @else
                                                <span class="fi-badge inline-flex items-center rounded-md bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">Não</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (! empty($analysis->response['materiais_parados']))
                <div class="fi-section rounded-xl border p-6">
                    <h3 class="font-bold text-gray-600 dark:text-gray-300">Materiais parados</h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400">
                                    <th class="pb-2 pr-4">Material</th>
                                    <th class="pb-2 pr-4">Estoque atual</th>
                                    <th class="pb-2 pr-4">Valor parado</th>
                                    <th class="pb-2">Dias sem saída</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analysis->response['materiais_parados'] as $item)
                                    <tr class="border-t border-gray-100 dark:border-gray-700">
                                        <td class="py-2 pr-4 font-medium">{{ $item['nome'] }}</td>
                                        <td class="py-2 pr-4">{{ $item['estoque_atual'] }}</td>
                                        <td class="py-2 pr-4">R$ {{ number_format($item['valor_parado'], 2, ',', '.') }}</td>
                                        <td class="py-2">{{ $item['dias_sem_movimentacao'] !== null ? $item['dias_sem_movimentacao'].' dias' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @else
            <div class="fi-section rounded-xl border border-danger-300 p-6 text-danger-700 dark:border-danger-500/30 dark:text-danger-300">
                {{ $analysis->error }}
            </div>
        @endif
    @endif
</x-filament-panels::page>
