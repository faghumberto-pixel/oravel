<x-filament-panels::page>
    @php
        $clients = $this->clients();
        $totals = $this->totals($clients);
        $monthly = $this->monthly();
        $daily = $this->daily();
        $detail = $this->detail();
        $publicTests = $this->publicTests();
        $maxDia = max(1, (int) $daily->max('acessos'));
        $nota = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', '');
        $box = 'fi-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800';
        $th = 'px-3 py-2 text-xs font-medium uppercase text-gray-500 dark:text-gray-400';
    @endphp

    <div class="{{ $box }}">
        <div class="flex flex-wrap items-end gap-4">
            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Cliente</span>
                <select wire:model.live="tenantId" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <option value="">Todos os clientes</option>
                    @foreach (\App\Models\Tenant::withoutGlobalScopes()->orderBy('name')->get(['id', 'name']) as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Período</span>
                <select wire:model.live="period" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                    @foreach (\App\Filament\Central\Pages\ParticipacaoTreinamento::PERIODOS as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            <strong>Acessos</strong> = telas da Academia abertas. <strong>Exercícios</strong> = perguntas respondidas (e quantas estavam certas).
            <strong>Provas</strong> = provas entregues, com acertos/total. <strong>Cursos</strong> = colaboradores que leram todas as aulas liberadas no contrato.
            <strong>Dias com acesso</strong> = soma, por colaborador, dos dias em que ele entrou na Academia. A equipe Oravel não entra na conta.
        </p>
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
        @foreach ([
            ['Clientes com participação', $totals['com_participacao'].' de '.$totals['clientes']],
            ['Acessos', number_format($totals['acessos'], 0, ',', '.')],
            ['Cursos concluídos', $totals['cursos']],
            ['Exercícios feitos', $totals['exercicios']],
            ['Provas entregues', $totals['provas']],
            ['Nota média das provas', $nota($totals['nota'])],
        ] as [$rotulo, $valor])
            <div class="{{ $box }}">
                <p class="text-xs uppercase text-gray-500 dark:text-gray-400">{{ $rotulo }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $valor }}</p>
            </div>
        @endforeach
    </div>

    <div class="{{ $box }} overflow-x-auto !p-0">
        <h3 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">Por cliente</h3>
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th class="{{ $th }} text-left">Cliente</th>
                    <th class="{{ $th }} text-right">Participantes</th>
                    <th class="{{ $th }} text-right">Acessos</th>
                    <th class="{{ $th }} text-right">Dias com acesso</th>
                    <th class="{{ $th }} text-right">Cursos</th>
                    <th class="{{ $th }} text-right">Aulas</th>
                    <th class="{{ $th }} text-right">Exercícios (certos)</th>
                    <th class="{{ $th }} text-right">Provas</th>
                    <th class="{{ $th }} text-right">Acertos nas provas</th>
                    <th class="{{ $th }} text-right">Nota</th>
                    <th class="{{ $th }} text-left">Última atividade</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($clients as $c)
                    <tr wire:click="select('{{ $c['tenant_id'] }}')" class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40 {{ $selectedTenantId === $c['tenant_id'] ? 'bg-primary-50 dark:bg-gray-700/60' : '' }}">
                        <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">
                            {{ $c['cliente'] }}
                            @if ($c['participantes'] === 0)
                                <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300">sem participação</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">{{ $c['participantes'] }} de {{ $c['colaboradores'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $c['acessos'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $c['dias_com_acesso'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $c['cursos'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $c['aulas'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $c['exercicios'] }} ({{ $c['exercicios_acertos'] }})</td>
                        <td class="px-3 py-2 text-right">{{ $c['provas'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $c['provas_total'] ? $c['provas_acertos'].' de '.$c['provas_total'] : '—' }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ $nota($c['nota']) }}</td>
                        <td class="px-3 py-2">{{ $c['ultimo']?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                    @if ($selectedTenantId === $c['tenant_id'] && $detail)
                        <tr>
                            <td colspan="11" class="bg-gray-50 px-4 py-3 dark:bg-gray-900/40">
                                <p class="mb-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Colaboradores de {{ $c['cliente'] }}</p>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr>
                                            <th class="{{ $th }} text-left">Colaborador</th>
                                            <th class="{{ $th }} text-right">Acessos</th>
                                            <th class="{{ $th }} text-right">Aulas</th>
                                            <th class="{{ $th }} text-right">Exercícios (certos)</th>
                                            <th class="{{ $th }} text-right">Provas</th>
                                            <th class="{{ $th }} text-right">Acertos nas provas</th>
                                            <th class="{{ $th }} text-left">Último acesso</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @forelse ($detail['users'] as $u)
                                            <tr>
                                                <td class="px-3 py-1.5">{{ $u['nome'] }} <span class="text-xs text-gray-500">{{ $u['email'] }}</span></td>
                                                <td class="px-3 py-1.5 text-right">{{ $u['acessos'] }}</td>
                                                <td class="px-3 py-1.5 text-right">{{ $u['aulas'] }}</td>
                                                <td class="px-3 py-1.5 text-right">{{ $u['exercicios'] }} ({{ $u['exercicios_acertos'] }})</td>
                                                <td class="px-3 py-1.5 text-right">{{ $u['provas'] }}</td>
                                                <td class="px-3 py-1.5 text-right">{{ $u['provas_total'] ? $u['provas_acertos'].' de '.$u['provas_total'] : '—' }}</td>
                                                <td class="px-3 py-1.5">{{ $u['ultimo']?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="px-3 py-2 text-gray-500">Nenhum colaborador cadastrado.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="11" class="px-4 py-6 text-center text-gray-500">Nenhum cliente.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="px-4 pb-3 pt-2 text-xs text-gray-500 dark:text-gray-400">Clique num cliente para ver os colaboradores e, abaixo, os acessos dia a dia só dele.</p>
    </div>

    <div class="{{ $box }} overflow-x-auto !p-0">
        <h3 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">Acessos mês a mês <span class="text-xs font-normal text-gray-500">(últimos 12 meses; acessos / pessoas diferentes)</span></h3>
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th class="{{ $th }} text-left">Cliente</th>
                    @foreach ($monthly['months'] as $m)
                        <th class="{{ $th }} text-right">{{ $m['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($monthly['rows'] as $row)
                    <tr>
                        <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">{{ $row['cliente'] }}</td>
                        @foreach ($monthly['months'] as $m)
                            @php $cel = $row['meses'][$m['key']]; @endphp
                            <td class="px-3 py-2 text-right {{ $cel['acessos'] ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">
                                {{ $cel['acessos'] ? $cel['acessos'].' / '.$cel['pessoas'] : '·' }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="bg-gray-50 font-semibold dark:bg-gray-900/40">
                    <td class="px-3 py-2">Total</td>
                    @foreach ($monthly['months'] as $m)
                        <td class="px-3 py-2 text-right">{{ collect($monthly['rows'])->sum(fn ($r) => $r['meses'][$m['key']]['acessos']) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    <div class="{{ $box }}">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
            Acessos dia a dia <span class="text-xs font-normal text-gray-500">(últimos 30 dias — {{ $selectedTenantId ? 'cliente selecionado' : ($tenantId ? 'cliente filtrado' : 'todos os clientes') }})</span>
        </h3>
        <div class="mt-4 flex items-end gap-1 overflow-x-auto" style="height: 140px;">
            @foreach ($daily as $d)
                <div class="flex h-full min-w-[14px] flex-1 flex-col items-center justify-end" title="{{ $d['label'] }}: {{ $d['acessos'] }} acessos, {{ $d['pessoas'] }} pessoa(s)">
                    <span class="text-[10px] text-gray-500">{{ $d['acessos'] ?: '' }}</span>
                    <div class="w-full rounded-t bg-orange-500" style="height: {{ $d['acessos'] ? max(4, (int) round($d['acessos'] / $maxDia * 100)) : 2 }}%; {{ $d['acessos'] ? '' : 'opacity:.25;' }}"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex gap-1 overflow-x-auto">
            @foreach ($daily as $i => $d)
                <span class="min-w-[14px] flex-1 text-center text-[9px] text-gray-500">{{ $i % 3 === 0 ? $d['label'] : '' }}</span>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Total no período: {{ $daily->sum('acessos') }} acessos · dias com acesso: {{ $daily->where('acessos', '>', 0)->count() }} de 30.</p>
    </div>

    <div class="{{ $box }} overflow-x-auto !p-0">
        <h3 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">Teste público de treinamento, por empresa</h3>
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th class="{{ $th }} text-left">Empresa</th>
                    <th class="{{ $th }} text-right">Pessoas</th>
                    <th class="{{ $th }} text-right">Tentativas</th>
                    <th class="{{ $th }} text-right">Aprovados</th>
                    <th class="{{ $th }} text-right">Acertos</th>
                    <th class="{{ $th }} text-right">Melhor nota</th>
                    <th class="{{ $th }} text-left">Último envio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($publicTests as $t)
                    <tr>
                        <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">
                            {{ $t['empresa'] }}
                            @unless ($t['cadastrado'])
                                <span class="ml-1 text-xs font-normal text-gray-500">(não encontrada entre os clientes)</span>
                            @endunless
                        </td>
                        <td class="px-3 py-2 text-right">{{ $t['pessoas'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $t['tentativas'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $t['aprovados'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $t['acertos'] }} de {{ $t['total'] }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ $nota($t['melhor_nota']) }}</td>
                        <td class="px-3 py-2">{{ $t['ultimo']?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Ninguém enviou o teste neste período.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="px-4 pb-3 pt-2 text-xs text-gray-500 dark:text-gray-400">As respostas individuais ficam em Gestão SaaS → Resultados de Treinamento.</p>
    </div>
</x-filament-panels::page>
