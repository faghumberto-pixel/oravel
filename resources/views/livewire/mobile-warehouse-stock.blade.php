<div class="mx-auto flex min-h-screen max-w-md flex-col md:h-full md:min-h-0 md:overflow-y-auto">
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        <h1 class="text-xs font-bold tracking-widest text-zinc-400">MEU ESTOQUE</h1>
        <span class="text-xs font-bold tracking-wide text-zinc-300">{{ strtoupper(config('app.name', 'ORAVEL')) }}</span>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-8">
        @if (! $warehouse)
            <div class="rounded-2xl bg-zinc-900 p-5 text-center">
                <p class="text-sm font-semibold text-zinc-200">Nenhum veículo vinculado</p>
                <p class="mt-1 text-[11px] leading-relaxed text-zinc-500">
                    Você ainda não tem um Almoxarifado Volante cadastrado. Peça ao administrador para vincular seu usuário a um veículo em Almoxarifados.
                </p>
            </div>
        @else
            {{-- Identificação do veículo --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-zinc-100">{{ $warehouse->name }}</h2>
                        <p class="text-[11px] text-zinc-500">Placa {{ $warehouse->vehicle_plate ?? '—' }}</p>
                    </div>
                    <span class="rounded-full bg-orange-500/15 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-orange-400">
                        Volante
                    </span>
                </div>
            </div>

            {{-- Busca --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar peça por nome ou SKU..."
                    class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-orange-500"
                />
            </div>

            {{-- Lista de peças --}}
            @forelse ($stocks as $stock)
                @php
                    $isLow = (float) $stock->current_quantity < (float) ($stock->part->minimum_stock ?? 0);
                    $isNegative = (float) $stock->current_quantity < 0;
                @endphp
                <div @class([
                    'rounded-2xl p-4',
                    'bg-zinc-900' => ! $isLow,
                    'bg-red-950/40 ring-1 ring-red-900' => $isLow && ! $isNegative,
                    'bg-red-950/60 ring-1 ring-red-700' => $isNegative,
                ])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-zinc-100">{{ $stock->part->name }}</p>
                            <p class="text-[11px] text-zinc-500">{{ $stock->part->sku }} · {{ $stock->part->unit_of_measure }}</p>
                        </div>
                        <div class="text-right">
                            <p @class([
                                'text-lg font-bold tabular-nums',
                                'text-zinc-100' => ! $isLow,
                                'text-red-400' => $isLow,
                            ])>{{ number_format((float) $stock->current_quantity, 1, ',', '.') }}</p>
                            @if ($isNegative)
                                <p class="text-[10px] font-bold uppercase tracking-wide text-red-400">Saldo negativo</p>
                            @elseif ($isLow)
                                <p class="text-[10px] font-bold uppercase tracking-wide text-red-400">Abaixo do mínimo</p>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl bg-zinc-900 p-5 text-center">
                    <p class="text-sm text-zinc-400">
                        {{ filled($search) ? 'Nenhuma peça encontrada.' : 'Seu veículo ainda não tem peças transferidas.' }}
                    </p>
                </div>
            @endforelse
        @endif
    </main>
</div>
