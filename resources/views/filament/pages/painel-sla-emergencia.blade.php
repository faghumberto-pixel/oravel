<x-filament-panels::page>
    @php
        $resumo = $this->resumo;
        $corStyles = [
            'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400',
            'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/10 dark:bg-warning-400/10 dark:text-warning-400',
            'success' => 'bg-success-50 text-success-700 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400',
            'gray' => 'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400',
        ];
        $rotulos = \App\Models\SlaTimer::CLOCKS;
    @endphp

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Cumprimento do SLA nos últimos {{ $this->dias }} dias</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Percentual de O.S. que cumpriram o prazo de cada relógio (cancelamentos não entram).</p>
            </div>
            <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Período">
                @foreach ([7 => '7 dias', 30 => '30 dias', 90 => '90 dias'] as $d => $rot)
                    <button type="button" wire:click="$set('dias', {{ $d }})"
                            class="rounded-lg px-3 py-1.5 text-sm font-semibold ring-1 ring-inset {{ $this->dias === $d ? 'bg-primary-600 text-white ring-primary-600' : 'bg-white text-gray-700 ring-gray-300 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/20' }}">{{ $rot }}</button>
                @endforeach
            </div>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ($rotulos as $chave => $rotulo)
                @php
                    $r = $this->indicadores['geral'][$chave] ?? ['total' => 0, 'cumpridos' => 0, 'pct' => null, 'tempo_medio' => null];
                @endphp
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <span class="block text-[10px] uppercase tracking-widest text-gray-400">{{ $rotulo }}</span>
                    <span class="mt-1 block text-2xl font-black {{ ($r['pct'] ?? 100) >= 90 ? 'text-emerald-600 dark:text-emerald-400' : (($r['pct'] ?? 100) >= 70 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400') }}">
                        {{ $r['pct'] !== null ? $r['pct'].'%' : '—' }}
                    </span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $r['cumpridos'] }} de {{ $r['total'] }} concluídos no prazo</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Tempo médio: {{ \App\Services\Sla\SlaService::formatarMinutos($r['tempo_medio']) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Indicadores por {{ strtolower($rotulos[$this->relogio]) }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Do pior para o melhor percentual. "Vencidos agora" são as O.S. abertas com este relógio já estourado.</p>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">Relógio
                <select wire:model.live="relogio" class="rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-gray-900">
                    @foreach ($rotulos as $chave => $rotulo)
                        <option value="{{ $chave }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @php
            $titulos = ['prioridade' => 'Por prioridade', 'criticidade' => 'Por criticidade do equipamento', 'tipo' => 'Por tipo de OS', 'tecnico' => 'Por técnico', 'cliente' => 'Por cliente'];
        @endphp
        <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-2">
            @foreach ($titulos as $dim => $titulo)
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $titulo }}</h3>
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <th class="px-3 py-1.5 font-medium">{{ \Illuminate\Support\Str::after($titulo, 'Por ') }}</th>
                                    <th class="px-3 py-1.5 text-right font-medium">Concluídos</th>
                                    <th class="px-3 py-1.5 text-right font-medium">No prazo</th>
                                    <th class="px-3 py-1.5 text-right font-medium">Tempo médio</th>
                                    <th class="px-3 py-1.5 text-right font-medium">Vencidos agora</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->indicadores['dimensoes'][$dim] as $l)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-1.5 text-gray-800 dark:text-gray-200">{{ $l['rotulo'] }}</td>
                                        <td class="px-3 py-1.5 text-right text-gray-700 dark:text-gray-300">{{ $l['total'] }}</td>
                                        <td class="px-3 py-1.5 text-right font-semibold {{ ($l['pct'] ?? 100) >= 90 ? 'text-emerald-600 dark:text-emerald-400' : (($l['pct'] ?? 100) >= 70 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400') }}">{{ $l['pct'] !== null ? $l['pct'].'%' : '—' }}</td>
                                        <td class="px-3 py-1.5 text-right text-gray-700 dark:text-gray-300">{{ \App\Services\Sla\SlaService::formatarMinutos($l['tempo_medio']) }}</td>
                                        <td class="px-3 py-1.5 text-right {{ $l['vencidos'] > 0 ? 'font-semibold text-danger-600 dark:text-danger-400' : 'text-gray-500' }}">{{ $l['vencidos'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-3 py-4 text-center text-sm text-gray-500">Sem dados no período.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">O.S. com relógio aberto</h2>
        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach (['no_prazo' => ['No prazo', 'text-emerald-600 dark:text-emerald-400'], 'em_risco' => ['Em risco', 'text-warning-600 dark:text-warning-400'], 'vencido' => ['Vencido', 'text-danger-600 dark:text-danger-400'], 'pausado' => ['Pausado', 'text-gray-600 dark:text-gray-300']] as $chave => [$rotulo, $cor])
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <span class="block text-[10px] uppercase tracking-widest text-gray-400">{{ $rotulo }}</span>
                    <span class="mt-1 block text-2xl font-black {{ $cor }}">{{ $resumo['abertos'][$chave] ?? 0 }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 font-medium">OS</th>
                        <th class="px-4 py-2 font-medium">Ativo</th>
                        <th class="px-4 py-2 font-medium">Cliente</th>
                        <th class="px-4 py-2 font-medium">Técnico</th>
                        <th class="px-4 py-2 font-medium">Prioridade</th>
                        <th class="px-4 py-2 font-medium">Relógios</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->chamados as $linha)
                        @php $order = $linha['order']; @endphp
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2">
                                <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $order]) }}"
                                   class="font-medium text-primary-600 hover:underline dark:text-primary-400">OS #{{ $order->os_number }}</a>
                                <span class="block text-xs text-gray-500">{{ $order->maintenance_type }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $order->asset?->patrimonio ?? '—' }} — {{ $order->asset?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $order->client?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $order->technician?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ \App\Services\Prioridade\PrioridadeDaOs::opcoes((string) $order->tenant_id)[$order->priority] ?? $order->priority }}</td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($linha['relogios'] as $r)
                                        <span class="fi-badge inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $corStyles[$r['cor']] }}">
                                            {{ $r['label'] }}: {{ $r['rotulo'] }}{{ $r['restante'] !== null ? ' ('.$r['restante'].')' : '' }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Nenhuma O.S. com relógio de SLA aberto. Cadastre as políticas em Manutenção → Cadastros → Políticas de SLA.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
