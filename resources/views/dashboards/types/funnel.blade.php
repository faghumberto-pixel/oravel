@php
    $topo = max(1, $p['steps'][0][1] ?? 1);
    $cores = ['var(--odb-accent)', '#f59e0b', '#38bdf8', 'var(--odb-ok)', '#a78bfa', '#f472b6'];
@endphp
<div class="odb-fun">
    @foreach ($p['steps'] as $i => $etapa)
        @php $anterior = $i > 0 ? max(1, $p['steps'][$i - 1][1]) : null; @endphp
        <div style="width: {{ max(24, min(100, round($etapa[1] / $topo * 100))) }}%; background: {{ $cores[$i % count($cores)] }}">
            <span>{{ $etapa[0] }}</span>
            <b>{{ $etapa[1] }}@if ($anterior && $etapa[1] <= $anterior) <em>{{ round($etapa[1] / $anterior * 100) }}%</em>@endif</b>
        </div>
    @endforeach
</div>
