@props(['pos', 'texto', 'app'])
{{-- ÚNICO CTA da página: todos apontam para o mesmo destino. Com JS abre o formulário (modal); sem JS leva à mesma tela no app. --}}
<a class="btn" href="{{ $app }}/intelligence/teste-gratis" data-cta data-pos="{{ $pos }}" data-track="Teste grátis: CTA {{ $pos }}">{{ $texto }}</a>
