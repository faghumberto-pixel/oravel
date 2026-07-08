{{-- BODY_END roda em toda pagina do painel, inclusive a de login (sem
     usuario autenticado) -- mesmo cuidado do announcement-banner: nao monta
     o componente Livewire pra visitante nao logado. --}}
@auth
    @livewire(\App\Livewire\ChatWidget::class)
@endauth
