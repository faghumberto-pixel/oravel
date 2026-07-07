<x-filament-panels::page>
    {{-- Desempenho por técnico --}}
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Desempenho por técnico</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Volume e tempo médio de execução (OS concluídas). Clique num número pra ver a lista de OS por trás dele.
            Dados objetivos — não é ranking, é ponto de partida pra conversa.
        </p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 font-medium">Técnico</th>
                        <th class="px-4 py-2 font-medium">Corretivas</th>
                        <th class="px-4 py-2 font-medium">Tempo médio (corretiva)</th>
                        <th class="px-4 py-2 font-medium">Preventivas</th>
                        <th class="px-4 py-2 font-medium">Tempo médio (preventiva)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->desempenho as $linha)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2 font-medium text-gray-950 dark:text-white">{{ $linha['technician']->name }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ $linha['url_corretivas'] }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $linha['corretivas'] }}</a>
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $linha['media_corretiva'] }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ $linha['url_preventivas'] }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $linha['preventivas'] }}</a>
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $linha['media_preventiva'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nenhuma OS concluída ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Retrabalho --}}
    <div class="fi-section mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Retrabalho</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Ativo recebeu uma nova OS dentro da janela abaixo, depois de uma OS anterior já ter sido concluída.
                    Cada linha é um caso — não mostramos só o total.
                </p>
            </div>
            <select wire:model.live="janelaRetrabalho" class="fi-select-input block rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                @foreach ($this->getJanelaOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 font-medium">Data</th>
                        <th class="px-4 py-2 font-medium">Ativo</th>
                        <th class="px-4 py-2 font-medium">Técnico</th>
                        <th class="px-4 py-2 font-medium">Cliente</th>
                        <th class="px-4 py-2 font-medium">Local</th>
                        <th class="px-4 py-2 font-medium">Dano</th>
                        <th class="px-4 py-2 font-medium">Tipo</th>
                        <th class="px-4 py-2 font-medium">Voltou em</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->retrabalhos as $linha)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $linha['os']]) }}"
                                   class="text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $linha['data']->format('d/m/Y') }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $linha['os']->asset?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                {{ $linha['tecnico'] }}
                                @if ($linha['tecnico'] !== $linha['tecnico_anterior'])
                                    <span class="text-xs text-gray-400">(anterior: {{ $linha['tecnico_anterior'] }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $linha['cliente'] }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $linha['local'] }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $linha['dano'] }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $linha['tipo'] }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $linha['dias_depois'] }} dia(s)</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nenhum retrabalho na janela selecionada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
