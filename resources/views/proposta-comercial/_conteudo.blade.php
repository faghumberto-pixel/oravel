@php
    $tenant = $proposta->tenant;
    $client = $proposta->client;
    $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $emissor = $tenant?->nome_fantasia ?: ($tenant?->name ?? 'Oravel');
    $enderecoEmissor = collect([
        trim(($tenant?->logradouro ?? '').' '.($tenant?->numero ?? '')) ?: $tenant?->address,
        $tenant?->bairro,
        collect([$tenant?->cidade, $tenant?->uf])->filter()->implode('/'),
        $tenant?->cep ? 'CEP '.$tenant->cep : null,
    ])->filter()->implode(' · ');
    $enderecoCliente = collect([
        $client?->address,
        $client?->neighborhood,
        collect([$client?->city, $client?->state ?: $client?->uf])->filter()->implode('/'),
        ($client?->zip_code ?: $client?->cep) ? 'CEP '.($client?->zip_code ?: $client?->cep) : null,
    ])->filter()->implode(' · ');
    $contatoCliente = $client?->contact_name ?: $client?->name;
    $primeiroNome = trim(explode(' ', (string) $contatoCliente)[0] ?? '');
    $codigo = strtoupper(substr((string) $proposta->id, 0, 8));
    $emitidaEm = ($proposta->sent_at ?? $proposta->created_at)?->format('d/m/Y');
    $iniciais = collect(preg_split('/\s+/', trim($emissor)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $campos = collect($proposta->campos ?? [])->filter(fn ($c) => filled($c['titulo'] ?? null) && filled($c['texto'] ?? null));
@endphp
<style>
    .pc { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }
    .pc table { border-collapse: collapse; }
    .pc-top { background: #f1f2f4; padding: 18px 22px; margin-bottom: 4px; }
    .pc-top table { width: 100%; }
    .pc-titulo { font-size: 26px; font-weight: bold; line-height: 1.05; color: #E8541A; text-transform: uppercase; }
    .pc-titulo span { color: #111827; display: block; }
    .pc-num { font-size: 10px; color: #4b5563; margin-top: 6px; }
    .pc-logo { width: 54px; height: 54px; border-radius: 27px; background: #ffffff; text-align: center; font-size: 20px; font-weight: bold; line-height: 54px; color: #111827; }
    .pc-pessoas { width: 100%; margin: 14px 0 6px; }
    .pc-pessoas td { width: 50%; vertical-align: top; padding: 10px 12px; border: 1px solid #d1d5db; }
    .pc-pessoas .tag { font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #E8541A; display: block; margin-bottom: 3px; }
    .pc-pessoas .nome { font-size: 12px; font-weight: bold; color: #111827; }
    .pc-pessoas .linha { font-size: 10px; color: #4b5563; }
    .pc h2 { font-size: 13px; color: #111827; margin: 16px 0 6px; }
    .pc p { margin: 0 0 8px; }
    table.pc-itens { width: 100%; border: 1px solid #d1d5db; }
    .pc-itens th { padding: 6px 8px; font-size: 10px; text-align: center; border: 1px solid #d1d5db; background: #ffffff; }
    .pc-itens td { padding: 6px 8px; font-size: 10px; border: 1px solid #d1d5db; vertical-align: top; }
    .pc-itens td.n { text-align: right; white-space: nowrap; }
    .pc-itens .sub { font-size: 9px; color: #6b7280; }
    table.pc-total { width: 100%; background: #111827; }
    .pc-total td { padding: 8px 10px; color: #ffffff; font-weight: bold; font-size: 11px; }
    .pc-total td.v { text-align: right; width: 30%; }
    table.pc-cards { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px; }
    .pc-cards td { width: 33%; vertical-align: top; border: 1px solid #9ca3af; border-radius: 8px; padding: 8px 10px; font-size: 10px; }
    .pc-cards .ct { text-align: center; font-weight: bold; font-size: 11px; color: #111827; margin-bottom: 5px; }
    .pc-campo { border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 12px; margin-bottom: 8px; }
    .pc-campo .ct { font-weight: bold; color: #111827; margin-bottom: 3px; }
    .pc-fecho { margin-top: 16px; }
</style>
<div class="pc">
    <div class="pc-top">
        <table>
            <tr>
                <td>
                    <div class="pc-titulo">Proposta<span>Comercial</span></div>
                    <div class="pc-num">Nº {{ $codigo }}@if($emitidaEm) — {{ $emitidaEm }}@endif</div>
                </td>
                <td style="width: 70px; text-align: right;">
                    <div class="pc-logo">{{ $iniciais }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="pc-pessoas">
        <tr>
            <td>
                <span class="tag">De</span>
                <div class="nome">{{ $emissor }}</div>
                @if($tenant?->razao_social && $tenant->razao_social !== $emissor)<div class="linha">{{ $tenant->razao_social }}</div>@endif
                @if($tenant?->cpf_cnpj)<div class="linha">CNPJ/CPF: {{ $tenant->cpf_cnpj }}</div>@endif
                @if($enderecoEmissor)<div class="linha">{{ $enderecoEmissor }}</div>@endif
                @if($tenant?->telefone)<div class="linha">Tel.: {{ $tenant->telefone }}</div>@endif
                @if($tenant?->email_contato)<div class="linha">{{ $tenant->email_contato }}</div>@endif
                @if(filled($proposta->cabecalho))<div class="linha" style="margin-top:4px;">{!! nl2br(e($proposta->cabecalho)) !!}</div>@endif
            </td>
            <td>
                <span class="tag">Para</span>
                <div class="nome">{{ $client?->name ?? '—' }}</div>
                @if($client?->contact_name && $client->contact_name !== $client->name)<div class="linha">A/C: {{ $client->contact_name }}</div>@endif
                @if($client?->cpf_cnpj)<div class="linha">CNPJ/CPF: {{ $client->cpf_cnpj }}</div>@endif
                @if($enderecoCliente)<div class="linha">{{ $enderecoCliente }}</div>@endif
                @if($client?->phone || $client?->whatsapp)<div class="linha">Tel.: {{ $client->phone ?: $client->whatsapp }}</div>@endif
                @if($client?->email)<div class="linha">{{ $client->email }}</div>@endif
            </td>
        </tr>
    </table>

    <p style="margin-top: 14px;">
        <strong>{{ $primeiroNome ?: 'Prezado(a)' }}</strong>, tudo bem?<br>
        Segue abaixo nossa proposta para trabalharmos juntos:
    </p>

    <h2>Itens propostos</h2>
    <table class="pc-itens">
        <thead>
            <tr>
                <th style="text-align:left;">Produto / serviço</th>
                <th style="width: 85px;">Valor unit.</th>
                <th style="width: 40px;">Qtde</th>
                <th style="width: 85px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($proposta->items as $item)
                <tr>
                    <td>
                        {{ $item->description }}
                        @php
                            $periodo = collect([
                                $item->unit_period ? 'por '.$item->unit_period : null,
                                $item->start_date ? 'de '.$item->start_date->format('d/m/Y') : null,
                                $item->end_date ? 'até '.$item->end_date->format('d/m/Y') : null,
                            ])->filter()->implode(' ');
                        @endphp
                        @if($periodo)<div class="sub">{{ ucfirst($periodo) }}</div>@endif
                        @if($item->item_terms)<div class="sub">Obs.: {{ $item->item_terms }}</div>@endif
                    </td>
                    <td class="n">{{ $money($item->unit_price) }}</td>
                    <td class="n" style="text-align:center;">{{ rtrim(rtrim(number_format($item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="n">{{ $money($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="pc-total">
        <tr><td>Total</td><td class="v">{{ $money($proposta->total_value) }}</td></tr>
    </table>

    <h2>Detalhamento da proposta</h2>
    <table class="pc-cards">
        <tr>
            <td>
                <div class="ct">Prazo de validade</div>
                @if($proposta->valid_until)
                    Esta proposta é válida até:<br><strong>{{ $proposta->valid_until->format('d/m/Y') }}</strong>
                @else
                    Consulte o vendedor sobre a validade desta proposta.
                @endif
            </td>
            <td>
                <div class="ct">Seu vendedor</div>
                <strong>{{ $proposta->sellerUser?->name ?? $emissor }}</strong>
                @if($proposta->sellerUser?->email)<br>{{ $proposta->sellerUser->email }}@endif
                @if($tenant?->telefone)<br>Tel.: {{ $tenant->telefone }}@endif
            </td>
            <td>
                <div class="ct">Emitida por</div>
                <strong>{{ $emissor }}</strong>
                @if($enderecoEmissor)<br>{{ $enderecoEmissor }}@endif
                @if($emitidaEm)<br>Emissão: {{ $emitidaEm }}@endif
            </td>
        </tr>
    </table>

    @if($proposta->terms || $campos->isNotEmpty())
        <div style="margin-top: 14px;">
            @foreach($campos as $campo)
                <div class="pc-campo">
                    <div class="ct">{{ $campo['titulo'] }}</div>
                    {!! nl2br(e($campo['texto'])) !!}
                </div>
            @endforeach
            @if($proposta->terms)
                <div class="pc-campo">
                    <div class="ct">Condições gerais</div>
                    {!! nl2br(e($proposta->terms)) !!}
                </div>
            @endif
        </div>
    @endif

    <div class="pc-fecho">
        <p><strong>{{ $primeiroNome ?: 'Prezado(a)' }}</strong>, esperamos poder atendê-lo(a) de forma única. Caso tenha qualquer dúvida, por favor não hesite em nos contatar.</p>
        <p>Obrigado,<br>{{ $proposta->sellerUser?->name ? $proposta->sellerUser->name.' – ' : '' }}{{ $emissor }}</p>
    </div>
</div>
