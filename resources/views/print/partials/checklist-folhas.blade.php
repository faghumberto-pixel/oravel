{{-- Folhas A4 dos checklists do grupo, para o mecanico preencher a mao. Uma ou mais folhas por modelo (20 linhas por folha). --}}
@foreach ($checklistsImpressao as $ck)
    @php
        $totalFolhas = count($ck['paginas']);
    @endphp
    @foreach ($ck['paginas'] as $i => $linhas)
        <section class="folha" data-testid="folha-checklist">
            <i class="canto tl"></i><i class="canto tr"></i><i class="canto bl"></i><i class="canto br"></i>
            <header class="cab ck">
                <div class="marca"><span class="pts"><b></b><b></b><b></b></span>Oravel</div>
                <div class="emp"><small>Empresa</small><strong>{{ \App\Support\Tenancy::current()?->name ?? '' }}</strong></div>
                <div class="nro"><small>Ordem de serviço nº</small><strong>{{ $os->os_number }}</strong></div>
            </header>
            <div class="cknome">{{ $ck['template']->nome }}</div>
            <div class="ckmeta">{{ $equipamento }} · {{ $ck['itens'] }} itens, {{ $ck['criticos'] }} críticos · folha {{ $i + 1 }} de {{ $totalFolhas }}@if ($ck['template']->referencia_manual) · Ref.: {{ $ck['template']->referencia_manual }}@endif</div>
            <table class="ckt">
                <thead><tr><th class="n">Nº</th><th>Item a conferir</th><th class="m">OK</th><th class="m">N/C</th><th class="m">N/A</th><th class="v">Valor / observação</th></tr></thead>
                <tbody>
                    @foreach ($linhas as $l)
                        @if ($l['tipo'] === 'secao')
                            <tr class="sec"><td colspan="6">{{ $l['titulo'] }}</td></tr>
                        @else
                            @php($it = $l['item'])
                            <tr>
                                <td class="n">{{ $l['n'] }}</td>
                                <td>{{ $it->descricao }}@if ($it->critico)<span class="cr">CRÍTICO</span>@endif @if ($it->referencia_manual)<span class="ref">Ref.: {{ $it->referencia_manual }}</span>@endif</td>
                                @if ($it->tipo_resposta === 'conforme_nao_conforme_na')
                                    <td class="m"><span class="caixa"></span></td><td class="m"><span class="caixa"></span></td><td class="m"><span class="caixa"></span></td>
                                    <td class="v"></td>
                                @else
                                    <td class="m dif" colspan="3">{{ ['numerico' => 'medir', 'selecao' => 'escolher', 'texto' => 'escrever', 'foto' => 'foto'][$it->tipo_resposta] ?? '' }}</td>
                                    <td class="v">
                                        @if ($it->tipo_resposta === 'numerico')<span class="linha">&nbsp;</span> {{ $it->unidade }}
                                        @elseif ($it->tipo_resposta === 'selecao')@foreach ((array) $it->opcoes as $op)<span class="op"><span class="caixa"></span> {{ $op }}</span>@endforeach
                                        @elseif ($it->tipo_resposta === 'foto')<span class="caixa"></span> foto anexada
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
            @if ($loop->last)
                <div class="l2">
                    <div class="campo"><label>Horímetro ou km</label><span class="cx"></span></div>
                    <div class="campo"><label>Data e hora do checklist</label><span class="cx"></span></div>
                </div>
                <div class="campo"><label>Observações do checklist</label><span class="cx alto"></span></div>
                <div class="assin">
                    <div><span class="traco"></span><small>Assinatura de quem fez o checklist</small></div>
                    <div><span class="traco"></span><small>Visto do responsável</small></div>
                </div>
            @endif
            <footer class="pe">N/C = não conforme. Item CRÍTICO não conforme: não liberar o equipamento. Depois, registre as respostas no sistema (Executar checklist, na O.S.).</footer>
        </section>
    @endforeach
@endforeach
