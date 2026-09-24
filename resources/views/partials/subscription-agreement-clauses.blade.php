{{--
    Cláusulas do Contrato de Assinatura -- conteúdo compartilhado entre o
    PDF final (resources/views/pdf/subscription-agreement.blade.php) e a
    tela de assinatura eletrônica (resources/views/signature/form.blade.php),
    pra nunca ficarem dessincronizados. Espera receber $contract (o Tenant).

    Ainda é um rascunho razoável, NÃO uma minuta revisada por advogado --
    sinalizado nas duas telas que usam este parcial. Cláusula 6 (Inadimplência
    e Bloqueio de Acesso) reflete o comportamento REAL do sistema (ver
    App\Models\Tenant::isAccessBlockedForNonPayment() e
    App\Http\Middleware\EnsureTenantPaymentIsCurrent) -- se o prazo de
    tolerância mudar em config('oravel.payment_grace_days'), o texto aqui
    já acompanha automaticamente. Pedido do usuário 2026-09-23: "o contrato
    precisa prever o que a maioria dos SaaS prevê baseado no que ele
    escolheu na central e das disposições legais, pagamentos em atraso,
    bloqueio do sistema caso inadimplente, etc".
--}}
@php
    $graceDays = (int) config('oravel.payment_grace_days', 5);
@endphp

<div class="clause">
    <span class="clause-title">1. Objeto.</span>
    A Oravel presta ao Contratante, mediante assinatura, acesso ao software de gestão Oravel
    (SaaS) com os módulos listados na seção "Plano Contratado" acima, hospedado em nuvem e
    acessível via internet.
</div>

<div class="clause">
    <span class="clause-title">2. Vigência e cobrança.</span>
    A assinatura é renovada automaticamente a cada ciclo de cobrança indicado acima, cobrada
    via Asaas no cartão de crédito, até que o Contratante solicite o cancelamento.
</div>

<div class="clause">
    <span class="clause-title">3. Reajuste e mudança de escopo.</span>
    O valor e os módulos contratados podem ser reajustados mediante acordo prévio entre as
    partes, formalizado por aditivo a este contrato.
</div>

<div class="clause">
    <span class="clause-title">4. Nível de serviço, proteção de dados e licença de uso (SLA/LGPD/Licença).</span>
    A prestação do serviço observa os termos de disponibilidade, backup, conformidade com a Lei
    Geral de Proteção de Dados (LGPD) e licenciamento de uso do software descritos,
    respectivamente, em {{ url('/legal/sla') }}, {{ url('/legal/lgpd') }} e
    {{ url('/legal/licenca-de-uso') }}, partes integrantes deste contrato independentemente de
    transcrição.
</div>

<div class="clause">
    <span class="clause-title">5. Cancelamento.</span>
    O Contratante pode solicitar o cancelamento a qualquer momento através do suporte Oravel,
    encerrando a cobrança a partir do próximo ciclo.
</div>

<div class="clause">
    <span class="clause-title">6. Inadimplência e bloqueio de acesso.</span>
    Em caso de atraso no pagamento de qualquer fatura, o acesso do Contratante ao sistema é
    suspenso automaticamente após {{ $graceDays }} {{ $graceDays === 1 ? 'dia corrido' : 'dias corridos' }} de
    inadimplência, sem necessidade de aviso adicional além das notificações de cobrança já
    emitidas pela Asaas. Em caso de cancelamento da cobrança (por exclusão da assinatura,
    estorno ou expiração do link de pagamento), o acesso é suspenso imediatamente, sem período
    de tolerância. O acesso é restabelecido automaticamente assim que o pagamento pendente for
    identificado e confirmado pelo sistema, sem necessidade de solicitação manual.
</div>

<div class="clause">
    <span class="clause-title">7. Propriedade e portabilidade dos dados.</span>
    Todos os dados inseridos pelo Contratante no sistema (cadastros, ordens de serviço,
    documentos, relatórios) permanecem de sua propriedade. Em caso de cancelamento, o
    Contratante pode solicitar a exportação de seus dados dentro do prazo de retenção
    praticado pela Oravel, informado pelo suporte no momento da solicitação.
</div>

<div class="clause">
    <span class="clause-title">8. Confidencialidade.</span>
    As partes se comprometem a manter sigilo sobre informações comerciais, técnicas e
    operacionais trocadas em razão deste contrato, não as divulgando a terceiros sem
    autorização prévia por escrito.
</div>

<div class="clause">
    <span class="clause-title">9. Foro.</span>
    Fica eleito o foro da comarca de domicílio da Oravel para dirimir quaisquer controvérsias
    oriundas deste contrato, com renúncia a qualquer outro, por mais privilegiado que seja.
</div>
