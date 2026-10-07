@if ($entregas->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma retirada registrada.</p>
@else
    <div class="divide-y divide-gray-200 text-sm dark:divide-white/10">
        @foreach ($entregas as $e)
            <div class="py-2">
                <p class="font-semibold text-gray-900 dark:text-white">{{ $e->responsavel() }} — {{ $e->motivo }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Retirou em {{ $e->entregue_em->format('d/m/Y H:i') }} ·
                    {{ $e->devolvida_em ? 'devolveu em '.$e->devolvida_em->format('d/m/Y H:i') : 'ainda não devolveu' }}
                </p>
            </div>
        @endforeach
    </div>
@endif
