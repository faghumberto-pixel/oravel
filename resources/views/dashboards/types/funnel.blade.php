@php($topo = max(1, $p['steps'][0][1] ?? 1))
<div class="odb-fun">
    @foreach ($p['steps'] as $etapa)
        <div style="width: {{ max(20, round($etapa[1] / $topo * 100)) }}%"><span>{{ $etapa[0] }}</span><b>{{ $etapa[1] }}</b></div>
    @endforeach
</div>
