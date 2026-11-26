@props(['ex', 'rotulo'])
@php use App\Support\IntelligenceSite\Exemplo as E; @endphp
<x-site.mock :rotulo="$rotulo" :aria="'Exemplo: faturamento em atraso de '.E::pct($ex->atraso('ago'), 1).' para '.E::pct($ex->atraso('out'), 1)">
    <h4>Faturamento em atraso</h4>
    <div class="nota"><b>{{ E::pct($ex->atraso('out'), 1) }}</b><span>{{ E::delta($ex->atrasoPontos(), 1) }} pontos desde agosto</span></div>
    <p>Meta do próximo ciclo: {{ E::pct($ex->atrasoMeta(), 1) }}</p>
</x-site.mock>
