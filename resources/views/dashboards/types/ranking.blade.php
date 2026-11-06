@if (count($p['rows']) === 0)
    <p class="odb-empty">Sem dados no período.</p>
@else
    <div class="odb-rk">
        @foreach ($p['rows'] as $r)
            <div class="odb-rk-r">
                <div class="odb-rk-t"><span><strong>{{ $r[0] }}</strong>@if ($r[1])<small>{{ $r[1] }}</small>@endif</span><span class="odb-num {{ $r[4] }}">{{ $r[3] }}</span></div>
                <div class="odb-tr odb-tr-m">
                    <i class="{{ $r[4] }}" style="width: {{ min(100, max(2, $r[2])) }}%"></i>
                    @foreach ($p['marks'] as $m)<s style="left: {{ $m }}%"></s>@endforeach
                </div>
            </div>
        @endforeach
        @if ($p['note'])<p class="odb-empty">{{ $p['note'] }}</p>@endif
    </div>
@endif
