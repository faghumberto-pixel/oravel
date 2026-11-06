@if (count($p['rows']) === 0)
    <p class="odb-empty">Sem dados no período.</p>
@else
    <div class="odb-tw">
        <div class="odb-heat" style="--cols: {{ count($p['columns']) }}">
            <div class="odb-heat-h"><span></span>
                @foreach ($p['columns'] as $c)<span class="m">{{ $c }}</span>@endforeach
                <span class="t">Total</span>
            </div>
            @foreach ($p['rows'] as $l)
                <div class="odb-heat-r">
                    <span class="l"><strong>{{ $l[0] }}</strong>@if ($l[1])<small>{{ $l[1] }}</small>@endif</span>
                    @foreach ($l[2] as $i => $v)
                        @php $f = $v > 0 ? max(14, min(100, round($v / $p['max'] * 100))) : 0; @endphp
                        <span class="c" title="{{ $p['columns'][$i] ?? '' }}: {{ $p['unit'] === '' ? (int) $v : $p['unit'].' '.number_format((float) $v, 2, ',', '.') }}"
                            @if ($f) style="background: color-mix(in srgb, var(--odb-crit) {{ $f }}%, var(--odb-track))" @endif></span>
                    @endforeach
                    <span class="t">{{ $l[3] }}@if (! empty($l[4]))<br><span class="odb-pill {{ $l[4]['pill'] }}">{{ $l[4]['text'] }}</span>@endif</span>
                </div>
            @endforeach
        </div>
    </div>
    <div class="odb-legend"><span>Menos</span><span class="odb-scale"></span><span>{{ $p['legend'] ?? 'Mais custo no mês' }}</span></div>
@endif
