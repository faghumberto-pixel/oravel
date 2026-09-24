@extends('layouts.app-signature')

@section('title', 'SLA - Acordo de Nível de Serviço | Oravel')

@section('styles')
@include('partials.legal-document-styles')
@endsection

@section('content')
<div class="legal-container">
    <div class="legal-panel">
        <div class="legal-print-bar no-print">
            <span></span>
            <button type="button" class="btn" onclick="window.print()">🖨️ Imprimir</button>
        </div>

        <div class="legal-header">
            <div class="legal-logo">O<span class="accent">r</span>avel</div>
            <div>
                <div class="legal-doc-title">SLA — Acordo de Nível de Serviço</div>
                <div class="legal-doc-tag">Anexo ao Contrato de Assinatura</div>
            </div>
        </div>

        <div class="clause">
            <span class="clause-title">1. Objeto.</span>
            Este Anexo define os parâmetros de disponibilidade, continuidade e suporte da
            plataforma Oravel, complementando o Contrato de Assinatura firmado entre a Oravel e
            o Contratante.
        </div>

        <div class="clause">
            <span class="clause-title">2. Disponibilidade (Uptime).</span>
            A Oravel envida seus melhores esforços para manter disponibilidade mensal de 99,9%,
            excluídas janelas de manutenção programada (comunicadas com antecedência mínima de 24
            horas sempre que possível) e eventos de força maior (falhas de provedores de
            infraestrutura terceiros, ataques cibernéticos, desastres naturais, instabilidade
            generalizada de internet).
        </div>

        <div class="clause">
            <span class="clause-title">3. Recuperação de desastres (RTO/RPO).</span>
            Em caso de incidente que afete a disponibilidade da plataforma, a Oravel busca
            restabelecer o serviço em até 4 horas (RTO — tempo objetivo de recuperação), com
            perda de dados limitada a, no máximo, 1 hora de operação anterior ao incidente (RPO —
            ponto objetivo de recuperação), respaldado pela rotina de backup descrita abaixo.
        </div>

        <div class="clause">
            <span class="clause-title">4. Backup.</span>
            Cópias de segurança automáticas são realizadas em intervalo de até 1 hora, com
            retenção mínima de 30 dias corridos, armazenadas em infraestrutura de nuvem
            geograficamente redundante.
        </div>

        <div class="clause">
            <span class="clause-title">5. Suporte técnico.</span>
            Suporte disponível para abertura de chamados via canal informado no painel do
            Contratante. Incidentes classificados como críticos (indisponibilidade total da
            plataforma) recebem atendimento prioritário 24 horas por dia, 7 dias por semana;
            demais solicitações são atendidas em horário comercial.
        </div>

        <div class="clause">
            <span class="clause-title">6. Exclusões.</span>
            Este SLA não cobre indisponibilidade decorrente de: (a) uso indevido da plataforma
            pelo Contratante ou por terceiros por ele autorizados; (b) falhas de conectividade ou
            equipamentos do próprio Contratante; (c) integrações de terceiros fora do controle da
            Oravel (ex.: instabilidade de gateways de pagamento); (d) inadimplência que resulte em
            suspensão de acesso nos termos do Contrato de Assinatura.
        </div>

        <div class="clause">
            <span class="clause-title">7. Relação com o Contrato de Assinatura.</span>
            Este Anexo é parte integrante do Contrato de Assinatura e da Licença de Uso firmados
            pelo Contratante, seguindo a mesma vigência, condições de reajuste e foro ali
            definidos.
        </div>
    </div>
</div>
@endsection
