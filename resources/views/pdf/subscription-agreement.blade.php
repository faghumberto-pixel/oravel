<!--
    Contrato de Assinatura -- gerado dinamicamente a partir do Plano
    negociado com o cliente (2026-09-23, pedido do usuário: "sem mais plano
    padrão A/B/C, cada cliente tem um modelo decidido junto com o cliente").
    $contract aqui é na verdade o Tenant (mesma convenção de variável já
    usada por Contract/MaintenanceOrder em SignatureService::generateDocumentPdf(),
    mantida por consistência em vez de renomear).

    Boilerplate jurídico abaixo é um rascunho razoável, NÃO uma minuta
    revisada por advogado -- sinalizado no e-mail/tela pro usuário revisar
    com o jurídico antes de depender disso legalmente pra valer.
-->
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Assinatura - Oravel</title>
    <style>
        @page { margin: 1.5cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; margin: 0; padding: 0; }

        .header { border-bottom: 2px solid #E8541A; padding-bottom: 15px; margin-bottom: 25px; }
        .header table { width: 100%; border: none; }
        .logo-area { width: 60%; }
        .logo-text { font-size: 26px; font-weight: 800; color: #111827; letter-spacing: -1px; }
        .logo-text .accent { color: #E8541A; }
        .logo-subtext { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .title-area { width: 40%; text-align: right; }
        .title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #111827; }
        .contract-tag { font-family: 'Courier', monospace; font-size: 13px; color: #E8541A; font-weight: bold; }

        .section { margin-bottom: 20px; clear: both; }
        .section-title { background: #f9fafb; padding: 6px 12px; font-weight: bold; border-left: 4px solid #E8541A; color: #374151; text-transform: uppercase; font-size: 10px; margin-bottom: 12px; }

        table.data-grid { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.data-grid td { padding: 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .label { font-weight: bold; color: #4b5563; font-size: 9px; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .value { font-size: 11px; color: #111827; font-weight: 500; }

        ul.module-list { margin: 0; padding-left: 18px; }
        ul.module-list li { margin-bottom: 4px; }

        .clause { margin-bottom: 12px; text-align: justify; }
        .clause-title { font-weight: bold; color: #111827; }

        .footer { position: fixed; bottom: -10px; width: 100%; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 10px; }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td class="logo-area">
                    <div class="logo-text">O<span class="accent">r</span>avel</div>
                    <div class="logo-subtext">Asset Intelligence &amp; Maintenance Systems</div>
                </td>
                <td class="title-area">
                    <div class="title">Contrato de Assinatura</div>
                    <div class="contract-tag">{{ now()->format('Y-m') }}-{{ mb_strtoupper(mb_substr($contract->slug ?? $contract->id, 0, 8)) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Contratante</div>
        <table class="data-grid">
            <tr>
                <td style="width: 50%;">
                    <span class="label">Empresa</span>
                    <span class="value">{{ $contract->name }}</span>
                </td>
                <td style="width: 50%;">
                    <span class="label">CNPJ / CPF</span>
                    <span class="value">{{ $contract->cpf_cnpj ?? '—' }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Endereço</span>
                    <span class="value">{{ trim(($contract->logradouro ?? '').', '.($contract->numero ?? '').' — '.($contract->bairro ?? '').', '.($contract->cidade ?? '').'/'.($contract->uf ?? ''), ', —/') ?: '—' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Plano Contratado</div>
        <table class="data-grid">
            <tr>
                <td style="width: 50%;">
                    <span class="label">Plano</span>
                    <span class="value">{{ $contract->plan?->name ?? '—' }}</span>
                </td>
                <td style="width: 50%;">
                    <span class="label">Valor</span>
                    <span class="value">R$ {{ number_format((float) ($contract->mrr_value ?? $contract->plan?->base_price ?? 0), 2, ',', '.') }} / {{ match ($contract->plan?->billing_cycle) {
                        'quarterly' => 'trimestre',
                        'semiannual' => 'semestre',
                        'annual' => 'ano',
                        default => 'mês',
                    } }}</span>
                </td>
            </tr>
        </table>

        <div style="margin-top:12px;">
            <span class="label">Módulos incluídos</span>
            <ul class="module-list">
                @php
                    $allOptions = \App\Models\Plan::getAvailableFeaturesOptions();
                    $planFeatures = collect($contract->plan?->features ?? [])
                        ->map(fn ($key) => str_replace('Tabela: ', '', $allOptions[$key] ?? $key))
                        ->sort()
                        ->values();
                @endphp
                @forelse ($planFeatures as $moduleLabel)
                    <li>{{ $moduleLabel }}</li>
                @empty
                    <li>A definir junto com a proposta comercial</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Termos e Condições</div>

        @include('partials.subscription-agreement-clauses', ['contract' => $contract])
    </div>

    <div class="footer">
        Oravel — Contrato de Assinatura gerado eletronicamente em {{ now()->format('d/m/Y H:i') }}. Documento sujeito a assinatura digital com registro de IP, data/hora e hash de integridade.
    </div>

</body>
</html>
