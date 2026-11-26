@props(['titulo', 'colunas', 'linhas' => [], 'vazio' => 'Nada a mostrar.', 'subtitulo' => null])
@php
    // Formatos: txt | num (inteiro/decimal pt-BR) | brl | pct | dias
    $fmt = function ($v, string $tipo) {
        if ($v === null || $v === '') return '—';
        return match ($tipo) {
            'brl' => 'R$ '.number_format((float) $v, 2, ',', '.'),
            'pct' => rtrim(rtrim(number_format((float) $v, 1, ',', '.'), '0'), ',').'%',
            'dias' => (int) $v.' d',
            'num' => rtrim(rtrim(number_format((float) $v, 1, ',', '.'), '0'), ','),
            default => (string) $v,
        };
    };
@endphp
<div class="rounded-xl p-4" style="border:1px solid rgba(128,128,128,.3)">
    <p class="font-semibold">{{ $titulo }}</p>
    @if ($subtitulo)<p class="mb-2 text-xs" style="opacity:.7">{{ $subtitulo }}</p>@endif
    @if (count($linhas))
        <div style="overflow-x:auto">
            <table class="w-full text-sm" style="border-collapse:collapse;min-width:420px">
                <thead><tr>
                    @foreach ($colunas as $c)<th class="text-left py-1 pr-3" style="font-size:12px;opacity:.7;white-space:nowrap">{{ $c['label'] }}</th>@endforeach
                </tr></thead>
                <tbody>
                @foreach ($linhas as $l)
                    <tr style="border-top:1px solid rgba(128,128,128,.2)">
                        @foreach ($colunas as $k => $c)<td class="py-1 pr-3" style="vertical-align:top">{{ $fmt($l[$k] ?? null, $c['fmt'] ?? 'txt') }}</td>@endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm" style="opacity:.7">{{ $vazio }}</p>
    @endif
</div>
