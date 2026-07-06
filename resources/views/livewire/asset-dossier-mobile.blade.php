<div class="mx-auto flex min-h-screen max-w-md flex-col">
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        <h1 class="text-xs font-bold tracking-widest text-zinc-400">DOSSIÊ DO ATIVO</h1>
        <span class="text-xs font-bold tracking-wide text-zinc-300">{{ strtoupper(config('app.name', 'ORAVEL')) }}</span>
    </header>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-8">
        @if (! $asset)
            {{-- Busca --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Buscar Ativo</h3>
                <p class="mt-1 text-[11px] text-zinc-500">Patrimônio, nome, tag ou nº de série — não precisa ser exato.</p>

                <form wire:submit="search" class="mt-3 space-y-2">
                    <input
                        type="text"
                        wire:model="query"
                        placeholder="Ex: PAT-0001, Guindaste..."
                        class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-orange-500"
                        autofocus
                    />
                    @error('query')
                        <p class="text-[11px] text-red-400">{{ $message }}</p>
                    @enderror

                    <button
                        type="submit"
                        class="min-h-[3rem] w-full rounded-xl bg-orange-500 text-sm font-bold text-white active:bg-orange-600"
                    >
                        Buscar
                    </button>
                </form>
            </div>

            @if (! empty($searchResults))
                <div class="rounded-2xl bg-zinc-900 p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">
                        {{ count($searchResults) }} encontrados — toque para abrir
                    </h3>
                    <div class="mt-3 space-y-2">
                        @foreach ($searchResults as $result)
                            <button
                                type="button"
                                wire:click="selectResult('{{ $result['id'] }}')"
                                class="min-h-[3rem] w-full rounded-xl border border-zinc-700 p-3 text-left active:bg-zinc-800"
                            >
                                <span class="block text-sm font-bold text-white">{{ $result['name'] }}</span>
                                <span class="block text-[11px] text-zinc-500">Patrimônio: {{ $result['patrimonio'] ?? '—' }} · Tag: {{ $result['tag'] ?? '—' }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            {{-- Cabeçalho do ativo --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h2 class="text-xl font-extrabold leading-tight text-white">{{ $asset->name }}</h2>
                <p class="mt-1 text-sm font-medium text-zinc-400">
                    PAT: {{ $asset->patrimonio ?? '—' }} · TAG: {{ $asset->tag ?? '—' }}
                </p>
                <div class="mt-3 flex gap-2">
                    <a
                        href="{{ route('assets.dossier.pdf', $asset) }}"
                        target="_blank"
                        class="flex min-h-[2.75rem] flex-1 items-center justify-center rounded-xl border border-zinc-700 text-xs font-bold text-zinc-200 active:bg-zinc-800"
                    >
                        Imprimir / PDF
                    </a>
                    <button
                        type="button"
                        wire:click="clear"
                        class="flex min-h-[2.75rem] flex-1 items-center justify-center rounded-xl border border-zinc-700 text-xs font-bold text-zinc-200 active:bg-zinc-800"
                    >
                        Nova Busca
                    </button>
                </div>
            </div>

            {{-- Dados gerais --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Dados Gerais</h3>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div><span class="block text-[11px] text-zinc-500">Categoria</span><span class="font-semibold text-zinc-100">{{ $asset->asset_category ?? '—' }}</span></div>
                    <div><span class="block text-[11px] text-zinc-500">Status</span><span class="font-semibold text-zinc-100">{{ ucfirst($asset->status ?? '—') }}</span></div>
                    <div><span class="block text-[11px] text-zinc-500">Especificação</span><span class="font-semibold text-zinc-100">{{ $asset->specification ?? '—' }}</span></div>
                    <div><span class="block text-[11px] text-zinc-500">Criticidade</span><span class="font-semibold text-zinc-100">{{ $asset->criticality_level ?? $asset->criticality ?? '—' }}</span></div>
                </div>
            </div>

            {{-- Cliente atual --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Cliente Atual</h3>
                <p class="mt-2 text-sm text-zinc-100">
                    {{ $asset->client?->name ?? 'Disponível — sem cliente vinculado no momento.' }}
                </p>
            </div>

            {{-- Contrato vigente --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Contrato Vigente</h3>
                @if ($this->currentContract)
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div><span class="block text-[11px] text-zinc-500">Nº Contrato</span><span class="font-semibold text-zinc-100">{{ $this->currentContract->contract_number }}</span></div>
                        <div><span class="block text-[11px] text-zinc-500">Cliente</span><span class="font-semibold text-zinc-100">{{ $this->currentContract->client?->name ?? '—' }}</span></div>
                        <div><span class="block text-[11px] text-zinc-500">Início</span><span class="font-semibold text-zinc-100">{{ optional($this->currentContract->start_date)->format('d/m/Y') ?? '—' }}</span></div>
                        <div><span class="block text-[11px] text-zinc-500">Valor Mensal</span><span class="font-semibold text-zinc-100">R$ {{ number_format((float) $this->currentContract->price, 2, ',', '.') }}</span></div>
                    </div>
                @else
                    <p class="mt-2 text-sm text-zinc-500">Nenhum contrato ativo para este ativo.</p>
                @endif
            </div>

            {{-- Horas trabalhadas --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Horas Trabalhadas</h3>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div><span class="block text-[11px] text-zinc-500">Horímetro Atual</span><span class="font-semibold text-zinc-100">{{ number_format((float) $asset->horimetro_atual, 2, ',', '.') }} h</span></div>
                    @if ($asset->is_vehicle)
                        <div><span class="block text-[11px] text-zinc-500">Odômetro Atual</span><span class="font-semibold text-zinc-100">{{ number_format((float) $asset->odometro_atual, 2, ',', '.') }} km</span></div>
                    @endif
                </div>
            </div>

            {{-- Matriz ABC --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Matriz ABC</h3>
                @if ($asset->abcMatrix)
                    @php
                        $abcColor = match ($asset->abcMatrix->nivel) {
                            'A' => 'bg-red-500/15 text-red-400',
                            'B' => 'bg-amber-500/15 text-amber-400',
                            default => 'bg-emerald-500/15 text-emerald-400',
                        };
                    @endphp
                    <span class="mt-2 inline-block rounded-full px-3 py-1 text-xs font-bold {{ $abcColor }}">
                        Nível {{ $asset->abcMatrix->nivel }} — {{ $asset->abcMatrix->descricao }}
                    </span>
                @else
                    <p class="mt-2 text-sm text-zinc-500">Sem classificação ABC cadastrada.</p>
                @endif
            </div>

            {{-- Avarias recentes --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Avarias Recentes</h3>
                <div class="mt-2 space-y-2">
                    @forelse ($this->recentDamages as $damage)
                        <div class="border-t border-zinc-800 pt-2 text-sm first:border-t-0 first:pt-0">
                            <p class="font-semibold text-zinc-100">{{ ucfirst($damage->severity) }} — {{ $damage->description }}</p>
                            <p class="text-[11px] text-zinc-500">{{ $damage->created_at->format('d/m/Y H:i') }} · {{ $damage->status }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">Nenhuma avaria registrada.</p>
                    @endforelse
                </div>
            </div>

            {{-- OS abertas --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Ordens de Serviço Abertas</h3>
                <div class="mt-2 space-y-2">
                    @forelse ($this->openOrders as $order)
                        <div class="border-t border-zinc-800 pt-2 text-sm first:border-t-0 first:pt-0">
                            <p class="font-semibold text-zinc-100">OS {{ $order->os_number }} — {{ $order->status }}</p>
                            <p class="text-[11px] text-zinc-500">Técnico: {{ $order->technician?->name ?? 'Não atribuído' }}</p>
                            <p class="text-[11px] text-zinc-500">Problema: {{ $order->reportedProblem?->description ?? $order->description ?? '—' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">Nenhuma OS em aberto.</p>
                    @endforelse
                </div>
            </div>
        @endif
    </main>
</div>
