@props(['ex', 'rotulo'])
@php use App\Support\IntelligenceSite\Exemplo as E; @endphp
<x-site.mock :rotulo="$rotulo" :aria="'Exemplo: nota da oficina de '.$ex->oficina('ago').' para '.$ex->oficina('out')">
    <h4>Saúde da oficina</h4>
    <div class="nota"><b>{{ $ex->oficina('out') }}</b><span>{{ E::delta($ex->oficinaVariacao()) }} desde agosto</span></div>
    <p>Era {{ $ex->oficina('ago') }}. Ainda é a área que mais pede atenção.</p>
</x-site.mock>
