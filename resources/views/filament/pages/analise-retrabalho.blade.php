<x-filament-panels::page>
    <div class="flex items-center gap-3">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Janela de retrabalho</label>
        <select wire:model.live="janelaDias" class="fi-select-input block rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            @foreach ($this->getJanelaOptions() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Retrabalho = mesmo ativo recebeu uma nova OS corretiva dentro da janela escolhida, depois da conclusão de uma corretiva anterior.
    </p>

    @if ($analysis)
        @if ($analysis->status === \App\Models\AIAnalysis::STATUS_CONCLUIDA)
            <div class="fi-section rounded-xl border p-6">
                <h2 class="text-lg font-bold">Resumo</h2>
                <p class="mt-2 text-sm">{{ $analysis->response['resumo_geral'] ?? '' }}</p>

                @if (! empty($analysis->response['principais_causas_interpretadas']))
                    <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">Principais causas</p>
                    <p class="mt-1 text-sm">{{ $analysis->response['principais_causas_interpretadas'] }}</p>
                @endif

                @if (! empty($analysis->response['equipamentos_criticos_comentario']))
                    <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">Equipamentos críticos</p>
                    <p class="mt-1 text-sm">{{ $analysis->response['equipamentos_criticos_comentario'] }}</p>
                @endif

                @if (! empty($analysis->response['recomendacoes']))
                    <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">Recomendações</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($analysis->response['recomendacoes'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if (! empty($analysis->response['ranking_retrabalho']))
                <div class="fi-section rounded-xl border p-6">
                    <h3 class="font-bold text-warning-600 dark:text-warning-400">Ranking de retrabalho por ativo</h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400">
                                    <th class="pb-2 pr-4">Ativo</th>
                                    <th class="pb-2 pr-4">Total de retrabalhos</th>
                                    <th class="pb-2">Dias médio entre ocorrências</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analysis->response['ranking_retrabalho'] as $item)
                                    <tr class="border-t border-gray-100 dark:border-gray-700">
                                        <td class="py-2 pr-4 font-medium">{{ $item['nome'] }}</td>
                                        <td class="py-2 pr-4">{{ $item['total_retrabalhos'] }}</td>
                                        <td class="py-2">{{ $item['dias_medio_entre_ocorrencias'] }} dias</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (! empty($analysis->response['causas_classificadas']))
                <div class="fi-section rounded-xl border p-6">
                    <h3 class="font-bold text-gray-600 dark:text-gray-300">Causas classificadas (avarias)</h3>
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($analysis->response['causas_classificadas'] as $item)
                            <li>{{ $item['causa'] }}: <span class="font-medium">{{ $item['total'] }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @else
            <div class="fi-section rounded-xl border border-danger-300 p-6 text-danger-700 dark:border-danger-500/30 dark:text-danger-300">
                {{ $analysis->error }}
            </div>
        @endif
    @endif
</x-filament-panels::page>
