<div class="odb-kpi">{{ $p['value'] }}@if ($p['unit']) <em>{{ $p['unit'] }}</em>@endif</div>
@if ($p['delta'])
    <div class="odb-dl {{ $p['tone'] }}">{{ $p['delta'] }}</div>
@endif
@if ($p['bar'])
    <div class="odb-tr"><i class="{{ $p['bar']['tone'] }}" style="width: {{ min(100, max(0, $p['bar']['pct'])) }}%"></i></div>
@endif
