@props(['rotulo', 'aria'])
{{-- Moldura das telas de exemplo. O rótulo "ilustrativo" faz parte do componente: nenhuma tela de exemplo aparece sem ele. --}}
<figure class="mock">
    <div class="tela" role="img" aria-label="{{ $aria }}"><div aria-hidden="true">{{ $slot }}</div></div>
    <figcaption class="ilustrativo">{{ $rotulo }}</figcaption>
</figure>
