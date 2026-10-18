<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        <label for="periodo-onb" class="text-sm font-medium text-gray-600 dark:text-gray-300">Período</label>
        <select id="periodo-onb" wire:model.live="periodo" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
            @foreach (\App\Filament\Central\Pages\OnboardingMetricasPage::PERIODOS as $valor => $rotulo)
                <option value="{{ $valor }}">{{ $rotulo }}</option>
            @endforeach
        </select>
        <span class="text-xs text-gray-500">Assinatura = empresa criada no período com a solução.</span>
    </div>

    @foreach ($this->getMetricas() as $m)
        <x-filament::section :heading="$m['titulo']" :description="$m['assinaturas'].' '.($m['assinaturas'] === 1 ? 'assinatura' : 'assinaturas')">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Concluíram os passos</div>
                    <div class="mt-1 text-3xl font-bold">{{ $m['pct_concluiram_todos'] }}%</div>
                    <div class="text-xs text-gray-500">{{ $m['concluiram_todos'] }} de {{ $m['assinaturas'] }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Concluíram em até 24 h</div>
                    <div class="mt-1 text-3xl font-bold">{{ $m['pct_em_24h'] }}%</div>
                    <div class="text-xs text-gray-500">{{ $m['concluiram_em_24h'] }} de {{ $m['assinaturas'] }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tempo mediano até concluir</div>
                    <div class="mt-1 text-3xl font-bold">{{ \App\Services\OnboardingMetricas::duracao($m['mediana_minutos']) }}</div>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="py-2 pr-4">Passo</th><th class="py-2 pr-4">Concluíram</th><th class="py-2">%</th></tr></thead>
                    <tbody>
                        @foreach ($m['passos'] as $p)
                            <tr class="border-t border-gray-200 dark:border-white/10"><td class="py-2 pr-4">{{ $p['titulo'] }}</td><td class="py-2 pr-4 tabular-nums">{{ $p['concluiram'] }}</td><td class="py-2 tabular-nums">{{ $p['percentual'] }}%</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
