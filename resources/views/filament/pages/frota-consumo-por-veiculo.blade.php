<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        <label class="text-sm text-gray-600 dark:text-gray-400" for="periodo">Período</label>
        <select id="periodo" wire:model.live="meses" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
            <option value="1">Último mês</option>
            <option value="3">3 meses</option>
            <option value="6">6 meses</option>
            <option value="12">12 meses</option>
        </select>
    </div>

    @php
        $linhas = $this->linhas();
    @endphp

    @if ($linhas->isEmpty())
        <div class="rounded-xl bg-gray-50 p-6 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">Nenhum abastecimento no período.</div>
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Veículo</th>
                        <th class="px-4 py-3 text-right">Abast.</th>
                        <th class="px-4 py-3 text-right">Litros</th>
                        <th class="px-4 py-3 text-right">Gasto</th>
                        <th class="px-4 py-3 text-right">Km</th>
                        <th class="px-4 py-3 text-right">km/l médio</th>
                        <th class="px-4 py-3 text-right">Custo/km</th>
                        <th class="px-4 py-3">Último consumo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($linhas as $l)
                        @php
                            $r = $l['resumo'];
                            $cor = match ($l['desvio']['situacao'] ?? 'sem_base') {
                                'critica' => 'text-red-600 dark:text-red-400',
                                'atencao' => 'text-amber-600 dark:text-amber-400',
                                'normal' => 'text-emerald-600 dark:text-emerald-400',
                                default => 'text-gray-500',
                            };
                        @endphp
                        <tr wire:key="{{ $l['veiculo']->id }}">
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $l['veiculo']->placa ?? $l['veiculo']->name }}</td>
                            <td class="px-4 py-3 text-right">{{ $r['abastecimentos'] }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($r['litros'], 1, ',', '.') }} L</td>
                            <td class="px-4 py-3 text-right">R$ {{ number_format($r['gasto'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ $r['km'] ? number_format($r['km'], 0, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ $r['km_l'] ? number_format($r['km_l'], 2, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ $r['custo_km'] ? 'R$ '.number_format($r['custo_km'], 2, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3 font-semibold {{ $cor }}">
                                @if ($l['ultimo'])
                                    {{ number_format((float) $l['ultimo']->consumo_km_l, 2, ',', '.') }} km/l
                                    @if ($l['desvio']['media'])
                                        <span class="font-normal text-gray-500">(média {{ number_format($l['desvio']['media'], 2, ',', '.') }})</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">km/l e custo por km usam só os trechos entre dois tanques cheios. Consumo em vermelho/laranja = abaixo do habitual do próprio veículo.</p>
    @endif
</x-filament-panels::page>
