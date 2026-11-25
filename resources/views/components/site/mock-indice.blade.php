@props(['ex', 'rotulo'])
@php
    use App\Support\IntelligenceSite\Exemplo as E;
    $max = max($ex->indice('ago'), $ex->indice('set'), $ex->indice('out'));
@endphp
<x-site.mock :rotulo="$rotulo" :aria="'Tela de exemplo do Índice de Saúde: nota '.$ex->indice('out').', subiu '.E::delta($ex->indiceVariacao()).' pontos em dois meses'">
    <h4>Índice de Saúde</h4>
    <div class="nota"><b>{{ $ex->indice('out') }}</b><span>{{ E::delta($ex->indiceVariacao()) }} em dois meses</span></div>
    <div class="barras">
        @foreach (['ago' => 'Ago', 'set' => 'Set', 'out' => 'Out'] as $m => $nome)
            <div style="height:{{ round($ex->indice($m) / $max * 100) }}%"><em>{{ $ex->indice($m) }}</em></div>
        @endforeach
    </div>
    <div class="meses"><span>Ago</span><span>Set</span><span>Out</span></div>
    <div class="kpis">
        <div class="kpi"><small>Disponibilidade</small><b>{{ E::pct($ex->disponibilidade('out')) }}</b><i>{{ E::delta($ex->disponibilidadePontos()) }} pontos</i></div>
        <div class="kpi"><small>Atraso</small><b>{{ E::pct($ex->atraso('out'), 1) }}</b><i>{{ E::delta($ex->atrasoPontos(), 1) }} pontos</i></div>
        <div class="kpi"><small>Oficina</small><b>{{ $ex->oficina('out') }}</b><i>{{ E::delta($ex->oficinaVariacao()) }}</i></div>
    </div>
</x-site.mock>
