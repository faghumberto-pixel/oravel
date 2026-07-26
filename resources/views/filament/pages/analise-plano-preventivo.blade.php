<x-filament-panels::page>
    <div class="flex items-center gap-3">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Considerar "quebrou logo após" quando for menos de</label>
        <select wire:model.live="thresholdQuebraDias" class="fi-select-input block rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            @foreach ($this->getThresholdOptions() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @if ($analysis)
        @if ($analysis->status === \App\Models\AIAnalysis::STATUS_CONCLUIDA)
            <div class="fi-section rounded-xl border p-6">
                <h2 class="text-lg font-bold">Resumo</h2>
                <p class="mt-2 text-sm">{{ $analysis->response['resumo_geral'] ?? '' }}</p>

                @if (! empty($analysis->response['equipamentos_criticos_comentario']))
                    <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">Equipamentos críticos</p>
                    <p class="mt-1 text-sm">{{ $analysis->response['equipamentos_criticos_comentario'] }}</p>
                @endif

                @if (! empty($analysis->response['padroes_quebra_pos_preventiva']))
                    <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">Padrões de quebra pós-preventiva</p>
                    <p class="mt-1 text-sm">{{ $analysis->response['padroes_quebra_pos_preventiva'] }}</p>
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

            @if (! empty($analysis->response['equipamentos_atrasados']))
                <div class="fi-section rounded-xl border p-6">
                    <h3 class="font-bold text-danger-600 dark:text-danger-400">Equipamentos com preventiva atrasada</h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400">
                                    <th class="pb-2 pr-4">Ativo</th>
                                    <th class="pb-2 pr-4">Plano</th>
                                    <th class="pb-2 pr-4">Horas de atraso</th>
                                    <th class="pb-2">Dias de atraso</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analysis->response['equipamentos_atrasados'] as $item)
                                    <tr class="border-t border-gray-100 dark:border-gray-700">
                                        <td class="py-2 pr-4 font-medium">{{ $item['nome'] }}</td>
                                        <td class="py-2 pr-4">{{ $item['plano'] }}</td>
                                        <td class="py-2 pr-4">{{ $item['overdue_hours'] }}</td>
                                        <td class="py-2">{{ $item['overdue_days'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (! empty($analysis->response['quebras_pos_preventiva']))
                <div class="fi-section rounded-xl border p-6">
                    <h3 class="font-bold text-warning-600 dark:text-warning-400">Quebras logo após a preventiva</h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400">
                                    <th class="pb-2 pr-4">Ativo</th>
                                    <th class="pb-2 pr-4">Dias até quebrar</th>
                                    <th class="pb-2">Quebrou logo após?</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analysis->response['quebras_pos_preventiva'] as $item)
                                    <tr class="border-t border-gray-100 dark:border-gray-700">
                                        <td class="py-2 pr-4 font-medium">{{ $item['nome'] }}</td>
                                        <td class="py-2 pr-4">{{ $item['dias_ate_quebra'] }}</td>
                                        <td class="py-2">
                                            @if ($item['quebrou_logo_apos'])
                                                <span class="fi-badge inline-flex items-center rounded-md bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">Sim</span>
                                            @else
                                                <span class="fi-badge inline-flex items-center rounded-md bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Não</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (! empty($analysis->response['mtbf_por_ativo']))
                <div class="fi-section rounded-xl border p-6">
                    <h3 class="font-bold text-gray-600 dark:text-gray-300">Tempo médio sem quebrar (MTBF simplificado)</h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400">
                                    <th class="pb-2 pr-4">Ativo</th>
                                    <th class="pb-2 pr-4">Dias médio sem quebrar</th>
                                    <th class="pb-2">Horas médias trabalhadas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analysis->response['mtbf_por_ativo'] as $item)
                                    <tr class="border-t border-gray-100 dark:border-gray-700">
                                        <td class="py-2 pr-4 font-medium">{{ $item['nome'] }}</td>
                                        <td class="py-2 pr-4">{{ $item['media_dias_sem_quebrar'] }}</td>
                                        <td class="py-2">{{ $item['media_horas_trabalhadas'] ?? '—' }}</td>
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
