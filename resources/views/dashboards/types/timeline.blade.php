@if (count($p['events']) === 0)
    <p class="odb-empty">{{ $p['empty'] }}</p>
@else
    <ol class="odb-tl">
        @foreach ($p['events'] as $e)
            <li class="{{ $e[3] }}">
                <span class="d">{{ $e[0] }}</span>
                <div>
                    <strong>{{ $e[1] }}</strong>@if (! empty($e[4])) <span class="odb-pill {{ $e[3] }}">{{ $e[4] }}</span>@endif
                    @if ($e[2])<small>{{ $e[2] }}</small>@endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
