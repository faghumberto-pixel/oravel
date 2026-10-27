@if (count($p['rows']) === 0)
    <p class="odb-empty">Sem dados no período.</p>
@else
    <div class="odb-hb">
        @foreach ($p['rows'] as $r)
            <div class="r">
                <span>{{ $r[0] }}</span>
                <div class="odb-tr"><i class="{{ $r[3] ?? '' }}" style="width: {{ min(100, $r[1] / $p['max'] * 100) }}%"></i></div>
                <span class="odb-num">{{ $r[2] ?? $r[1] }}</span>
            </div>
        @endforeach
    </div>
@endif
