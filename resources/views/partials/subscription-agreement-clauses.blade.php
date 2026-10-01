{{--
    Cláusulas do Contrato de Assinatura -- conteúdo compartilhado entre o
    PDF final (resources/views/pdf/subscription-agreement.blade.php) e a
    tela de assinatura eletrônica (resources/views/signature/form.blade.php),
    pra nunca ficarem dessincronizados. Espera receber $contract (o Tenant).

    Revisão jurídica interna (2026-10-01, pedido do usuário: "como um
    especialista contratado para referendar assuntos jurídicos"): acrescentadas
    qualificação das partes, licença de uso e vedações, responsabilidades do
    Contratante, encargos de mora, LGPD (papéis controlador/operador),
    propriedade intelectual, limitação de responsabilidade, assinatura
    eletrônica e disposições gerais. Continua sendo uma minuta de apoio, NÃO
    substitui a revisão de advogado inscrito na OAB -- sinalizado nas duas
    telas que usam este parcial. Itens de decisão COMERCIAL embutidos (e que
    o dono do produto deve confirmar): multa de 2% + juros de 1% a.m., reajuste
    anual pelo IPCA, teto de responsabilidade = 12 meses de mensalidade, prazo
    de exportação de dados (config('oravel.company.data_export_days')).
    Cláusula 6 (Inadimplência) reflete o comportamento REAL do sistema (ver
    App\Models\Tenant::isAccessBlockedForNonPayment() e
    App\Http\Middleware\EnsureTenantPaymentIsCurrent) -- se o prazo de
    tolerância mudar em config('oravel.payment_grace_days'), o texto aqui
    acompanha automaticamente.
--}}
@php
    $graceDays = (int) config('oravel.payment_grace_days', 5);
    $exportDays = (int) config('oravel.company.data_export_days', 30);
    $company = config('oravel.company');
    $moneyFmt = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
@endphp

<div class="clause">
    <span class="clause-title">Partes.</span>
    <strong>CONTRATADA:</strong>
    @if (filled($company['legal_name']) && filled($company['cnpj']))
        {{ $company['legal_name'] }}, inscrita no CNPJ sob o nº {{ $company['cnpj'] }}@if (filled($company['address'])), com sede em {{ $company['address'] }}@endif,
    @else
        Oravel (razão social, CNPJ e endereço a constar na versão final deste contrato),
    @endif
    doravante denominada "Oravel".
    <strong>CONTRATANTE:</strong> a pessoa jurídica ou física identificada na seção
    "Contratante" acima, que declara ter poderes para firmar este contrato e para
    vincular a empresa, doravante denominada "Contratante".
</div>

<div class="clause">
    <span class="clause-title">1. Objeto e licença de uso.</span>
    A Oravel presta ao Contratante, mediante assinatura, acesso ao software de gestão Oravel
    (SaaS) com os módulos listados na seção "Plano Contratado" acima, hospedado em nuvem e
    acessível via internet. O acesso é concedido por meio de licença de uso limitada, não
    exclusiva, intransferível e revogável nos termos deste contrato, restrita às atividades
    do próprio Contratante. É vedado ao Contratante sublicenciar, revender, ceder ou
    disponibilizar o sistema a terceiros, realizar engenharia reversa, copiar ou tentar
    acessar o código-fonte, ou utilizar o sistema para fins ilícitos.
</div>

<div class="clause">
    <span class="clause-title">2. Vigência, preço e forma de pagamento.</span>
    O contrato vigora por prazo indeterminado e a assinatura é renovada automaticamente a
    cada ciclo de cobrança indicado acima, até que o Contratante solicite o cancelamento. A
    cobrança é emitida via Asaas, pelos meios de pagamento por ela disponibilizados (cartão de
    crédito, Pix ou boleto, conforme o caso).
    @if ($contract->implementationAmount() > 0)
        Além da mensalidade, será cobrada uma taxa única de implantação de
        {{ $moneyFmt($contract->implementationAmount()) }}, não recorrente,
        @if ($contract->implementationInstallments() > 1)
            dividida em {{ $contract->implementationInstallments() }} parcelas
            ({{ collect($contract->implementationInstallmentAmounts())->map($moneyFmt)->implode(' e ') }}),
            a primeira com vencimento em 7 dias e a segunda 30 dias após a primeira,
        @endif
        emitida via Asaas (boleto, cartão ou Pix, à escolha do Contratante), referente à
        configuração inicial, parametrização e treinamento. A taxa de implantação não é
        reembolsável após o início dos serviços de implantação, e o atraso em qualquer parcela
        sujeita-se aos encargos da cláusula 6.
    @endif
</div>

<div class="clause">
    <span class="clause-title">3. Reajuste e mudança de escopo.</span>
    O valor da mensalidade será reajustado a cada 12 (doze) meses, contados da assinatura,
    pela variação do IPCA/IBGE no período (ou, na sua falta, por índice oficial que o
    substitua), sem prejuízo de reajustes adicionais acordados entre as partes. A inclusão,
    exclusão ou alteração de módulos e de valores fora do reajuste anual depende de acordo
    prévio, formalizado por aditivo a este contrato ou por aceite eletrônico do Contratante.
</div>

<div class="clause">
    <span class="clause-title">4. Nível de serviço, proteção de dados e licença de uso (SLA/LGPD/Licença).</span>
    A prestação do serviço observa os termos de disponibilidade, backup, conformidade com a Lei
    Geral de Proteção de Dados (LGPD) e licenciamento de uso do software descritos,
    respectivamente, em {{ url('/legal/sla') }}, {{ url('/legal/lgpd') }} e
    {{ url('/legal/licenca-de-uso') }}, partes integrantes deste contrato independentemente de
    transcrição. Em caso de conflito, prevalecem as cláusulas deste contrato.
</div>

<div class="clause">
    <span class="clause-title">5. Responsabilidades do Contratante.</span>
    O Contratante é responsável: (a) pela veracidade, licitude e atualização dos dados e
    documentos que inserir no sistema; (b) pela guarda do sigilo das credenciais de acesso e
    por todos os atos praticados com elas por seus usuários, devendo comunicar de imediato
    qualquer uso indevido; (c) pelo cumprimento da legislação aplicável às suas próprias
    atividades, incluindo normas regulamentadoras (NRs), laudos, ARTs, inspeções e demais
    obrigações técnicas e trabalhistas. O sistema é ferramenta de apoio à gestão e <strong>não
    substitui</strong> o julgamento nem a responsabilidade de profissionais legalmente
    habilitados do Contratante, nem garante, por si só, a conformidade legal da operação.
</div>

<div class="clause">
    <span class="clause-title">6. Inadimplência e bloqueio de acesso.</span>
    Em caso de atraso no pagamento de qualquer fatura, incidirão sobre o valor em atraso
    multa moratória de 2% (dois por cento), juros de 1% (um por cento) ao mês, pro rata die, e
    correção monetária pelo IPCA. Além disso, o acesso do Contratante ao sistema é
    suspenso automaticamente após {{ $graceDays }} {{ $graceDays === 1 ? 'dia corrido' : 'dias corridos' }} de
    inadimplência, sem necessidade de aviso adicional além das notificações de cobrança já
    emitidas pela Asaas. Em caso de cancelamento da cobrança (por exclusão da assinatura,
    estorno ou expiração do link de pagamento), o acesso é suspenso imediatamente, sem período
    de tolerância. O acesso é restabelecido automaticamente assim que o pagamento pendente for
    identificado e confirmado pelo sistema, sem necessidade de solicitação manual. A suspensão
    não exime o Contratante das parcelas vencidas e vincendas, e os dados são preservados
    durante a suspensão. Persistindo a inadimplência por mais de 90 (noventa) dias, a Oravel
    poderá rescindir o contrato, sem prejuízo da cobrança dos valores devidos.
</div>

<div class="clause">
    <span class="clause-title">7. Cancelamento.</span>
    O Contratante pode solicitar o cancelamento a qualquer momento através do suporte Oravel,
    encerrando a cobrança a partir do próximo ciclo; valores de ciclos já iniciados ou pagos
    não são reembolsáveis, salvo disposição legal em contrário. A Oravel poderá rescindir o
    contrato mediante aviso prévio de 30 (trinta) dias e, de imediato, em caso de violação
    das cláusulas 1 ou 5, uso ilícito do sistema ou fraude.
</div>

<div class="clause">
    <span class="clause-title">8. Propriedade e portabilidade dos dados.</span>
    Todos os dados inseridos pelo Contratante no sistema (cadastros, ordens de serviço,
    documentos, relatórios) permanecem de sua propriedade. Em caso de término do contrato, o
    Contratante pode solicitar a exportação de seus dados em até {{ $exportDays }} dias após o
    encerramento, mediante pedido ao suporte; decorrido esse prazo, a Oravel poderá eliminar
    os dados, ressalvadas as hipóteses de guarda obrigatória por lei.
</div>

<div class="clause">
    <span class="clause-title">9. Proteção de dados pessoais (LGPD).</span>
    Quanto aos dados pessoais de terceiros inseridos pelo Contratante no sistema (colaboradores,
    clientes, contatos), o Contratante atua como controlador e a Oravel como operadora, nos
    termos da Lei nº 13.709/2018, tratando-os apenas para prestar o serviço contratado e
    conforme as instruções do Contratante, que garante possuir base legal para esse
    tratamento. A Oravel adota medidas de segurança técnicas e administrativas adequadas,
    pode valer-se de suboperadores necessários à prestação do serviço (como hospedagem em
    nuvem e processamento de pagamentos), comunicará ao Contratante, em prazo razoável,
    incidentes de segurança que afetem dados pessoais sob sua operação e, ao término do
    contrato, eliminará ou devolverá os dados nos termos da cláusula 8.
</div>

<div class="clause">
    <span class="clause-title">10. Propriedade intelectual.</span>
    O software, sua documentação, marcas, interfaces e melhorias são e permanecem de
    propriedade exclusiva da Oravel (ou de seus licenciantes). Este contrato não transfere ao
    Contratante qualquer direito de propriedade intelectual, apenas a licença de uso da
    cláusula 1. Sugestões do Contratante sobre o sistema poderão ser incorporadas pela Oravel
    sem ônus.
</div>

<div class="clause">
    <span class="clause-title">11. Limitação de responsabilidade.</span>
    Salvo em caso de dolo ou culpa grave, a responsabilidade total da Oravel perante o
    Contratante, por qualquer causa, fica limitada ao valor efetivamente pago pelo Contratante
    à Oravel nos 12 (doze) meses anteriores ao evento que originou o dano, e a Oravel não
    responde por lucros cessantes, perda de receita, de oportunidade ou de contratos, nem por
    danos indiretos. A Oravel também não responde por indisponibilidade decorrente de falha de
    internet ou de equipamento do Contratante, de ato de terceiros, caso fortuito ou força
    maior, nem por decisões operacionais, de manutenção, de segurança ou de conformidade
    tomadas pelo Contratante com base nas informações do sistema.
</div>

<div class="clause">
    <span class="clause-title">12. Confidencialidade.</span>
    As partes se comprometem a manter sigilo sobre informações comerciais, técnicas e
    operacionais trocadas em razão deste contrato, não as divulgando a terceiros sem
    autorização prévia por escrito, durante a vigência e por 5 (cinco) anos após o seu término,
    ressalvadas as informações de domínio público e as que devam ser reveladas por ordem de
    autoridade competente.
</div>

<div class="clause">
    <span class="clause-title">13. Assinatura eletrônica.</span>
    As partes reconhecem como válida, eficaz e suficiente para comprovar a autoria e a
    integridade deste contrato a assinatura eletrônica realizada no sistema, com registro de
    data, hora, endereço IP e identificação do signatário, nos termos do art. 10, § 2º, da
    Medida Provisória nº 2.200-2/2001 e da Lei nº 14.063/2020, dispensando a assinatura
    física. O signatário declara ter poderes para representar o Contratante.
</div>

<div class="clause">
    <span class="clause-title">14. Disposições gerais.</span>
    Este contrato não gera vínculo societário, de representação ou trabalhista entre as
    partes. O Contratante não pode ceder este contrato sem anuência prévia e por escrito da
    Oravel, que poderá cedê-lo a empresa do seu grupo ou sucessora. A tolerância de uma parte
    quanto ao descumprimento de qualquer cláusula não implica renúncia ou novação. Se alguma
    cláusula for considerada inválida, as demais permanecem em vigor. As comunicações entre as
    partes serão feitas pelos meios eletrônicos cadastrados no sistema (e-mail do administrador
    do Contratante e canais de suporte da Oravel).
</div>

<div class="clause">
    <span class="clause-title">15. Foro e legislação aplicável.</span>
    Este contrato é regido pelas leis da República Federativa do Brasil. Fica eleito o foro da
    comarca de {{ $company['forum'] ?? 'domicílio da Oravel' }}, domicílio da Oravel, para dirimir quaisquer controvérsias oriundas deste
    contrato, com renúncia a qualquer outro, por mais privilegiado que seja.
</div>
