@php
    $d = $this->dados();
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $rotulos = \App\Services\CustoManutencaoService::rotulosSerie();

    // Classes literais por cor (o Tailwind do painel so' gera o que aparece escrito aqui).
    $cartao = [
        'gray' => 'bg-gray-500/10 border-gray-500/30 text-gray-700 dark:text-gray-300',
        'red' => 'bg-red-500/15 border-red-500/40 text-red-700 dark:text-red-300',
        'amber' => 'bg-amber-500/15 border-amber-500/40 text-amber-700 dark:text-amber-300',
        'emerald' => 'bg-emerald-500/15 border-emerald-500/40 text-emerald-700 dark:text-emerald-300',
    ];
    $anel = ['gray' => 'ring-gray-400', 'red' => 'ring-red-400', 'amber' => 'ring-amber-400', 'emerald' => 'ring-emerald-400'];
    $borda = ['gray' => 'border-gray-400', 'red' => 'border-red-500', 'amber' => 'border-amber-500', 'emerald' => 'border-emerald-500'];
    $selo = [
        'gray' => 'bg-gray-500/15 text-gray-700 dark:text-gray-300',
        'red' => 'bg-red-500/15 text-red-700 dark:text-red-300',
        'amber' => 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        'emerald' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    ];
    $barra = ['gray' => 'bg-gray-400', 'red' => 'bg-red-500', 'amber' => 'bg-amber-500', 'emerald' => 'bg-emerald-500'];
    $statusNome = ['disponivel' => 'Disponível', 'locado' => 'Locado', 'manutencao' => 'Em manutenção', 'operando' => 'Em operação', 'aguardando_triagem' => 'Aguardando triagem', 'reservado' => 'Reservado', 'quarentena' => 'Quarentena'];
@endphp

<x-filament-panels::page>
    <div class="flex flex-col gap-y-6">

        {{-- Cards de totais (clicaveis): filtram a lista pela sinalizacao --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($d['cards'] as $c)
                <button type="button" wire:click="filtrarCard('{{ $c['chave'] }}')"
                    @class([
                        'rounded-xl border p-3 text-left transition focus:outline-none',
                        $cartao[$c['cor']],
                        'ring-2 '.$anel[$c['cor']] => $c['ativo'],
                    ])>
                    <div class="text-xs">{{ $c['rotulo'] }}</div>
                    <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $c['quantidade'] }}</div>
                    <div class="text-xs">{{ $brl($c['custo']) }} em manutenção no período</div>
                </button>
            @endforeach
        </div>

        {{-- Filtros --}}
        <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
            <div>
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Período</label>
                <select wire:model.live="meses" class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                    @foreach (\App\Filament\Pages\CustoPorEquipamento::PERIODOS as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Categoria</label>
                <select wire:model.live="categoria" class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                    <option value="">Todas</option>
                    @foreach ($this->categorias() as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Ordenar por</label>
                <select wire:model.live="ordem" class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                    @foreach (\App\Filament\Pages\CustoPorEquipamento::ORDENS as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Buscar equipamento</label>
                <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Nome, patrimônio ou tag"
                    class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
            </div>
        </div>

        {{-- Ranking --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Equipamento</th>
                        <th class="px-3 py-2 text-right">Valor de aquisição</th>
                        <th class="px-3 py-2 text-right">Custo no período</th>
                        <th class="px-3 py-2">Últimos 12 meses</th>
                        <th class="px-3 py-2 text-right">Acumulado</th>
                        <th class="px-3 py-2 text-right">% do valor</th>
                        <th class="px-3 py-2 text-right">Tendência</th>
                        <th class="px-3 py-2 text-right">OS</th>
                        <th class="px-3 py-2">Situação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($d['linhas'] as $i => $l)
                        @php $cor = $l['situacao']['cor']; $max = max(1, max($l['serie'])); @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="border-s-4 {{ $borda[$cor] }} px-3 py-2 text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-3 py-2">
                                <a href="{{ $l['url'] }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ $l['nome'] }}</a>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $l['patrimonio'] ?: 'sem patrimônio' }} · {{ $l['categoria'] ?: 'sem categoria' }} · {{ $statusNome[$l['status']] ?? $l['status'] }}</div>
                            </td>
                            <td class="px-3 py-2 text-right">{{ $l['valor'] > 0 ? $brl($l['valor']) : '—' }}</td>
                            <td class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">{{ $brl($l['custo_periodo']) }}<div class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $l['os_periodo'] }} OS</div></td>
                            <td class="px-3 py-2">
                                <div class="flex h-8 items-end gap-0.5">
                                    @foreach ($l['serie'] as $k => $v)
                                        <div class="w-1.5 rounded-sm {{ $v > 0 ? $barra[$cor] : 'bg-gray-300 dark:bg-white/10' }}" style="height: {{ $v > 0 ? max(10, round($v / $max * 100)) : 4 }}%" title="{{ $rotulos[$k] }}: {{ $brl($v) }}"></div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right">{{ $brl($l['custo_total']) }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ $l['razao'] !== null ? number_format($l['razao'] * 100, 0, ',', '.').'%' : '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                @if ($l['variacao'] === null)
                                    <span class="text-gray-400">—</span>
                                @elseif ($l['variacao'] > 0)
                                    <span class="text-red-600 dark:text-red-300">↑ {{ $l['variacao'] }}%</span>
                                @else
                                    <span class="text-emerald-600 dark:text-emerald-300">↓ {{ abs($l['variacao']) }}%</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">{{ $l['os_total'] }}@if ($l['retrabalho'])<div class="text-xs text-red-600 dark:text-red-300">{{ $l['retrabalho'] }} retrabalho</div>@endif</td>
                            <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $selo[$cor] }}">{{ $l['situacao']['rotulo'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">Nenhum equipamento encontrado com esses filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Custo = mão de obra + material + logística das ordens de serviço, no mês em que foram concluídas. O "% do valor" compara o custo acumulado com o valor de aquisição:
            revisar a partir de {{ number_format(config('custo_manutencao.limiar_revisar') * 100, 0) }}% e considerar trocar ou vender a partir de {{ number_format(config('custo_manutencao.limiar_trocar_vender') * 100, 0) }}%.
            A tendência compara os últimos {{ config('custo_manutencao.janela_tendencia_meses') }} meses com os {{ config('custo_manutencao.janela_tendencia_meses') }} anteriores.
        </p>
    </div>
</x-filament-panels::page>
