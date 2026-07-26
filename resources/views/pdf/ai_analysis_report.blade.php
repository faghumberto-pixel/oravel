<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Análise por IA - Oravel</title>
    <style>
        @page { margin: 1.5cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; margin: 0; padding: 0; }

        .header { border-bottom: 2px solid #dc2626; padding-bottom: 15px; margin-bottom: 25px; }
        .header table { width: 100%; border: none; }
        .logo-area { width: 60%; }
        .logo-text { font-size: 26px; font-weight: 800; color: #dc2626; letter-spacing: -1px; }
        .logo-subtext { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .title-area { width: 40%; text-align: right; }
        .title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #111827; }
        .meta-tag { font-size: 10px; color: #6b7280; }

        .section { margin-bottom: 20px; clear: both; }
        .section-title { background: #f9fafb; padding: 6px 12px; font-weight: bold; border-left: 4px solid #dc2626; color: #374151; text-transform: uppercase; font-size: 10px; margin-bottom: 10px; }

        table.data-grid { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.data-grid td { padding: 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .label { font-weight: bold; color: #4b5563; font-size: 9px; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .value { font-size: 11px; color: #111827; font-weight: 500; }

        ul { margin: 4px 0; padding-left: 18px; }
        li { margin-bottom: 3px; }

        .disclaimer { margin-top: 20px; padding: 10px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 4px; font-size: 9px; color: #92400e; }

        .footer { position: fixed; bottom: -10px; width: 100%; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 10px; }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td class="logo-area">
                    <div class="logo-text">ORAVEL</div>
                    <div class="logo-subtext">Asset Intelligence & Maintenance Systems</div>
                </td>
                <td class="title-area">
                    <div class="title">{{ $typeLabel }}</div>
                    <div class="meta-tag">Gerado em {{ $generatedAt }}</div>
                    <div class="meta-tag">Solicitado por {{ $analysis->user?->name ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    @php($response = $analysis->response ?? [])

    @if ($analysis->type === \App\Models\AIAnalysis::TYPE_AVARIA)
        @if ($analysis->equipmentDamage?->asset)
            <div class="section">
                <div class="section-title">Equipamento</div>
                <table class="data-grid">
                    <tr>
                        <td><span class="label">Patrimônio</span><span class="value">{{ $analysis->equipmentDamage->asset->patrimonio }}</span></td>
                        <td><span class="label">Ativo</span><span class="value">{{ $analysis->equipmentDamage->asset->name }}</span></td>
                    </tr>
                </table>
            </div>
        @endif

        <div class="section">
            <div class="section-title">Diagnóstico Provável</div>
            <p>{{ $response['diagnostico_provavel'] ?? '—' }}</p>
        </div>
        <div class="section">
            <div class="section-title">Causa Raiz</div>
            <p>{{ $response['causa_raiz'] ?? '—' }}</p>
        </div>
        <div class="section">
            <div class="section-title">Ações Corretivas</div>
            <ul>
                @foreach ($response['acoes_corretivas'] ?? [] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        <div class="section">
            <div class="section-title">Peças Necessárias</div>
            <ul>
                @foreach ($response['pecas_necessarias'] ?? [] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        <div class="section">
            <table class="data-grid">
                <tr>
                    <td><span class="label">Tempo estimado de reparo</span><span class="value">{{ $response['tempo_estimado_reparo'] ?? '—' }}</span></td>
                    <td>
                        <span class="label">Custo estimado</span>
                        <span class="value">
                            @if (($response['custo_estimado_min'] ?? null) !== null)
                                R$ {{ number_format($response['custo_estimado_min'], 2, ',', '.') }} a R$ {{ number_format($response['custo_estimado_max'] ?? 0, 2, ',', '.') }}
                            @else
                                —
                            @endif
                        </span>
                    </td>
                </tr>
            </table>
        </div>
    @elseif ($analysis->type === \App\Models\AIAnalysis::TYPE_LOGISTICA)
        <div class="section">
            <div class="section-title">Resumo</div>
            <p>{{ $response['resumo_geral'] ?? '' }}</p>
            @if (! empty($response['dica_pratica']))
                <p><strong>Dica prática:</strong> {{ $response['dica_pratica'] }}</p>
            @endif
        </div>

        @foreach ($response['rotas'] ?? [] as $rota)
            <div class="section">
                <div class="section-title">{{ $rota['veiculo'] }}</div>
                <table class="data-grid">
                    <tr>
                        <td>
                            <span class="label">Sequência atual ({{ $rota['distancia_atual_km'] ?? 0 }} km)</span>
                            <span class="value">{{ implode(' → ', $rota['sequencia_atual'] ?? []) }}</span>
                        </td>
                        <td>
                            <span class="label">Sequência otimizada ({{ $rota['distancia_otimizada_km'] ?? 0 }} km)</span>
                            <span class="value">{{ implode(' → ', $rota['sequencia_otimizada'] ?? []) }}</span>
                        </td>
                    </tr>
                </table>
                <p><strong>Economia: {{ $rota['economia_km'] ?? 0 }} km</strong>
                    @if (! empty($rota['economia_estimada_reais']))
                        (≈ R$ {{ number_format($rota['economia_estimada_reais'], 2, ',', '.') }})
                    @endif
                </p>
            </div>
        @endforeach
    @elseif ($analysis->type === \App\Models\AIAnalysis::TYPE_COMERCIAL)
        <div class="section">
            <div class="section-title">Probabilidade de Perda</div>
            <p>{{ $response['probabilidade_perda'] ?? '—' }}</p>
        </div>
        <div class="section">
            <div class="section-title">Recomendações</div>
            <ul>
                @foreach ($response['recomendacoes'] ?? [] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="section">
            <pre>{{ json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif

    <div class="disclaimer">
        Análise gerada por inteligência artificial (Claude, Anthropic) a partir dos dados cadastrados no sistema — é uma sugestão de apoio à decisão, não um laudo técnico definitivo. Deve ser validada por um responsável antes de qualquer ação.
    </div>

    <div class="footer">Oravel — documento gerado eletronicamente em {{ $generatedAt }}</div>

</body>
</html>
