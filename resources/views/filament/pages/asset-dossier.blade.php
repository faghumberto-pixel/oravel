<x-filament-panels::page>
    @if (! $asset)
        <x-filament::section>
            <form wire:submit="search" class="flex items-end gap-x-3">
                <div class="flex-1">
                    <label for="query" class="text-sm font-medium text-gray-950 dark:text-white">Patrimônio, nome, tag ou nº de série</label>
                    <input
                        type="text"
                        wire:model="query"
                        id="query"
                        placeholder="Ex: PAT-0001, Guindaste, AST-123..."
                        class="fi-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    />
                    @error('query')
                        <span class="text-sm text-danger-600">{{ $message }}</span>
                    @enderror
                </div>
                <x-filament::button type="submit">
                    Buscar
                </x-filament::button>
            </form>
            <p class="mt-2 text-sm text-gray-500">Não precisa ser exato — aceita parte do texto. Ou escaneie o QR code do ativo pra abrir o dossiê direto.</p>

            @if (! empty($searchResults))
                <div class="mt-4 space-y-1">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ count($searchResults) }} ativos encontrados — escolha um:</p>
                    @foreach ($searchResults as $result)
                        <button
                            type="button"
                            wire:click="selectResult('{{ $result['id'] }}')"
                            class="flex w-full items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            <span class="font-medium text-gray-950 dark:text-white">{{ $result['name'] }}</span>
                            <span class="text-xs text-gray-400">Patrimônio: {{ $result['patrimonio'] ?? '—' }} · Tag: {{ $result['tag'] ?? '—' }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @else
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-950 dark:text-white">{{ $asset->name }}</h2>
                <p class="text-sm text-gray-500">Patrimônio: {{ $asset->patrimonio ?? '—' }} · Tag: {{ $asset->tag ?? '—' }} · Nº Série: {{ $asset->serial_number ?? '—' }}</p>
            </div>
            <div class="flex gap-x-2">
                <x-filament::button
                    tag="a"
                    :href="route('assets.dossier.pdf', $asset)"
                    target="_blank"
                    icon="heroicon-o-printer"
                    color="gray"
                >
                    Imprimir / PDF
                </x-filament::button>
                <x-filament::button wire:click="clear" color="gray" icon="heroicon-o-magnifying-glass">
                    Nova busca
                </x-filament::button>
            </div>
        </div>

        <x-filament::section heading="Dados Gerais">
            <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                <div><span class="text-gray-400">Categoria</span><br><b>{{ $asset->asset_category ?? '—' }}</b></div>
                <div><span class="text-gray-400">Especificação</span><br><b>{{ $asset->specification ?? '—' }}</b></div>
                <div><span class="text-gray-400">Status</span><br><b>{{ ucfirst($asset->status ?? '—') }}</b></div>
                <div><span class="text-gray-400">Criticidade</span><br><b>{{ $asset->criticality_level ?? $asset->criticality ?? '—' }}</b></div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Cliente Atual">
            @if ($asset->client)
                <p class="text-sm">Com o cliente: <b>{{ $asset->client->name }}</b></p>
            @else
                <p class="text-sm text-gray-400">Disponível — sem cliente vinculado no momento.</p>
            @endif
        </x-filament::section>

        <x-filament::section heading="Contrato Vigente">
            @if ($this->currentContract)
                <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                    <div><span class="text-gray-400">Nº Contrato</span><br><b>{{ $this->currentContract->contract_number }}</b></div>
                    <div><span class="text-gray-400">Cliente</span><br><b>{{ $this->currentContract->client?->name ?? '—' }}</b></div>
                    <div><span class="text-gray-400">Início</span><br><b>{{ optional($this->currentContract->start_date)->format('d/m/Y') ?? '—' }}</b></div>
                    <div><span class="text-gray-400">Valor Mensal</span><br><b>R$ {{ number_format((float) $this->currentContract->price, 2, ',', '.') }}</b></div>
                </div>
            @else
                <p class="text-sm text-gray-400">Nenhum contrato ativo pra este ativo.</p>
            @endif
        </x-filament::section>

        <x-filament::section heading="Horas Trabalhadas">
            <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-3">
                <div><span class="text-gray-400">Horímetro Atual</span><br><b>{{ number_format((float) $asset->horimetro_atual, 2, ',', '.') }} h</b></div>
                <div><span class="text-gray-400">Horímetro Inicial</span><br><b>{{ number_format((float) $asset->horimetro_inicial, 2, ',', '.') }} h</b></div>
                @if ($asset->is_vehicle)
                    <div><span class="text-gray-400">Odômetro Atual</span><br><b>{{ number_format((float) $asset->odometro_atual, 2, ',', '.') }} km</b></div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section heading="Matriz ABC">
            @if ($asset->abcMatrix)
                <x-filament::badge :color="match ($asset->abcMatrix->nivel) {
                    'A' => 'danger',
                    'B' => 'warning',
                    default => 'success',
                }">
                    Nível {{ $asset->abcMatrix->nivel }} — {{ $asset->abcMatrix->descricao }}
                </x-filament::badge>
            @else
                <p class="text-sm text-gray-400">Sem classificação ABC cadastrada.</p>
            @endif
        </x-filament::section>

        <x-filament::section heading="Avarias Recentes">
            @forelse ($this->recentDamages as $damage)
                <div class="border-t border-gray-100 py-2 text-sm first:border-t-0 first:pt-0 dark:border-gray-700">
                    <p><b>{{ ucfirst($damage->severity) }}</b> — {{ $damage->description }}</p>
                    <p class="text-xs text-gray-400">{{ $damage->created_at->format('d/m/Y H:i') }} · Status: {{ $damage->status }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-400">Nenhuma avaria registrada.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Ordens de Serviço Abertas">
            @forelse ($this->openOrders as $order)
                <div class="border-t border-gray-100 py-2 text-sm first:border-t-0 first:pt-0 dark:border-gray-700">
                    <p><b>OS {{ $order->os_number }}</b> — {{ $order->status }}</p>
                    <p class="text-xs text-gray-400">Técnico: {{ $order->technician?->name ?? 'Não atribuído' }}</p>
                    <p class="text-xs text-gray-400">Problema relatado: {{ $order->reportedProblem?->description ?? $order->description ?? '—' }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-400">Nenhuma OS em aberto.</p>
            @endforelse
        </x-filament::section>
    @endif
</x-filament-panels::page>
