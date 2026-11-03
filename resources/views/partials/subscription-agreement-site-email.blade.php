{{--
    Anexo III do Contrato de Assinatura -- Site e e-mail institucional. Só aparece quando o Contrato (Plan) prevê o
    item (Central: "Site e e-mail institucional" = Incluso ou Vendido à parte). Compartilhado entre o PDF e a tela de
    assinatura, igual aos demais parciais. Espera $contract (o Tenant).

    Só traz o que foi decidido pelo dono do produto (2026-11-03): incluso e gratuito no contrato de 12 meses, renovado
    com o contrato enquanto pago; ou vendido à parte por valor ÚNICO; o item acompanha o contrato (suspende e encerra
    com ele); a oferta vale mesmo se o cliente já tiver site/e-mail. Domínio, páginas e caixas de e-mail NÃO são
    definidos aqui: vêm do "escopo combinado" do Contrato ou da proposta comercial aceita. Minuta de apoio: segue a
    mesma ressalva de revisão jurídica das cláusulas.
--}}
@php
    $planoSite = $contract->plan;
    $moneyFmtSite = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $exportDaysSite = (int) config('oravel.company.data_export_days', 30);
@endphp

@if ($planoSite && $planoSite->temSiteEmail())
    <div style="margin-top:14px; padding:10px 12px; border-left:4px solid #ea580c; background:#fff7ed;">
        <div class="clause-title" style="font-size:12px;">ANEXO III — SITE E E-MAIL INSTITUCIONAL</div>

        <div class="clause" style="margin-top:6px;">
            <span class="clause-title">1. Objeto.</span>
            A Oravel disponibiliza ao Contratante um site institucional e e-mail corporativo, nos termos deste Anexo,
            que integra o contrato.
            @if (filled($planoSite->site_email_escopo))
                <br><strong>Escopo combinado:</strong> {{ $planoSite->site_email_escopo }}
            @else
                O escopo (domínio, páginas do site e caixas de e-mail) é o descrito na proposta comercial aceita pelo Contratante.
            @endif
        </div>

        <div class="clause">
            <span class="clause-title">2. Condição comercial.</span>
            @if ($planoSite->siteEmailModo() === \App\Models\Plan::SITE_EMAIL_INCLUSO)
                O site e o e-mail institucional são disponibilizados <strong>sem custo adicional</strong>, como contrapartida da
                assinatura pelo prazo inicial de 12 (doze) meses previsto na cláusula 2. Renovam-se automaticamente, junto com
                o contrato, a cada período de 12 (doze) meses, desde que as mensalidades do período estejam pagas.
            @else
                O site e o e-mail institucional são vendidos separadamente, por valor <strong>único</strong> de
                <strong>{{ $moneyFmtSite($planoSite->siteEmailValor()) }}</strong>, não recorrente, cobrado pela Oravel via Asaas.
                Pago esse valor, permanecem disponíveis enquanto o contrato estiver vigente e em dia.
            @endif
            @if ($planoSite->site_email_cliente_possui)
                Ainda que o Contratante já possua site e e-mail próprios, a Oravel os disponibiliza, sem obrigação de uso.
            @endif
        </div>

        <div class="clause">
            <span class="clause-title">3. Vigência e suspensão.</span>
            O site e o e-mail institucional acompanham o contrato: são suspensos quando o acesso for suspenso por
            inadimplência (cláusula 6) e se encerram com o término do contrato, por qualquer causa. Encerrado o contrato,
            o Contratante pode solicitar a exportação do conteúdo do site em até {{ $exportDaysSite }} dias, na forma da
            cláusula 8.
        </div>

        <div class="clause">
            <span class="clause-title">4. Demais cláusulas.</span>
            Aplicam-se a este Anexo as demais cláusulas do contrato, inclusive as de propriedade intelectual, proteção de
            dados pessoais e limitação de responsabilidade.
        </div>
    </div>
@endif
