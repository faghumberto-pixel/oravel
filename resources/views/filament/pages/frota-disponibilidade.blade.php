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
        $media = $this->mediaDaFrota($linhas);
        $cor = fn ($s) => match ($s) {
            'boa' => 'text-emerald-600 dark:text-emerald-400',
            'atencao' => 'text-amber-600 dark:text-amber-400',
            default => 'text-red-600 dark:text-red-400',
        };
    @endphp

    @if ($linhas->isEmpty())
        <div class="rounded-xl bg-gray-50 p-6 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">Nenhum veículo cadastrado.</div>
    @else
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Disponibilidade média da frota</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($media, 1, ',', '.') }}%</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Meta: {{ number_format(\App\Services\Frota\DisponibilidadeFrotaService::META, 0) }}% · mínimo aceitável: {{ number_format(\App\Services\Frota\DisponibilidadeFrotaService::MINIMO, 0) }}%</p>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Veículo</th>
                        <th class="px-4 py-3 text-right">Disponibilidade</th>
                        <th class="px-4 py-3 text-right">Horas parado</th>
                        <th class="px-4 py-3 text-right">Paradas</th>
                        <th class="px-4 py-3">Principal causa</th>
                        <th class="px-4 py-3 text-right">Horas fora (em uso)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($linhas as $l)
                        <tr wire:key="{{ $l['veiculo']->id }}">
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $l['veiculo']->placa ?? $l['veiculo']->name }}</td>
                            <td class="px-4 py-3 text-right font-bold {{ $cor($l['situacao']) }}">{{ number_format($l['disponibilidade'], 1, ',', '.') }}%</td>
                            <td class="px-4 py-3 text-right">{{ number_format($l['parado_horas'], 1, ',', '.') }} h</td>
                            <td class="px-4 py-3 text-right">{{ $l['paradas'] }}</td>
                            <td class="px-4 py-3">{{ $l['causa_principal'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($l['fora_horas'], 1, ',', '.') }} h</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Parado = paradas do ativo (quebra, manutenção, aguardando peça) e sinistros com o veículo parado; "ocioso/sem uso" não conta.
            Paradas que se sobrepõem são unidas (nada conta duas vezes). Veículo cadastrado no meio do período conta a partir do cadastro.
            "Horas fora" é o tempo em saída registrada (uso), e não reduz a disponibilidade.
        </p>
    @endif
</x-filament-panels::page>
