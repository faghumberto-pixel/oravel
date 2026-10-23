<x-mobile.shell title="Meu Estoque" subtitle="Peças no seu almoxarifado volante">
    @if (! $warehouse)
        <div class="rounded-xl border border-zinc-200 bg-white p-5 text-center shadow-sm">
            <p class="text-sm font-semibold text-zinc-900">Nenhum veículo vinculado</p>
            <p class="mt-1 text-xs leading-relaxed text-zinc-600">
                Você ainda não tem um Almoxarifado Volante cadastrado. Peça ao administrador para vincular seu usuário a um veículo em Almoxarifados.
            </p>
        </div>
    @else
        {{-- Identificação do veículo --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-zinc-900">{{ $warehouse->name }}</h2>
                    <p class="text-xs text-zinc-600">Placa {{ $warehouse->vehicle_plate ?? '—' }}</p>
                </div>
                <span class="rounded-full bg-blue-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-blue-800">Volante</span>
            </div>
        </div>

        {{-- Busca --}}
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar peça por nome ou SKU..."
            class="w-full rounded-xl border border-zinc-200 bg-white p-3 text-base text-zinc-900 placeholder-zinc-400 shadow-sm focus:ring-2 focus:ring-blue-500"
        />

        {{-- Lista de peças --}}
        @forelse ($stocks as $stock)
            @php
                $isLow = (float) $stock->current_quantity < (float) ($stock->part->minimum_stock ?? 0);
                $isNegative = (float) $stock->current_quantity < 0;
            @endphp
            <div @class([
                'rounded-xl border p-4 shadow-sm',
                'border-zinc-200 bg-white' => ! $isLow,
                'border-red-200 bg-red-50' => $isLow && ! $isNegative,
                'border-red-300 bg-red-100' => $isNegative,
            ])>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-zinc-900">{{ $stock->part->name }}</p>
                        <p class="text-xs text-zinc-600">{{ $stock->part->sku }} · {{ $stock->part->unit_of_measure }}</p>
                    </div>
                    <div class="text-right">
                        <p @class([
                            'text-lg font-bold tabular-nums',
                            'text-zinc-900' => ! $isLow,
                            'text-red-700' => $isLow,
                        ])>{{ number_format((float) $stock->current_quantity, 1, ',', '.') }}</p>
                        @if ($isNegative)
                            <p class="text-[10px] font-bold uppercase tracking-wide text-red-700">Saldo negativo</p>
                        @elseif ($isLow)
                            <p class="text-[10px] font-bold uppercase tracking-wide text-red-700">Abaixo do mínimo</p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-white p-5 text-center shadow-sm">
                <p class="text-sm text-zinc-600">
                    {{ filled($search) ? 'Nenhuma peça encontrada.' : 'Seu veículo ainda não tem peças transferidas.' }}
                </p>
            </div>
        @endforelse
    @endif
</x-mobile.shell>
