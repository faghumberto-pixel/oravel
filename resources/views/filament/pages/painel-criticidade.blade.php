<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">O que precisa de atenção agora</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Cruza Matriz ABC do ativo, preventiva vencida, avarias em aberto e reincidência de dano (últimos 90 dias).
            Ativos sem nenhum alerta não aparecem aqui.
        </p>

        @php
            $flagStyles = [
                'critico' => 'bg-danger-50 text-danger-700 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400',
                'atencao' => 'bg-warning-50 text-warning-700 ring-warning-600/10 dark:bg-warning-400/10 dark:text-warning-400',
            ];
            $flagLabels = ['critico' => 'Crítico', 'atencao' => 'Atenção'];
        @endphp

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 font-medium">Ativo</th>
                        <th class="px-4 py-2 font-medium">Matriz ABC</th>
                        <th class="px-4 py-2 font-medium">Preventiva</th>
                        <th class="px-4 py-2 font-medium">Avarias em Aberto</th>
                        <th class="px-4 py-2 font-medium">Reincidência (90d)</th>
                        <th class="px-4 py-2 font-medium">Nível</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->linhas as $linha)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2">
                                <a href="{{ \App\Filament\Resources\AssetResource::getUrl('edit', ['record' => $linha['asset']]) }}"
                                   class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $linha['asset']->patrimonio ?? '—' }} — {{ $linha['asset']->name }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                {{ $linha['nivel_abc'] ? "Nível {$linha['nivel_abc']}" : '—' }}
                            </td>
                            <td class="px-4 py-2">
                                @if ($linha['preventiva_vencida'])
                                    <span class="text-danger-600 dark:text-danger-400">Vencida</span>
                                @else
                                    <span class="text-gray-400">Em dia</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $linha['avarias_abertas'] }}</td>
                            <td class="px-4 py-2">
                                @if ($linha['reincidente'])
                                    <a href="{{ \App\Filament\Pages\AvariasReincidencia::getUrl() }}" class="text-primary-600 hover:underline dark:text-primary-400">Sim, ver detalhe →</a>
                                @else
                                    <span class="text-gray-400">Não</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span class="fi-badge inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $flagStyles[$linha['flag']] }}">
                                    {{ $flagLabels[$linha['flag']] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Nenhum ativo com alerta no momento.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
