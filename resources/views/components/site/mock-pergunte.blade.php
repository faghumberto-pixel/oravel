@props(['ex', 'rotulo'])
@php use App\Support\IntelligenceSite\Exemplo as E; @endphp
<x-site.mock :rotulo="$rotulo" aria="Tela de exemplo do assistente: pergunta sobre disponibilidade e resposta com os números">
    <h4>Pergunte em português</h4>
    <div class="balao q">{{ $ex->pergunta() }}</div>
    <div class="balao a"><b>Sim.</b> Sua disponibilidade subiu de {{ E::pct($ex->disponibilidade('ago')) }} para {{ E::pct($ex->disponibilidade('out')) }} em dois meses ({{ E::delta($ex->disponibilidadePontos()) }} pontos). Hoje {{ $ex->parados() }} dos {{ $ex->frota() }} equipamentos estão parados.</div>
</x-site.mock>
