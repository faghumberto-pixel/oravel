{{-- Checklists do grupo na impressao do dossie da OS (modelos para preencher; o resultado do checklist antigo da OS continua na secao propria). --}}
@php
    $ckImpressao = app(\App\Services\Checklists\ChecklistsDaOs::class)->paraImpressao($order, 1000);
@endphp
@foreach ($ckImpressao as $ck)
    <div class="mb-8 break-inside-avoid-page" data-testid="dossie-checklist">
        <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-1">Checklist do equipamento: {{ $ck['template']->nome }}</h3>
        <p class="text-[11px] text-gray-500 mb-3">{{ $ck['itens'] }} itens, {{ $ck['criticos'] }} críticos @if ($ck['template']->referencia_manual) · Ref.: {{ $ck['template']->referencia_manual }}@endif · N/C = não conforme</p>
        <table class="w-full text-left text-xs border border-gray-300">
            <thead><tr class="bg-gray-50"><th class="p-2 w-8 border">Nº</th><th class="p-2 border">Item a conferir</th><th class="p-2 w-10 border text-center">OK</th><th class="p-2 w-10 border text-center">N/C</th><th class="p-2 w-10 border text-center">N/A</th><th class="p-2 w-40 border">Valor / observação</th></tr></thead>
            <tbody>
                @foreach ($ck['paginas'][0] ?? [] as $l)
                    @if ($l['tipo'] === 'secao')
                        <tr class="bg-gray-100"><td colspan="6" class="p-2 border font-bold uppercase tracking-wide text-[10px]">{{ $l['titulo'] }}</td></tr>
                    @else
                        @php($it = $l['item'])
                        <tr>
                            <td class="p-2 border text-center font-bold">{{ $l['n'] }}</td>
                            <td class="p-2 border">{{ $it->descricao }}@if ($it->critico) <span class="font-black text-red-700 text-[9px] border border-red-700 rounded px-1">CRÍTICO</span>@endif @if ($it->referencia_manual)<span class="block text-[10px] text-gray-500">Ref.: {{ $it->referencia_manual }}</span>@endif</td>
                            @if ($it->tipo_resposta === 'conforme_nao_conforme_na')
                                @foreach ([1, 2, 3] as $c)<td class="p-2 border text-center"><span class="inline-block w-4 h-4 border border-gray-800 rounded-sm align-middle"></span></td>@endforeach
                                <td class="p-2 border"></td>
                            @else
                                <td class="p-2 border text-center text-gray-400" colspan="3">{{ ['numerico' => 'medir', 'selecao' => 'escolher', 'texto' => 'escrever', 'foto' => 'foto'][$it->tipo_resposta] ?? '' }}</td>
                                <td class="p-2 border">@if ($it->tipo_resposta === 'numerico')________ {{ $it->unidade }}@elseif ($it->tipo_resposta === 'selecao'){{ implode(' / ', (array) $it->opcoes) }}@elseif ($it->tipo_resposta === 'foto')☐ foto anexada @endif</td>
                            @endif
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        <div class="grid grid-cols-2 gap-10 mt-8 text-[10px] text-gray-500">
            <div class="border-t border-gray-800 pt-1 text-center">Assinatura de quem fez o checklist</div>
            <div class="border-t border-gray-800 pt-1 text-center">Visto do responsável</div>
        </div>
    </div>
@endforeach
