<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Janela de análise</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Avarias e alterações de Ativos no período. Preventivas vencidas mostram o estado atual, não têm data.
                </p>
            </div>
            <select wire:model.live="days" class="fi-select-input block rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                @foreach ($this->getDaysOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Pendências abertas --}}
    <div class="fi-section mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Pendências abertas</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Abertas a partir de uma Ordem de Serviço — notificam Supervisor, Gerente e Analista de Manutenção.</p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 font-medium">Aberta em</th>
                        <th class="px-4 py-2 font-medium">OS</th>
                        <th class="px-4 py-2 font-medium">Ativo</th>
                        <th class="px-4 py-2 font-medium">Descrição</th>
                        <th class="px-4 py-2 font-medium">Aberta por</th>
                        <th class="px-4 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->pendenciasAbertas as $pendencia)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $pendencia->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $pendencia->maintenance_order_id]) }}"
                                   class="text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $pendencia->maintenanceOrder?->os_number ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $pendencia->maintenanceOrder?->asset?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $pendencia->description }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $pendencia->createdBy?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                <button
                                    type="button"
                                    wire:click="resolverPendencia('{{ $pendencia->id }}')"
                                    wire:confirm="Marcar essa pendência como resolvida?"
                                    class="fi-btn fi-btn-size-sm inline-flex items-center rounded-lg bg-success-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-success-500"
                                >
                                    Marcar como resolvida
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nenhuma pendência aberta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Preventivas vencidas --}}
    <div class="fi-section mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Preventivas vencidas agora</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ordenado pelas horas de atraso, do maior pro menor.</p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 font-medium">Ativo</th>
                        <th class="px-4 py-2 font-medium">Atraso (horas)</th>
                        <th class="px-4 py-2 font-medium">Vencia em (horímetro)</th>
                        <th class="px-4 py-2 font-medium">Horímetro atual</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->preventivasVencidas as $linha)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2">
                                <a href="{{ \App\Filament\Resources\AssetResource::getUrl('edit', ['record' => $linha['asset']]) }}"
                                   class="text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $linha['asset']->name }}
                                </a>
                            </td>
                            <td class="px-4 py-2 font-semibold text-danger-600 dark:text-danger-400">
                                {{ number_format($linha['status']['overdue_hours'], 1) }}h
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ number_format($linha['status']['due_at_hours'], 1) }}h</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ number_format((float) $linha['asset']->horimetro_atual, 1) }}h</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nenhuma preventiva vencida agora.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Timeline de eventos --}}
    <div class="fi-section mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Linha do tempo</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Avarias e alterações de cadastro, mais recentes primeiro (até 200 eventos).</p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 font-medium">Data</th>
                        <th class="px-4 py-2 font-medium">Ativo</th>
                        <th class="px-4 py-2 font-medium">Evento</th>
                        <th class="px-4 py-2 font-medium">Detalhe</th>
                        <th class="px-4 py-2 font-medium">Responsável</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->eventos as $evento)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $evento['data']->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">
                                @if ($evento['asset'])
                                    <a href="{{ \App\Filament\Resources\AssetResource::getUrl('edit', ['record' => $evento['asset']]) }}"
                                       class="text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $evento['asset']->name }}
                                    </a>
                                @else
                                    <span class="text-gray-400">Ativo removido</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'fi-badge inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset',
                                    'bg-danger-50 text-danger-700 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400' => $evento['tipo'] === 'avaria',
                                    'bg-gray-50 text-gray-700 ring-gray-600/10 dark:bg-gray-400/10 dark:text-gray-400' => $evento['tipo'] === 'log',
                                ])>
                                    {{ $evento['titulo'] }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $evento['detalhe'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $evento['autor'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nenhum evento nesse período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
