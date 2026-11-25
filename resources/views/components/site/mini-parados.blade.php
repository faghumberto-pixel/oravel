@props(['ex', 'rotulo'])
@php use App\Support\IntelligenceSite\Exemplo as E; @endphp
<x-site.mock :rotulo="$rotulo" :aria="'Exemplo: '.$ex->parados().' de '.$ex->frota().' equipamentos parados'">
    <h4>Disponibilidade da frota</h4>
    <div class="nota"><b>{{ E::pct($ex->disponibilidade('out')) }}</b><span>{{ E::delta($ex->disponibilidadePontos()) }} pontos desde agosto</span></div>
    <p>{{ $ex->parados() }} de {{ $ex->frota() }} equipamentos parados</p>
</x-site.mock>
