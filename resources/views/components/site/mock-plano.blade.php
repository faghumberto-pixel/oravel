@props(['ex', 'rotulo'])
@php use App\Support\IntelligenceSite\Exemplo as E; @endphp
<x-site.mock :rotulo="$rotulo" aria="Tela de exemplo do Plano de 30, 60 e 90 dias">
    <h4>Plano de 30, 60 e 90 dias</h4>
    <div class="foco"><b>Foco do ciclo: Saúde da Oficina</b> (nota {{ $ex->oficina('out') }})<br>Atraso nas faturas: {{ E::pct($ex->atraso('out'), 1) }} hoje, meta {{ E::pct($ex->atrasoMeta(), 1) }}</div>
    <div class="plano">
        <div class="col"><b>30 dias</b><span>Cobrar logo após o vencimento</span><span>Listar equipamentos parados</span></div>
        <div class="col"><b>60 dias</b><span>Decidir sobre {{ $ex->equipamentos()[0]['patrimonio'] }}</span><span>Subir preventivas</span></div>
        <div class="col"><b>90 dias</b><span>Medir contra a meta</span><span>Reenviar a planilha</span></div>
    </div>
</x-site.mock>
