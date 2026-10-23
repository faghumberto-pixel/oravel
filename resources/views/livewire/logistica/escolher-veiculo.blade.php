{{-- Escolha do veículo (lista + busca por placa, patrimônio ou nome). Variáveis: $veiculos, $titulo --}}
<p class="text-xs font-bold tracking-wider text-zinc-600">{{ $titulo }}</p>
<input type="text" wire:model.live.debounce.400ms="buscaVeiculo" placeholder="Buscar por placa, patrimônio ou nome" class="min-h-[3rem] w-full rounded-xl bg-zinc-100 px-3 text-base text-zinc-900 border border-zinc-300">
@forelse ($veiculos as $v)
    <button type="button" wire:click="escolherVeiculo('{{ $v->id }}')" class="flex w-full items-center justify-between rounded-xl bg-white p-4 text-left active:bg-zinc-100 border border-zinc-200 shadow-sm">
        <span>
            <span class="block text-base font-extrabold text-zinc-900">{{ $v->placa ?? $v->name }}</span>
            <span class="block text-xs text-zinc-600">{{ $v->name }}@if ($v->patrimonio) · PAT {{ $v->patrimonio }}@endif</span>
        </span>
        <span class="text-xs font-bold text-zinc-600">{{ number_format((float) $v->odometro_atual, 0, ',', '.') }} km</span>
    </button>
@empty
    <p class="rounded-xl bg-white p-4 text-sm text-zinc-600 border border-zinc-200 shadow-sm">Nenhum veículo encontrado.</p>
@endforelse
