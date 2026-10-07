<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        <select wire:model.live="meses" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
            <option value="1">Mês atual</option>
            <option value="3">3 meses</option>
            <option value="6">6 meses</option>
            <option value="12">12 meses</option>
        </select>
        <select wire:model.live="ordem" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
            @foreach (\App\Filament\Pages\FrotaRankingMotoristas::ordens() as $k => $nome)
                <option value="{{ $k }}">{{ $nome }}</option>
            @endforeach
        </select>
    </div>

    @php
        $linhas = $this->linhas();
    @endphp

    @if ($linhas->isEmpty())
        <div class="rounded-xl bg-gray-50 p-6 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">Nenhum motorista com saída, multa ou sinistro no período.</div>
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Motorista</th>
                        <th class="px-4 py-3 text-right">Saídas</th>
                        <th class="px-4 py-3 text-right">Km</th>
                        <th class="px-4 py-3 text-right">Multas</th>
                        <th class="px-4 py-3 text-right">Pontos (período)</th>
                        <th class="px-4 py-3 text-right">Pontos (12 meses)</th>
                        <th class="px-4 py-3 text-right">Sinistros</th>
                        <th class="px-4 py-3 text-right">Culpa própria</th>
                        <th class="px-4 py-3 text-right">km/l</th>
                        <th class="px-4 py-3 text-right">Ocorr. / 1.000 km</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($linhas as $i => $l)
                        <tr wire:key="{{ $l['motorista']->id }}">
                            <td class="px-4 py-3 text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $l['motorista']->name }}</td>
                            <td class="px-4 py-3 text-right">{{ $l['saidas'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $l['km'] ? number_format($l['km'], 0, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ $l['multas'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $l['pontos'] }}</td>
                            <td class="px-4 py-3 text-right {{ $l['pontos_12_meses'] >= \App\Models\FrotaMulta::PONTOS_CRITICO ? 'font-bold text-red-600 dark:text-red-400' : ($l['pontos_12_meses'] >= \App\Models\FrotaMulta::PONTOS_ATENCAO ? 'font-bold text-amber-600 dark:text-amber-400' : '') }}">{{ $l['pontos_12_meses'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $l['sinistros'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $l['sinistros_culpa_propria'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $l['consumo_km_l'] ? number_format($l['consumo_km_l'], 2, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $l['ocorrencias_por_mil_km'] !== null ? number_format($l['ocorrencias_por_mil_km'], 2, ',', '.') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            São indicadores para conversar com o motorista, não uma nota: rota, carga e tipo de veículo mudam muito os números.
            Ocorrências = multas + sinistros. Km = só das saídas já encerradas. Consumo = média dos abastecimentos do motorista com tanque cheio.
            Multas e sinistros entram pelo condutor indicado.
        </p>
    @endif
</x-filament-panels::page>
