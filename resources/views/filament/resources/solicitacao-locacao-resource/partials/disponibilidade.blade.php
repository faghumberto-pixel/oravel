<div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 p-3">
    <div class="grid grid-cols-3 gap-3 text-center mb-3">
        <div>
            <div class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ $total }}</div>
            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Cadastrados</div>
        </div>
        <div>
            <div class="text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $naoLocadosTotal }}</div>
            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Não locados</div>
        </div>
        <div>
            <div class="text-2xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ $emManutencao }}</div>
            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Em manutenção</div>
        </div>
    </div>

    @if($naoLocadosTotal > 0)
        <div class="text-[11px] text-gray-500 dark:text-gray-400">
            <span class="font-semibold text-gray-600 dark:text-gray-300">Por unidade:</span>
            @foreach($porUnidade as $unidade => $qtd)
                <span class="inline-flex items-center gap-1 ml-1.5">{{ $unidade }} <span class="font-mono font-bold">({{ $qtd }})</span></span>@if(!$loop->last),@endif
            @endforeach
        </div>
    @elseif($total === 0)
        <p class="text-[11px] text-gray-400 italic">Nenhum ativo cadastrado nesta categoria ainda.</p>
    @else
        <p class="text-[11px] text-rose-500 italic">Todos os {{ $total }} ativos desta categoria já estão locados ou reservados.</p>
    @endif
</div>
