<x-filament-panels::page>
    @php
        $lista = $this->pendencias();
        $servico = app(\App\Services\Frota\PendenciasFrotaService::class);
    @endphp

    <div class="grid gap-3 sm:grid-cols-3">
        <select wire:model.live="filtroCategoria" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
            <option value="">Todas as categorias</option>
            @foreach (\App\Services\Frota\PendenciasFrotaService::categorias() as $k => $nome)
                <option value="{{ $k }}">{{ $nome }}</option>
            @endforeach
        </select>
        <select wire:model.live="filtroGravidade" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
            <option value="">Todas as gravidades</option>
            <option value="critica">Críticas</option>
            <option value="atencao">Atenção</option>
        </select>
        <select wire:model.live="filtroVeiculo" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
            <option value="">Todos os veículos</option>
            @foreach ($this->veiculos() as $id => $nome)
                <option value="{{ $id }}">{{ $nome }}</option>
            @endforeach
        </select>
    </div>

    @if ($lista->isEmpty())
        <div class="rounded-xl bg-emerald-50 p-6 text-center text-sm font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
            Nenhuma pendência. A frota está em dia.
        </div>
    @else
        <div class="divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
            @foreach ($lista as $p)
                @php
                    $os = $p['ativo_id'] ? $servico->osAberta($p['chave']) : null;
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 p-4" wire:key="{{ $p['chave'] }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $p['gravidade'] === 'critica' ? 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' }}">
                                {{ $p['gravidade'] === 'critica' ? 'Crítica' : 'Atenção' }}
                            </span>
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ \App\Services\Frota\PendenciasFrotaService::categorias()[$p['categoria']] }}</span>
                            @if ($p['placa'] || $p['veiculo'])
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $p['placa'] ?? $p['veiculo'] }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $p['mensagem'] }}</p>
                    </div>
                    @if ($os)
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">OS {{ $os->os_number }} aberta</span>
                    @elseif ($p['ativo_id'])
                        <x-filament::button size="sm" color="gray" wire:click="gerarOs('{{ $p['chave'] }}')" wire:confirm="Abrir uma OS para esta pendência?">Gerar OS</x-filament::button>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
