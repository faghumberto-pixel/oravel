<div class="space-y-2 text-sm">
    @forelse ($entradas as $e)
        @php $dif = (float) $e->quantity - (float) $esperado; @endphp
        <div class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10">
            <div>
                <p class="font-semibold">{{ $e->counted_by_name ?: 'Usuário não identificado' }}</p>
                <p class="text-xs text-gray-500">{{ $e->counted_at->format('d/m/Y \à\s H:i:s') }} · {{ $e->source === 'celular' ? 'pelo celular' : 'pelo computador' }}@if ($e->note) · {{ $e->note }}@endif</p>
            </div>
            <div class="text-right">
                <p class="text-lg font-bold tabular-nums">{{ rtrim(rtrim(number_format((float) $e->quantity, 3, ',', '.'), '0'), ',') }}</p>
                <p class="text-xs {{ abs($dif) < 0.001 ? 'text-green-600' : 'text-red-600' }}">{{ abs($dif) < 0.001 ? 'igual ao sistema' : ($dif > 0 ? '+' : '').rtrim(rtrim(number_format($dif, 3, ',', '.'), '0'), ',').' vs sistema' }}</p>
            </div>
        </div>
    @empty
        <p class="text-gray-500">Este item ainda não foi contado.</p>
    @endforelse
</div>
