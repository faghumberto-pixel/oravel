<div>
    <div class="ac-sec">Navegação</div>
    <nav class="ac-nav">
        <a href="{{ url('/academia') }}" class="{{ $active === 'home' ? 'ac-on' : '' }}">🏠 Início</a>
        <a href="{{ url('/academia/ranking') }}" class="{{ $active === 'ranking' ? 'ac-on' : '' }}">🏆 Ranking</a>
        @if ($isAdmin)
            <a href="{{ url('/academia/equipe') }}" class="{{ $active === 'team' ? 'ac-on' : '' }}">📊 Participação da equipe</a>
        @endif
    </nav>

    @php $any = false; @endphp
    @foreach ($groups as $label => $items)
        @if ($items->isNotEmpty())
            @php $any = true; @endphp
            <div class="ac-sec">{{ $label }} ({{ $items->count() }})</div>
            @foreach ($items as $c)
                @php $color = $c['percent'] >= 70 ? '#16a34a' : ($c['percent'] >= 40 ? '#d97706' : '#dc2626'); @endphp
                <a class="ac-course {{ $active === $c['slug'] ? 'ac-on' : '' }}" href="{{ url('/academia/curso/'.$c['slug']) }}" wire:key="side-{{ $c['id'] }}">
                    <span class="ac-course-title">
                        <b>{{ $c['status'] === 'done' ? '✓ ' : '' }}{{ $c['title'] }}</b>
                        <small>{{ $c['percent'] }}%</small>
                    </span>
                    <span class="ac-mini"><div style="width:{{ $c['percent'] }}%;background:{{ $c['status'] === 'todo' ? '#cbd5e1' : $color }}"></div></span>
                    <small>{{ $c['lessons_done'] }} de {{ $c['lessons_total'] }} aulas{{ is_null($c['nota']) ? '' : ' · nota '.number_format($c['nota'], 1, ',', '') }}</small>
                </a>
            @endforeach
        @endif
    @endforeach
    @unless ($any)
        <div class="ac-sec">Meus cursos</div>
        <p class="ac-empty">Nenhum curso disponível no seu contrato ainda.</p>
    @endunless

    <div style="height:56px"></div>
    <div class="ac-side-foot"><span>⭐ Meus pontos</span><strong>{{ number_format($points, 0, ',', '.') }}</strong></div>
</div>
