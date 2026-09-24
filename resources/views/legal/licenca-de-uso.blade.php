@extends('layouts.app-signature')

@section('title', 'Contrato de Licença de Uso | Oravel')

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
                <div class="legal-doc-title">Contrato de Licença de Uso de Software</div>
                <div class="legal-doc-tag">Anexo ao Contrato de Assinatura</div>
            </div>
        </div>

        <div class="clause">
            <span class="clause-title">1. Concessão da licença.</span>
            A Oravel concede ao Contratante, exclusivamente pelo prazo de vigência do Contrato de
            Assinatura e enquanto este permanecer adimplente, uma licença de uso não exclusiva,
            intransferível e revogável para acessar e utilizar a plataforma Oravel (SaaS),
            limitada aos módulos efetivamente contratados, para os fins próprios da atividade do
            Contratante.
        </div>

        <div class="clause">
            <span class="clause-title">2. Natureza da licença — não é venda de software.</span>
            Esta licença confere apenas o direito de USO da plataforma nos termos aqui descritos.
            O Contratante não adquire, a qualquer título, propriedade sobre o software, seu
            código-fonte, layout, banco de dados estrutural, marcas ou qualquer outro elemento de
            propriedade intelectual da Oravel — ver Cláusula 4.
        </div>

        <div class="clause">
            <span class="clause-title">3. Vigência atrelada ao Contrato de Assinatura.</span>
            A licença de uso vigora enquanto o Contrato de Assinatura estiver ativo e o pagamento
            em dia. Encerrado o Contrato de Assinatura — por cancelamento solicitado pelo
            Contratante, inadimplência não regularizada dentro do prazo de tolerância, ou qualquer
            outra hipótese de rescisão nele prevista — esta licença se extingue automaticamente,
            independentemente de notificação adicional, cessando o direito de acesso e uso da
            plataforma. A suspensão temporária de acesso por inadimplência (Cláusula 6 do Contrato
            de Assinatura) suspende também o exercício desta licença pelo mesmo período,
            restabelecendo-se automaticamente quando o acesso for restabelecido.
        </div>

        <div class="clause">
            <span class="clause-title">4. Propriedade intelectual.</span>
            Todos os direitos de propriedade intelectual sobre a plataforma Oravel — incluindo
            código-fonte, algoritmos, interfaces, identidade visual, marca "Oravel" e qualquer
            tecnologia subjacente — pertencem exclusivamente à Oravel ou a seus licenciantes,
            protegidos pela legislação de direitos autorais e de propriedade industrial aplicável.
            Nenhuma disposição deste contrato transfere ao Contratante qualquer direito sobre
            esses ativos, além do direito de uso aqui expressamente concedido.
        </div>

        <div class="clause">
            <span class="clause-title">5. Restrições de uso.</span>
            É vedado ao Contratante, direta ou indiretamente: (a) realizar engenharia reversa,
            descompilar ou desmontar a plataforma; (b) copiar, modificar ou criar obras derivadas
            do software; (c) sublicenciar, revender, alugar ou de qualquer forma disponibilizar a
            plataforma a terceiros estranhos ao Contrato; (d) utilizar a plataforma para fins
            ilícitos ou em violação a direitos de terceiros; (e) remover ou adulterar avisos de
            propriedade intelectual da Oravel.
        </div>

        <div class="clause">
            <span class="clause-title">6. Dados do Contratante não são afetados pela extinção da licença.</span>
            A extinção desta licença de uso afeta exclusivamente o direito de acesso e uso da
            plataforma, não alterando a propriedade do Contratante sobre seus próprios dados
            (Cláusula 7 do Contrato de Assinatura) nem seu direito de solicitar a exportação
            desses dados dentro do prazo de retenção praticado pela Oravel.
        </div>

        <div class="clause">
            <span class="clause-title">7. Atualizações da plataforma.</span>
            Por se tratar de software como serviço (SaaS), a Oravel pode incluir, alterar ou
            descontinuar funcionalidades ao longo da vigência do contrato, como parte da evolução
            contínua da plataforma, sem que isso constitua alteração unilateral prejudicial ao
            Contratante, desde que preservada a essência dos módulos efetivamente contratados.
        </div>

        <div class="clause">
            <span class="clause-title">8. Limitação de responsabilidade.</span>
            Observados os parâmetros de disponibilidade e continuidade descritos no Anexo SLA, a
            responsabilidade da Oravel por eventuais danos decorrentes do uso ou da
            indisponibilidade da plataforma fica limitada aos valores efetivamente pagos pelo
            Contratante nos 12 (doze) meses anteriores ao evento, não respondendo a Oravel por
            lucros cessantes, perda de negócios ou danos indiretos, salvo dolo ou culpa grave
            comprovados.
        </div>

        <div class="clause">
            <span class="clause-title">9. Garantia.</span>
            A plataforma é fornecida "como está" ("as is"), nos padrões de disponibilidade e
            desempenho descritos no Anexo SLA, sem outras garantias implícitas de adequação a um
            propósito específico não previamente acordado por escrito entre as partes.
        </div>

        <div class="clause">
            <span class="clause-title">10. Relação com os demais anexos.</span>
            Esta Licença de Uso integra, junto com os Anexos SLA e LGPD, o Contrato de Assinatura
            firmado pelo Contratante, prevalecendo suas disposições específicas sobre uso e
            propriedade intelectual em caso de conflito aparente com o texto principal do
            Contrato de Assinatura.
        </div>

        <div class="clause">
            <span class="clause-title">11. Foro.</span>
            Fica eleito o foro da comarca de domicílio da Oravel para dirimir quaisquer
            controvérsias oriundas desta Licença de Uso, com renúncia a qualquer outro, por mais
            privilegiado que seja.
        </div>
    </div>
</div>
@endsection
