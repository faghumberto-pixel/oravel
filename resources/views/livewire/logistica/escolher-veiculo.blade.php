{{-- Escolha do veículo (lista + busca por placa, patrimônio ou nome). Variáveis: $veiculos, $titulo --}}
<p class="text-xs font-bold tracking-wider text-slate-400">{{ $titulo }}</p>
<input type="text" wire:model.live.debounce.400ms="buscaVeiculo" placeholder="Buscar por placa, patrimônio ou nome" class="min-h-[3rem] w-full rounded-xl bg-slate-800 px-3 text-base text-white">
@forelse ($veiculos as $v)
    <button type="button" wire:click="escolherVeiculo('{{ $v->id }}')" class="flex w-full items-center justify-between rounded-2xl bg-slate-900 p-4 text-left active:bg-slate-800">
        <span>
            <span class="block text-base font-extrabold text-white">{{ $v->placa ?? $v->name }}</span>
            <span class="block text-xs text-slate-400">{{ $v->name }}@if ($v->patrimonio) · PAT {{ $v->patrimonio }}@endif</span>
        </span>
        <span class="text-xs font-bold text-slate-400">{{ number_format((float) $v->odometro_atual, 0, ',', '.') }} km</span>
    </button>
@empty
    <p class="rounded-2xl bg-slate-900 p-4 text-sm text-slate-400">Nenhum veículo encontrado.</p>
@endforelse
