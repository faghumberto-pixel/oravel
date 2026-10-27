@if (count($p['items']) === 0)
    <p class="odb-empty">{{ $p['empty'] }}</p>
@else
    <div class="odb-alert">
        @foreach ($p['items'] as $item)
            <div><span class="odb-pill {{ $item[1] }}">{{ $item[0] }}</span><span>{{ $item[2] }}</span></div>
        @endforeach
    </div>
@endif
