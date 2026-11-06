@php $total = array_sum(array_map(fn ($f) => max(0, (float) $f[1]), $p['slices'])); @endphp
@if ($total <= 0)
    <p class="odb-empty">Sem dados no período.</p>
@else
    <div class="odb-donut">
        {!! \App\Dashboards\Svg::donut($p['slices'], $p['center'], $p['centerSub']) !!}
        <ul class="odb-dlg">
            @foreach ($p['slices'] as $f)
                <li><b style="background: {{ $f[2] }}"></b><span>{{ $f[0] }}</span><span class="odb-num">{{ round(max(0, (float) $f[1]) / $total * 100) }}%</span></li>
            @endforeach
        </ul>
    </div>
@endif
