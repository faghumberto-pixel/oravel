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
        @php
            $worked = $this->workedHoursSummary;
            $paymentStatus = $this->paymentStatus;
        @endphp

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
                    Ver e imprimir
                </x-filament::button>
                <x-filament::button wire:click="clear" color="gray" icon="heroicon-o-magnifying-glass">
                    Nova busca
                </x-filament::button>
            </div>
        </div>

        {{-- Pedido do usuário 29/09/2026: dossiê mais minimalista, "como um
             relatório mesmo" -- uma seção de resumo só, com contrato/cliente
             clicáveis e a situação de pagamento em uma linha (em dia/atrasado),
             sem listar fatura por fatura (isso é assunto de Contas a Receber). --}}
        <x-filament::section heading="Resumo">
            <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                <div><span class="text-gray-400">Categoria</span><br><b>{{ $asset->asset_category ?? '—' }}</b></div>
                <div><span class="text-gray-400">Status</span><br><b>{{ ucfirst($asset->status ?? '—') }}</b></div>

                <div>
                    <span class="text-gray-400">Cliente</span><br>
                    @if ($asset->client)
                        <a href="{{ \App\Filament\Resources\ClientResource::getUrl('edit', ['record' => $asset->client]) }}" class="font-bold text-primary-600 hover:underline dark:text-primary-400">
                            {{ $asset->client->name }}
                        </a>
                    @else
                        <b class="text-gray-400">Disponível</b>
                    @endif
                </div>

                <div>
                    <span class="text-gray-400">Contrato</span><br>
                    @if ($this->currentContract)
                        <a href="{{ \App\Filament\Resources\ContractResource::getUrl('edit', ['record' => $this->currentContract]) }}" class="font-bold text-primary-600 hover:underline dark:text-primary-400">
                            {{ $this->currentContract->contract_number }}
                        </a>
                    @else
                        <b class="text-gray-400">—</b>
                    @endif
                </div>
            </div>

            @if ($paymentStatus)
                <div class="mt-4">
                    <x-filament::badge :color="$paymentStatus === 'em_dia' ? 'success' : 'danger'">
                        {{ $paymentStatus === 'em_dia' ? '✓ Cliente em dia com os pagamentos' : '⚠ Cliente com pagamento em atraso' }}
                    </x-filament::badge>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Horas Trabalhadas">
            <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                <div><span class="text-gray-400">Horímetro Atual</span><br><b>{{ number_format($worked['horimetro_atual'], 2, ',', '.') }} h</b></div>

                @if ($worked['dias_locado'] !== null)
                    <div><span class="text-gray-400">Trabalhado na Locação</span><br><b>{{ number_format($worked['horas_trabalhadas'], 2, ',', '.') }} h</b></div>
                    <div><span class="text-gray-400">Dias de Locação</span><br><b>{{ $worked['dias_locado'] }} dia(s)</b></div>
                    <div><span class="text-gray-400">Média Diária</span><br><b>{{ number_format($worked['media_diaria'], 2, ',', '.') }} h/dia</b></div>
                @else
                    <div class="md:col-span-3"><span class="text-gray-400">{{ $this->currentContract ? 'Sem histórico de horímetro suficiente pra calcular horas trabalhadas nesta locação.' : 'Sem contrato ativo pra calcular horas trabalhadas na locação.' }}</span></div>
                @endif

                @if ($asset->is_vehicle)
                    <div><span class="text-gray-400">Odômetro Atual</span><br><b>{{ number_format((float) $asset->odometro_atual, 2, ',', '.') }} km</b></div>
                @endif
            </div>
        </x-filament::section>

        {{-- Alertas condensados: só contagens, sem listar item por item
             (era 3 seções verbosas -- Matriz ABC, Avarias Recentes, OS Abertas
             -- agora é uma linha de sinalização). --}}
        <x-filament::section heading="Alertas">
            <div class="flex flex-wrap items-center gap-3 text-sm">
                @if ($asset->abcMatrix)
                    <x-filament::badge :color="match ($asset->abcMatrix->nivel) {
                        'A' => 'danger',
                        'B' => 'warning',
                        default => 'success',
                    }">
                        Criticidade {{ $asset->abcMatrix->nivel }}
                    </x-filament::badge>
                @endif

                <x-filament::badge :color="$this->recentDamages->isNotEmpty() ? 'danger' : 'success'">
                    {{ $this->recentDamages->count() }} avaria(s) recente(s)
                </x-filament::badge>

                <x-filament::badge :color="$this->openOrders->isNotEmpty() ? 'warning' : 'success'">
                    {{ $this->openOrders->count() }} OS em aberto
                </x-filament::badge>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
