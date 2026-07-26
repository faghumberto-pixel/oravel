@php
    $record = $getRecord();
    $response = $record->response ?? [];
@endphp

@if ($record->type === \App\Models\AIAnalysis::TYPE_AVARIA)
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="lg:col-span-2">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Diagnóstico provável</p>
            <p class="text-sm">{{ $response['diagnostico_provavel'] ?? '—' }}</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Causa raiz</p>
            <p class="text-sm">{{ $response['causa_raiz'] ?? '—' }}</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tempo estimado de reparo</p>
            <p class="text-sm">{{ $response['tempo_estimado_reparo'] ?? '—' }}</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ações corretivas</p>
            <ul class="list-disc pl-5 text-sm">
                @foreach ($response['acoes_corretivas'] ?? [] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Peças necessárias</p>
            <ul class="list-disc pl-5 text-sm">
                @foreach ($response['pecas_necessarias'] ?? [] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        <div class="lg:col-span-2">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Custo estimado</p>
            <p class="text-sm">
                @if (($response['custo_estimado_min'] ?? null) !== null)
                    R$ {{ number_format($response['custo_estimado_min'], 2, ',', '.') }} a R$ {{ number_format($response['custo_estimado_max'] ?? 0, 2, ',', '.') }}
                @else
                    —
                @endif
            </p>
        </div>
    </div>
@elseif ($record->type === \App\Models\AIAnalysis::TYPE_LOGISTICA)
    <p class="text-sm">{{ $response['resumo_geral'] ?? '' }}</p>

    @if (! empty($response['recomendacoes']))
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            @foreach ($response['recomendacoes'] as $recomendacao)
                <li>{{ $recomendacao }}</li>
            @endforeach
        </ul>
    @endif

    @if (! empty($response['dica_pratica']))
        <p class="mt-2 text-sm font-medium text-primary-600 dark:text-primary-400">💡 {{ $response['dica_pratica'] }}</p>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        @foreach ($response['rotas'] ?? [] as $rota)
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h4 class="font-bold">{{ $rota['veiculo'] }}</h4>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Sequência atual</p>
                <p class="text-sm">{{ implode(' → ', $rota['sequencia_atual'] ?? []) }} ({{ $rota['distancia_atual_km'] ?? 0 }} km)</p>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Sequência otimizada</p>
                <p class="text-sm">{{ implode(' → ', $rota['sequencia_otimizada'] ?? []) }} ({{ $rota['distancia_otimizada_km'] ?? 0 }} km)</p>
                <p class="mt-2 text-sm font-bold text-success-600 dark:text-success-400">
                    Economia: {{ $rota['economia_km'] ?? 0 }} km
                    @if (! empty($rota['economia_estimada_reais']))
                        (≈ R$ {{ number_format($rota['economia_estimada_reais'], 2, ',', '.') }})
                    @endif
                </p>
            </div>
        @endforeach
    </div>
@elseif ($record->type === \App\Models\AIAnalysis::TYPE_COMERCIAL)
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="lg:col-span-2">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Probabilidade de perda</p>
            <p class="text-sm">{{ $response['probabilidade_perda'] ?? '—' }}</p>
        </div>
        <div class="lg:col-span-2">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Recomendações</p>
            <ul class="list-disc pl-5 text-sm">
                @foreach ($response['recomendacoes'] ?? [] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        @if (! empty($response['email_sugerido']))
            <div class="lg:col-span-2">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">E-mail sugerido</p>
                <p class="whitespace-pre-line text-sm">{{ $response['email_sugerido'] }}</p>
            </div>
        @endif
    </div>
@elseif ($record->type === \App\Models\AIAnalysis::TYPE_ESTOQUE)
    <p class="text-sm">{{ $response['resumo_geral'] ?? '' }}</p>

    @if (! empty($response['prioridades_compra']))
        <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">Prioridades de compra</p>
        <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
            @foreach ($response['prioridades_compra'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    @endif

    @if (! empty($response['recomendacoes_estoque_parado']))
        <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">Estoque parado</p>
        <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
            @foreach ($response['recomendacoes_estoque_parado'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    @endif

    @if (! empty($response['materiais_criticos']))
        <p class="mt-4 text-sm font-medium text-gray-500 dark:text-gray-400">Materiais críticos</p>
        <div class="mt-2 grid grid-cols-1 gap-3 lg:grid-cols-2">
            @foreach ($response['materiais_criticos'] as $item)
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-bold">{{ $item['nome'] }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Atual: {{ $item['estoque_atual'] }} / Mínimo: {{ $item['estoque_minimo'] }}</p>
                    <p class="text-sm">
                        {{ $item['dias_estimados_para_esgotar'] !== null ? $item['dias_estimados_para_esgotar'].' dias até esgotar' : 'sem estimativa (sem consumo recente)' }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif
@else
    <pre class="overflow-x-auto rounded-lg bg-gray-50 p-4 text-xs dark:bg-gray-900">{{ json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
@endif
