{{--
    Anexo II do Contrato de Assinatura -- Taxas de configuração aprovadas pelo Contratante (opcionais, pagamento único).
    Compartilhado entre o PDF e a tela de assinatura. Espera $contract (o Tenant). Só aparece quando há taxa aprovada.
--}}
@php
    $taxasAprovadas = $contract instanceof \App\Models\Tenant
        ? \App\Models\TenantTaxa::withoutGlobalScopes()->where('tenant_id', $contract->id)->where('status', '!=', \App\Models\TenantTaxa::CANCELADA)->orderBy('created_at')->get()
        : collect();
@endphp

@if ($taxasAprovadas->isNotEmpty())
    <div style="margin-top:14px; padding:10px 12px; border-left:4px solid #0f172a; background:#f1f5f9;">
        <div class="clause-title" style="font-size:12px;">ANEXO II — TAXAS DE CONFIGURAÇÃO APROVADAS</div>
        <div class="clause" style="margin-top:6px;">
            O Contratante aprovou, de forma opcional, os serviços de configuração abaixo. Cada taxa é <strong>cobrada uma única vez</strong>,
            separada da mensalidade, e remunera apenas a configuração descrita. Os serviços não aprovados podem ser feitos pelo próprio Contratante,
            com o treinamento disponível na Academia Oravel.
        </div>
        @foreach ($taxasAprovadas as $taxa)
            <div class="clause"><span class="clause-title">{{ $taxa->nome }}</span> — R$ {{ number_format((float) $taxa->valor, 2, ',', '.') }} (pagamento único).</div>
        @endforeach
        <div class="clause"><span class="clause-title">Total das taxas:</span> R$ {{ number_format((float) $taxasAprovadas->sum('valor'), 2, ',', '.') }}.</div>
    </div>
@endif
