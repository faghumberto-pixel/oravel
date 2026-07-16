<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Janela de análise</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Reincidência = mesmo ativo com 2 ou mais avarias do mesmo tipo dentro do período.
                </p>
            </div>
            <select wire:model.live="days" class="fi-select-input block rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                @foreach ($this->getDaysOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Avarias por tipo --}}
    <div class="fi-section mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Avarias por tipo</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Todas as avarias registradas no período, agrupadas por tipo.</p>

        @php $maxTotal = max($this->porTipo->pluck('total')->max() ?? 1, 1); @endphp

        <div class="mt-4 space-y-2">
            @forelse ($this->porTipo as $tipo)
                <div class="flex items-center gap-3">
                    <span class="w-32 shrink-0 text-sm text-gray-700 dark:text-gray-300">{{ $tipo['label'] }}</span>
                    <div class="h-2.5 flex-1 rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-2.5 rounded-full bg-primary-500" style="width: {{ max(4, round(($tipo['total'] / $maxTotal) * 100)) }}%"></div>
                    </div>
                    <span class="w-8 shrink-0 text-right text-sm font-semibold text-gray-950 dark:text-white">{{ $tipo['total'] }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma avaria registrada nesse período.</p>
            @endforelse
        </div>
    </div>

    {{-- Reincidências --}}
    <div class="fi-section mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Reincidências</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Dados de apoio pra investigar a causa — peça aplicada e técnico responsável em cada ocorrência anterior.
            Não indicamos a causa automaticamente.
        </p>

        <div class="mt-4 space-y-4">
            @forelse ($this->reincidencias as $grupo)
                <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div>
                            @if ($grupo['asset'])
                                <a href="{{ \App\Filament\Resources\AssetResource::getUrl('edit', ['record' => $grupo['asset']]) }}"
                                   class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $grupo['asset']->patrimonio ?? '—' }} — {{ $grupo['asset']->name }}
                                </a>
                            @else
                                <span class="text-sm font-semibold text-gray-950 dark:text-white">Ativo removido</span>
                            @endif
                            <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">— {{ $grupo['damage_type_label'] }}</span>
                        </div>
                        <span class="fi-badge inline-flex items-center rounded-md bg-danger-50 px-2 py-1 text-xs font-medium text-danger-700 ring-1 ring-inset ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400">
                            {{ $grupo['total'] }} ocorrências
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-2 font-medium">Data</th>
                                    <th class="px-4 py-2 font-medium">Severidade</th>
                                    <th class="px-4 py-2 font-medium">Reportado por</th>
                                    <th class="px-4 py-2 font-medium">Técnico da OS</th>
                                    <th class="px-4 py-2 font-medium">Peças aplicadas na OS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grupo['ocorrencias'] as $ocorrencia)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                            @if ($ocorrencia->maintenance_order_id)
                                                <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $ocorrencia->maintenance_order_id]) }}"
                                                   class="text-primary-600 hover:underline dark:text-primary-400">
                                                    {{ $ocorrencia->created_at->format('d/m/Y') }}
                                                </a>
                                            @else
                                                {{ $ocorrencia->created_at->format('d/m/Y') }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 capitalize text-gray-700 dark:text-gray-300">{{ $ocorrencia->severity }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $ocorrencia->reportedBy?->name ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $ocorrencia->maintenanceOrder?->technician?->name ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                            @php $materiais = $ocorrencia->maintenanceOrder?->materials ?? collect(); @endphp
                                            @if ($materiais->isNotEmpty())
                                                {{ $materiais->map(fn ($m) => $m->material?->name ?? $m->name)->join(', ') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma reincidência no período selecionado.</p>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
