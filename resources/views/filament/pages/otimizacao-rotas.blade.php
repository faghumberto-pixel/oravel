<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Distância calculada em linha reta (aproximada) a partir do pátio cadastrado até o cliente de cada mobilização — não reflete trânsito ou malha viária real.
    </p>

    @if ($analysis)
        @if ($analysis->status === \App\Models\AIAnalysis::STATUS_CONCLUIDA)
            <div class="fi-section rounded-xl border p-6">
                <h2 class="text-lg font-bold">Resumo — {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</h2>
                <p class="mt-2 text-sm">{{ $analysis->response['resumo_geral'] ?? '' }}</p>

                @if (! empty($analysis->response['recomendacoes']))
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($analysis->response['recomendacoes'] as $recomendacao)
                            <li>{{ $recomendacao }}</li>
                        @endforeach
                    </ul>
                @endif

                @if (! empty($analysis->response['dica_pratica']))
                    <p class="mt-3 text-sm font-medium text-primary-600 dark:text-primary-400">
                        💡 {{ $analysis->response['dica_pratica'] }}
                    </p>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @foreach ($analysis->response['rotas'] ?? [] as $rota)
                    <div class="fi-section rounded-xl border p-5">
                        <h3 class="font-bold">{{ $rota['veiculo'] }}</h3>

                        <div class="mt-3 text-sm">
                            <p class="font-medium text-gray-500 dark:text-gray-400">Sequência atual</p>
                            <p>{{ implode(' → ', $rota['sequencia_atual']) }}</p>
                            <p class="text-gray-500 dark:text-gray-400">{{ $rota['distancia_atual_km'] }} km</p>
                        </div>

                        <div class="mt-3 text-sm">
                            <p class="font-medium text-gray-500 dark:text-gray-400">Sequência otimizada</p>
                            <p>{{ implode(' → ', $rota['sequencia_otimizada']) }}</p>
                            <p class="text-gray-500 dark:text-gray-400">{{ $rota['distancia_otimizada_km'] }} km</p>
                        </div>

                        <p class="mt-3 text-sm font-bold text-success-600 dark:text-success-400">
                            Economia: {{ $rota['economia_km'] }} km
                            @if ($rota['economia_estimada_reais'])
                                (≈ R$ {{ number_format($rota['economia_estimada_reais'], 2, ',', '.') }})
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="fi-section rounded-xl border border-danger-300 p-6 text-danger-700 dark:border-danger-500/30 dark:text-danger-300">
                {{ $analysis->error }}
            </div>
        @endif
    @endif
</x-filament-panels::page>
