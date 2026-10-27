@if (count($p['rows']) === 0)
    <p class="odb-empty">{{ $p['empty'] }}</p>
@else
    <div class="odb-tw">
        <table>
            <thead><tr>
                @foreach ($p['headers'] as $h)
                    <th @if (str_starts_with($h, '>')) class="r" @endif>{{ ltrim($h, '>') }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach ($p['rows'] as $row)
                    <tr>
                        @foreach ($row as $i => $cell)
                            <td @if (str_starts_with($p['headers'][$i], '>')) class="r" @endif>
                                @if (is_array($cell))
                                    <span class="odb-pill {{ $cell['pill'] }}">{{ $cell['text'] }}</span>
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
