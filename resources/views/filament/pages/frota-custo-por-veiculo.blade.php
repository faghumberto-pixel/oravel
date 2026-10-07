<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        <label class="text-sm text-gray-600 dark:text-gray-400" for="periodo">Período</label>
        <select id="periodo" wire:model.live="meses" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
            <option value="1">Mês atual</option>
            <option value="3">3 meses</option>
            <option value="6">6 meses</option>
            <option value="12">12 meses</option>
        </select>
        <span class="text-xs text-gray-500 dark:text-gray-400">Meses cheios, contando o mês atual.</span>
    </div>

    @php
        $linhas = $this->linhas();
        $totais = $this->totais($linhas);
        $nomes = $this->nomesComponentes();
        $dinheiro = fn ($v) => $v > 0 ? 'R$ '.number_format($v, 2, ',', '.') : '—';
    @endphp

    @if ($linhas->isEmpty())
        <div class="rounded-xl bg-gray-50 p-6 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">Nenhum custo no período.</div>
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Veículo</th>
                        @foreach ($nomes as $nome)
                            <th class="px-4 py-3 text-right">{{ $nome }}</th>
                        @endforeach
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Km</th>
                        <th class="px-4 py-3 text-right">Custo/km</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($linhas as $l)
                        <tr wire:key="{{ $l['veiculo']->id }}">
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $l['veiculo']->placa ?? $l['veiculo']->name }}</td>
                            @foreach ($nomes as $k => $nome)
                                <td class="px-4 py-3 text-right">{{ $dinheiro($l['componentes'][$k]) }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ $dinheiro($l['total']) }}</td>
                            <td class="px-4 py-3 text-right">{{ $l['km'] ? number_format($l['km'], 0, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ $l['custo_km'] ? 'R$ '.number_format($l['custo_km'], 2, ',', '.') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-bold dark:bg-white/5">
                    <tr>
                        <td class="px-4 py-3">Total da frota</td>
                        @foreach ($nomes as $k => $nome)
                            <td class="px-4 py-3 text-right">{{ $dinheiro($totais['componentes'][$k]) }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-right">{{ $dinheiro($totais['total']) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Pneus e baterias entram pelo custo de compra quando são instalados no veículo. Multas: só as pagas pela empresa. Sinistros: franquia
            (ou o orçamento, se não houver franquia nem OS). Seguro, IPVA e outros: rateados por mês. Manutenção: custo total das OS abertas no período.
        </p>
    @endif
</x-filament-panels::page>
