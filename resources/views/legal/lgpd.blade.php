@extends('layouts.app-signature')

@section('title', 'LGPD - Conformidade e Proteção de Dados | Oravel')

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
                <div class="legal-doc-title">LGPD — Conformidade e Proteção de Dados</div>
                <div class="legal-doc-tag">Anexo ao Contrato de Assinatura</div>
            </div>
        </div>

        <div class="clause">
            <span class="clause-title">1. Papéis das partes.</span>
            Para os fins da Lei nº 13.709/2018 (LGPD), a Oravel atua como Operadora dos dados
            pessoais inseridos pelo Contratante na plataforma (ex.: dados de clientes,
            funcionários e técnicos do Contratante), e o Contratante atua como Controlador desses
            dados, definindo suas finalidades de tratamento.
        </div>

        <div class="clause">
            <span class="clause-title">2. Armazenamento e localização.</span>
            Os dados são armazenados em infraestrutura de nuvem localizada no Brasil (região São
            Paulo), sem transferência internacional de dados pessoais no curso normal da
            operação.
        </div>

        <div class="clause">
            <span class="clause-title">3. Segurança da informação.</span>
            Dados em repouso são protegidos por criptografia AES-256; dados em trânsito, por
            TLS 1.3. O acesso aos dados de cada Contratante é isolado logicamente por tenant,
            impedindo o acesso cruzado entre clientes distintos da plataforma.
        </div>

        <div class="clause">
            <span class="clause-title">4. Direitos dos titulares.</span>
            A Oravel provê, através do Contratante (Controlador) ou diretamente quando aplicável,
            meios para atendimento aos direitos dos titulares de dados previstos no art. 18 da
            LGPD: confirmação de tratamento, acesso, correção, anonimização, portabilidade,
            eliminação e informação sobre compartilhamento.
        </div>

        <div class="clause">
            <span class="clause-title">5. Retenção e eliminação.</span>
            Após o cancelamento do Contrato de Assinatura, os dados do Contratante permanecem
            disponíveis para exportação por até 30 dias corridos, findo os quais são eliminados de
            forma definitiva dos sistemas de produção da Oravel, ressalvadas as hipóteses de
            guarda obrigatória por lei (ex.: obrigações fiscais).
        </div>

        <div class="clause">
            <span class="clause-title">6. Subcontratação (subprocessadores).</span>
            A Oravel pode utilizar provedores de infraestrutura e serviços de terceiros
            (hospedagem em nuvem, envio de e-mail transacional, processamento de pagamentos)
            estritamente para viabilizar a prestação do serviço, exigindo desses provedores nível
            de proteção de dados compatível com a LGPD.
        </div>

        <div class="clause">
            <span class="clause-title">7. Notificação de incidentes.</span>
            Em caso de incidente de segurança que resulte em risco relevante aos dados pessoais
            tratados, a Oravel notificará o Contratante em prazo razoável, com informações
            suficientes para que este cumpra suas próprias obrigações legais de comunicação, se
            aplicável.
        </div>

        <div class="clause">
            <span class="clause-title">8. Relação com o Contrato de Assinatura.</span>
            Este Anexo é parte integrante do Contrato de Assinatura e da Licença de Uso firmados
            pelo Contratante, seguindo a mesma vigência e foro ali definidos.
        </div>
    </div>
</div>
@endsection
