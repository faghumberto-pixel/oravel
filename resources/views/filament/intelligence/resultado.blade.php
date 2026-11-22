@php
    $areas = config('intelligence.areas');
    $diag = $snapshot->diagnostics->keyBy('area');
    $titulos = collect(\App\Domain\Intelligence\Support\Catalogo::indicadores());
@endphp
<div class="space-y-4 text-sm">
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach ($areas as $chave => $rotulo)
            @if ($d = $diag->get($chave))
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="text-xs text-gray-500">{{ $rotulo }}</div>
                    <div class="text-xl font-semibold">{{ number_format($d->pontuacao, 0) }}</div>
                    <div class="text-xs">{{ $d->faixa }}</div>
                </div>
            @endif
        @endforeach
    </div>
    <table class="w-full text-left">
        <thead><tr class="text-xs text-gray-500"><th class="py-1">Indicador</th><th class="py-1 text-right">Valor</th></tr></thead>
        <tbody>
            @foreach ($snapshot->indicators as $i)
                <tr class="border-t border-gray-100 dark:border-white/5">
                    <td class="py-1">{{ $titulos[$i->chave]['titulo'] ?? $i->chave }}</td>
                    <td class="py-1 text-right">{{ number_format((float) $i->valor, 2, ',', '.') }} {{ $i->unidade }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
