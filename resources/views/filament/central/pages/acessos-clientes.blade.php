<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $detail = $this->detail();
        $fmt = fn (int $s) => \App\Services\AccessAnalytics::duration($s);
        $tz = config('app.timezone');
        $onlineCount = $rows->where('online', true)->count();
    @endphp

    <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-end gap-4">
            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Cliente</span>
                <select wire:model.live="tenantId" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <option value="">Todos os clientes</option>
                    @foreach ($this->tenants() as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">
                <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Período</span>
                <select wire:model.live="period" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <option value="1">Hoje</option>
                    <option value="7">Últimos 7 dias</option>
                    <option value="30">Últimos 30 dias</option>
                    <option value="90">Últimos 90 dias</option>
                </select>
            </label>
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" wire:model.live="showStaff" class="rounded border-gray-300">
                Incluir acessos da equipe Oravel
            </label>
        </div>
        <p class="mt-3 text-sm font-semibold {{ $onlineCount ? 'text-green-600 dark:text-green-400' : 'text-gray-500' }}">
            ● {{ $onlineCount }} {{ $onlineCount === 1 ? 'usuário online agora' : 'usuários online agora' }}
            <span class="font-normal text-gray-500 dark:text-gray-400">(ativo nos últimos 5 minutos)</span>
        </p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Tempo <strong>estimado</strong> pela navegação: cada tela conta até a ação seguinte (no máximo {{ \App\Services\AccessAnalytics::IDLE_CAP_MINUTES }} min parado);
            a sessão termina após {{ \App\Services\AccessAnalytics::SESSION_GAP_MINUTES }} min sem atividade ou em um novo login.
        </p>
    </div>

    <div class="fi-section overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-2 text-left font-medium">Usuário</th>
                    <th class="px-4 py-2 text-left font-medium">Cliente</th>
                    <th class="px-4 py-2 text-left font-medium">Último acesso</th>
                    <th class="px-4 py-2 text-right font-medium">Logins</th>
                    <th class="px-4 py-2 text-right font-medium">Sessões</th>
                    <th class="px-4 py-2 text-right font-medium">Tempo total</th>
                    <th class="px-4 py-2 text-left font-medium">Tela com mais tempo</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($rows as $r)
                    @php $s = $r['summary']; @endphp
                    <tr class="{{ $this->selectedUserId === $r['user_id'] ? 'bg-primary-50 dark:bg-primary-500/10' : '' }}">
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2 font-medium text-gray-950 dark:text-white">
                                @if ($r['online'])
                                    <span class="relative flex h-2.5 w-2.5 shrink-0" title="Online agora">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-60"></span>
                                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
                                    </span>
                                @else
                                    <span class="inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-gray-300 dark:bg-gray-600" title="Offline"></span>
                                @endif
                                {{ $r['name'] }}
                                @if ($r['online'])
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-500/15 dark:text-green-400">Online</span>
                                @endif
                                @if ($r['staff'])
                                    <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">equipe Oravel</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">{{ $r['email'] }}</div>
                        </td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $r['tenant'] }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $s['last_at']?->timezone($tz)->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $s['logins'] }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $s['sessions'] }}</td>
                        <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ $fmt($s['seconds']) }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $s['top_screen'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            <button type="button" wire:click="select('{{ $r['user_id'] }}')" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                {{ $this->selectedUserId === $r['user_id'] ? 'Fechar' : 'Ver detalhes' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Nenhum acesso registrado neste período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($detail)
        <div class="space-y-3">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                Detalhe: {{ $rows->firstWhere('user_id', $this->selectedUserId)['name'] ?? '' }}
                <span class="text-sm font-normal text-gray-500">— {{ $detail['summary']['screens'] }} telas diferentes, {{ $fmt($detail['summary']['seconds']) }} no total</span>
            </h3>

            @if ($detail['summary']['by_screen'])
                <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Onde passou mais tempo</div>
                    @php $max = max(1, max($detail['summary']['by_screen'])); @endphp
                    @foreach (array_slice($detail['summary']['by_screen'], 0, 8, true) as $label => $sec)
                        <div class="mb-1 flex items-center gap-3 text-sm">
                            <div class="w-56 shrink-0 truncate text-gray-700 dark:text-gray-300">{{ $label }}</div>
                            <div class="h-2 flex-1 rounded-full bg-gray-100 dark:bg-gray-700">
                                <div class="h-2 rounded-full bg-primary-500" style="width: {{ round($sec / $max * 100) }}%"></div>
                            </div>
                            <div class="w-20 shrink-0 text-right tabular-nums text-gray-600 dark:text-gray-400">{{ $fmt($sec) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            @foreach ($detail['sessions'] as $i => $session)
                <div class="fi-section rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800" x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }">
                    <button type="button" x-on:click="open = !open" class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left text-sm">
                        <span class="font-medium text-gray-950 dark:text-white">
                            {{ $session['start']->timezone($tz)->format('d/m/Y H:i') }} → {{ $session['end']->timezone($tz)->format('H:i') }}
                        </span>
                        <span class="text-gray-500 dark:text-gray-400">{{ $fmt($session['seconds']) }} · {{ count($session['events']) }} ações</span>
                    </button>
                    <div x-show="open" x-cloak class="border-t border-gray-200 dark:border-gray-700">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($session['events'] as $e)
                                    <tr>
                                        <td class="w-20 px-4 py-1.5 tabular-nums text-gray-500">{{ $e['at']->timezone($tz)->format('H:i:s') }}</td>
                                        <td class="px-4 py-1.5 text-gray-800 dark:text-gray-200">{{ \App\Services\AccessAnalytics::describe($e) }}</td>
                                        <td class="w-24 px-4 py-1.5 text-right tabular-nums text-gray-500">{{ $e['action'] === 'view' ? $fmt($e['seconds']) : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
