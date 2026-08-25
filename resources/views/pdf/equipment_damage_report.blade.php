<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Laudo Técnico de Avaria - Oravel</title>
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
        .os-tag { font-family: 'Courier', monospace; font-size: 16px; color: #dc2626; font-weight: bold; }

        .section { margin-bottom: 25px; clear: both; }
        .section-title { background: #f9fafb; padding: 6px 12px; font-weight: bold; border-left: 4px solid #dc2626; color: #374151; text-transform: uppercase; font-size: 10px; margin-bottom: 12px; }

        table.data-grid { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.data-grid td { padding: 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .label { font-weight: bold; color: #4b5563; font-size: 9px; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .value { font-size: 11px; color: #111827; font-weight: 500; }

        .description-box { padding: 10px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 11px; }

        .evidence-container { margin-top: 10px; }
        .evidence-card { page-break-inside: avoid; border: 1px solid #e5e7eb; border-radius: 4px; padding: 0; margin-bottom: 20px; background: #ffffff; overflow: hidden; }
        .evidence-header { padding: 6px 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; color: #ffffff; }
        .evidence-header.severity-grave { background: #dc2626; }
        .evidence-header.severity-moderada { background: #d97706; }
        .evidence-header.severity-leve { background: #059669; }
        .evidence-body { padding: 12px; }
        .evidence-img { width: 100%; max-height: 380px; object-fit: contain; border-radius: 2px; margin-bottom: 10px; display: block; }
        .evidence-meta-table { width: 100%; background: #f3f4f6; padding: 8px; border-radius: 4px; }
        .evidence-meta-table td { border: none !important; padding: 2px 5px !important; font-size: 9px !important; }
        .meta-icon { color: #dc2626; font-weight: bold; }

        .followup-table { width: 100%; border-collapse: collapse; }
        .followup-table th, .followup-table td { border: 1px solid #e5e7eb; padding: 6px 8px; font-size: 9px; text-align: left; vertical-align: top; }
        .followup-table th { background: #f9fafb; text-transform: uppercase; color: #4b5563; }

        .signatures-area { margin-top: 40px; page-break-inside: avoid; }
        .signature-table { width: 100%; border: none; }
        .signature-box { width: 48%; text-align: center; vertical-align: bottom; }
        .signature-line { border-top: 1px solid #9ca3af; margin: 0 10px; padding-top: 8px; }
        .signature-image { max-width: 280px; max-height: 140px; margin-bottom: -15px; filter: contrast(150%); }
        .signature-placeholder { height: 80px; }

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
                    <div class="title">Laudo Técnico de Avaria de Equipamento</div>
                    <div class="os-tag">OS #{{ $damage->maintenanceOrder->os_number }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Informações do Equipamento e Ocorrência</div>
        <table class="data-grid">
            <tr>
                <td style="width: 50%;">
                    <span class="label">Equipamento / Ativo</span>
                    <span class="value">{{ $damage->asset->name }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Reportado por</span>
                    <span class="value">{{ $damage->reportedBy->name ?? 'N/A' }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Data do Registro</span>
                    <span class="value">{{ $damage->created_at->format('d/m/Y H:i') }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Diagnóstico Técnico</div>
        <table class="data-grid">
            <tr>
                <td style="width: 33%;">
                    <span class="label">Severidade</span>
                    <span class="value">
                        @switch($damage->severity)
                            @case('grave') GRAVE / PERDA TOTAL @break
                            @case('moderada') MODERADA @break
                            @default LEVE
                        @endswitch
                    </span>
                </td>
                <td style="width: 33%;">
                    <span class="label">Exige Substituição do Equipamento?</span>
                    <span class="value">{{ $damage->requires_replacement ? 'SIM' : 'NÃO' }}</span>
                </td>
                <td style="width: 34%;">
                    <span class="label">Status Atual</span>
                    <span class="value">{{ strtoupper(str_replace('_', ' ', $damage->status)) }}</span>
                </td>
            </tr>
            <tr>
                <td style="width: 33%;">
                    <span class="label">Causa Atribuída</span>
                    <span class="value">{{ $damage->cause ? (\App\Models\EquipmentDamage::causeLabels()[$damage->cause] ?? $damage->cause) : 'Não classificada' }}</span>
                </td>
                <td style="width: 33%;">
                    <span class="label">Cobrável ao Cliente?</span>
                    <span class="value">{{ $damage->isBillableToClient() ? 'SIM' : 'NÃO' }}</span>
                </td>
                <td style="width: 34%;"></td>
            </tr>
        </table>
        <div class="description-box">{{ $damage->description }}</div>
    </div>

    <div class="section">
        <div class="section-title">Registro Fotográfico e Rastreabilidade Satélite</div>
        <div class="evidence-container">
            @forelse($damage->getMedia('photos') as $photo)
                <div class="evidence-card">
                    <div class="evidence-header severity-{{ $damage->severity }}">
                        Evidência #{{ $loop->iteration }}
                    </div>
                    <div class="evidence-body">
                        <img src="{{ $photo->getPath() }}" class="evidence-img">
                        <table class="evidence-meta-table">
                            <tr>
                                <td style="width: 15%;"><span class="meta-icon">🌍 GPS:</span></td>
                                <td>
                                    @if($photo->getCustomProperty('latitude'))
                                        Lat: {{ $photo->getCustomProperty('latitude') }} | Long: {{ $photo->getCustomProperty('longitude') }}
                                    @else
                                        Não capturado
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><span class="meta-icon">⏰ DATA:</span></td>
                                <td>
                                    {{ $photo->getCustomProperty('captured_at') ? \Carbon\Carbon::parse($photo->getCustomProperty('captured_at'))->format('d/m/Y H:i:s') : $photo->created_at->format('d/m/Y H:i:s') }}
                                    (Timestamp Infalsificável)
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            @empty
                <div style="padding: 20px; text-align: center; border: 1px dashed #ccc;">
                    Nenhuma evidência fotográfica registrada para esta avaria.
                </div>
            @endforelse
        </div>
    </div>

    @if($damage->supervisor_reviewed_at)
        <div class="section">
            <div class="section-title">Revisão do Supervisor de Manutenção</div>
            <table class="data-grid">
                <tr>
                    <td style="width: 50%;">
                        <span class="label">Revisado por</span>
                        <span class="value">{{ $damage->supervisorReviewedBy->name ?? 'N/A' }}</span>
                    </td>
                    <td style="width: 50%;">
                        <span class="label">Em</span>
                        <span class="value">{{ $damage->supervisor_reviewed_at->format('d/m/Y H:i') }}</span>
                    </td>
                </tr>
            </table>
            @if($damage->supervisor_notes)
                <div class="description-box" style="margin-top: 5px;">{{ $damage->supervisor_notes }}</div>
            @endif
        </div>
    @endif

    @if($damage->commercial_reviewed_at || $damage->estimated_cost)
        <div class="section">
            <div class="section-title">Tratativa Comercial</div>
            <table class="data-grid">
                <tr>
                    <td style="width: 33%;">
                        <span class="label">Valor Estimado</span>
                        <span class="value">{{ $damage->estimated_cost ? 'R$ '.number_format($damage->estimated_cost, 2, ',', '.') : 'Não definido' }}</span>
                    </td>
                    <td style="width: 34%;">
                        <span class="label">Ativo Substituto Vinculado</span>
                        <span class="value">{{ $damage->replacementAsset->name ?? 'Nenhum' }}</span>
                    </td>
                    <td style="width: 33%;">
                        <span class="label">Tratado por</span>
                        <span class="value">{{ $damage->commercialReviewedBy->name ?? 'N/A' }}</span>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    @php
        $approvedQuote = $damage->quotes->whereIn('status', [
            \App\Models\Quote::STATUS_APROVADO,
            \App\Models\Quote::STATUS_CONCLUIDO,
        ])->sortByDesc('created_at')->first();
    @endphp
    @if($approvedQuote)
        <div class="section">
            <div class="section-title">Orçamento Indenizatório</div>
            <table class="data-grid">
                <tr>
                    <td style="width: 34%;">
                        <span class="label">Valor Aprovado</span>
                        <span class="value">R$ {{ number_format($approvedQuote->total_value, 2, ',', '.') }}</span>
                    </td>
                    <td style="width: 33%;">
                        <span class="label">Status do Orçamento</span>
                        <span class="value">{{ \App\Models\Quote::statusLabels()[$approvedQuote->status] ?? $approvedQuote->status }}</span>
                    </td>
                    <td style="width: 33%;">
                        <span class="label">Encaminhado ao Financeiro</span>
                        <span class="value">{{ $approvedQuote->financeiro_forwarded_at?->format('d/m/Y H:i') ?? 'Ainda não' }}</span>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    @if($damage->followUps->isNotEmpty())
        <div class="section">
            <div class="section-title">Histórico de Contatos de Cobrança</div>
            <table class="followup-table">
                <thead>
                    <tr>
                        <th style="width: 12%;">Data</th>
                        <th style="width: 10%;">Canal</th>
                        <th style="width: 15%;">Responsável</th>
                        <th style="width: 43%;">Resumo</th>
                        <th style="width: 20%;">Próxima Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($damage->followUps->sortBy('contact_date') as $followUp)
                        <tr>
                            <td>{{ $followUp->contact_date->format('d/m/Y H:i') }}</td>
                            <td>{{ strtoupper($followUp->channel) }}</td>
                            <td>{{ $followUp->user->name ?? 'N/A' }}</td>
                            <td>{{ $followUp->summary }}</td>
                            <td>
                                {{ $followUp->next_action }}
                                @if($followUp->next_action_date)
                                    <br>({{ $followUp->next_action_date->format('d/m/Y') }})
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="signatures-area">
        <div class="section-title">Validação e Ciência das Partes</div>
        <table class="signature-table">
            <tr>
                <td class="signature-box">
                    <div class="signature-placeholder"></div>
                    <div class="signature-line"></div>
                    <span class="value">{{ $damage->reportedBy->name ?? 'Técnico Responsável' }}</span><br>
                    <span class="label" style="text-align: center;">Técnico / Oravel System</span>
                </td>
                <td class="signature-box">
                    <div class="signature-placeholder">
                        @if($damage->client_signature)
                            <img src="{{ $damage->client_signature }}" class="signature-image">
                        @endif
                    </div>
                    <div class="signature-line"></div>
                    <span class="value">
                        @if($damage->client_acknowledged_at)
                            Assinado em {{ $damage->client_acknowledged_at->format('d/m/Y H:i') }}
                        @else
                            Ciência não coletada
                        @endif
                    </span><br>
                    <span class="label" style="text-align: center;">Cliente / Ciência do Dano</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Este documento é parte integrante do sistema de gestão <strong>Oravel</strong>.
        Gerado em {{ $generatedAt }}.
        A autenticidade deste laudo técnico é garantida pela integração de metadados geográficos e assinatura digital.
    </div>

</body>
</html>
