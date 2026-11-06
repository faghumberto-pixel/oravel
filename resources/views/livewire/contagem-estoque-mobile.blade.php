<x-mobile.shell :title="$contagem ? $contagem->name : 'Contagem física'" :subtitle="$contagem ? ($contagem->internalUnit?->name ?? 'Almoxarifado') : 'Contagens abertas'" :nav="false">
    @if ($contagemId)
        <x-slot:actions>
            <button type="button" wire:click="voltar" class="rounded-full bg-white/20 px-4 py-2 text-sm font-semibold text-white">← Voltar</button>
        </x-slot:actions>
    @endif

    @if ($aviso)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm font-medium text-amber-900">{{ $aviso }}</div>
    @endif

    @if ($contagemId)
        <x-mobile.leitor-qr metodo="lerQr" rotulo="Ler QR do item ou da prateleira" />
    @endif

    {{-- 1) Lista de contagens abertas --}}
    @if (! $contagemId)
        @forelse ($contagens as $c)
            @php $pct = $c->items_count > 0 ? (int) round($c->contados_count / $c->items_count * 100) : 0; @endphp
            <button type="button" wire:click="abrirContagem('{{ $c->id }}')" class="block w-full rounded-xl border border-zinc-200 bg-white p-4 text-left shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold text-zinc-900">{{ $c->name }}</p>
                        <p class="text-xs text-zinc-600">{{ $c->internalUnit?->name }} · aberta em {{ $c->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <span class="shrink-0 text-lg font-bold tabular-nums text-blue-700">{{ $pct }}%</span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-200"><div class="h-full rounded-full bg-blue-600" style="width: {{ $pct }}%"></div></div>
                <p class="mt-2 text-xs text-zinc-600">{{ $c->contados_count }} de {{ $c->items_count }} itens contados</p>
            </button>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-white p-5 text-center shadow-sm">
                <p class="text-sm font-semibold text-zinc-900">Nenhuma contagem aberta</p>
                <p class="mt-1 text-xs leading-relaxed text-zinc-600">Peça ao responsável pelo estoque para abrir uma contagem física em Materiais e Peças → Contagens físicas.</p>
            </div>
        @endforelse

    {{-- 2) Enderecos da contagem --}}
    @elseif ($contagem && $enderecoId === null && $busca === '')
        <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Buscar item por nome ou código..." class="w-full rounded-xl border border-zinc-200 bg-white p-3 text-base text-zinc-900 placeholder-zinc-400 shadow-sm focus:ring-2 focus:ring-blue-500" />
        <p class="px-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">Corredores e prateleiras</p>
        @forelse ($enderecos as $e)
            @php $pct = $e['total'] > 0 ? (int) round($e['contados'] / $e['total'] * 100) : 0; $feito = $e['contados'] === $e['total']; @endphp
            <button type="button" wire:click="abrirEndereco('{{ $e['id'] }}')" @class(['block w-full rounded-xl border p-4 text-left shadow-sm', 'border-emerald-300 bg-emerald-50' => $feito, 'border-zinc-200 bg-white' => ! $feito])>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-base font-bold text-zinc-900">{{ $e['codigo'] }}</p>
                    <span @class(['rounded-full px-3 py-1 text-xs font-bold', 'bg-emerald-600 text-white' => $feito, 'bg-blue-100 text-blue-800' => ! $feito])>{{ $feito ? 'Concluído' : $e['contados'].'/'.$e['total'] }}</span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-200"><div @class(['h-full rounded-full', 'bg-emerald-600' => $feito, 'bg-blue-600' => ! $feito]) style="width: {{ $pct }}%"></div></div>
            </button>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-white p-5 text-center text-sm text-zinc-600 shadow-sm">Esta contagem não tem itens.</div>
        @endforelse

    {{-- 3) Itens (de um endereco ou da busca) --}}
    @elseif ($contagem)
        @if ($busca !== '' || $enderecoId === null)
            <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Buscar item por nome ou código..." class="w-full rounded-xl border border-zinc-200 bg-white p-3 text-base text-zinc-900 placeholder-zinc-400 shadow-sm focus:ring-2 focus:ring-blue-500" />
        @else
            <p class="px-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $itens->first()?->location_code ?: 'Sem endereço' }} · conte o que está na prateleira</p>
        @endif

        @forelse ($itens as $item)
            @php $contado = $item->counted_quantity !== null; @endphp
            <div wire:key="item-{{ $item->id }}" @class(['rounded-xl border p-4 shadow-sm', 'border-emerald-300 bg-emerald-50' => $contado, 'border-zinc-200 bg-white' => ! $contado])>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold leading-snug text-zinc-900">{{ $item->item_name }}</p>
                        <p class="text-xs text-zinc-600">{{ $item->item_sku ?: 'sem código' }} · {{ $item->location_code }} · {{ $item->group_name ?: 'sem grupo' }}</p>
                    </div>
                    @if ($contado)
                        <span class="shrink-0 rounded-full bg-emerald-600 px-3 py-1 text-xs font-bold text-white">{{ rtrim(rtrim(number_format((float) $item->counted_quantity, 3, ',', '.'), '0'), ',') }}</span>
                    @endif
                </div>
                @if ($contado)
                    <p class="mt-1 text-[11px] text-emerald-800">Contado por {{ $item->counted_by_name ?: '—' }} em {{ $item->counted_at?->format('d/m/Y H:i') }}</p>
                @endif
                <div class="mt-3 flex items-center gap-2">
                    <input type="text" inputmode="decimal" wire:model="quantidades.{{ $item->id }}" wire:keydown.enter="registrar('{{ $item->id }}')" placeholder="{{ $contado ? 'Recontar' : 'Quantidade' }}" class="min-w-0 flex-1 rounded-xl border border-zinc-300 bg-white p-3 text-lg font-semibold tabular-nums text-zinc-900 focus:ring-2 focus:ring-blue-500" />
                    <button type="button" wire:click="registrar('{{ $item->id }}')" class="shrink-0 rounded-xl bg-blue-600 px-5 py-3 text-base font-bold text-white active:bg-blue-700">{{ $contado ? 'Recontar' : 'Registrar' }}</button>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-white p-5 text-center text-sm text-zinc-600 shadow-sm">Nenhum item encontrado.</div>
        @endforelse
    @endif
</x-mobile.shell>
