<x-filament-panels::page>
    @php $d = $this->dados(); $campo = 'rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900 dark:text-white'; @endphp

    <div class="grid grid-cols-2 gap-3 md:grid-cols-6">
        <label class="text-xs font-semibold">De<input type="date" wire:model.live="de" class="{{ $campo }} mt-1 w-full"></label>
        <label class="text-xs font-semibold">Até<input type="date" wire:model.live="ate" class="{{ $campo }} mt-1 w-full"></label>
        <label class="text-xs font-semibold">Colaborador
            <select wire:model.live="colaborador" class="{{ $campo }} mt-1 w-full"><option value="">Todos</option>@foreach($this->colaboradores() as $id => $nome)<option value="{{ $id }}">{{ $nome }}</option>@endforeach</select>
        </label>
        <label class="text-xs font-semibold">Tipo
            <select wire:model.live="tipo" class="{{ $campo }} mt-1 w-full"><option value="">Todos</option>@foreach(\App\Models\Absence::typeLabels() as $v => $r)<option value="{{ $v }}">{{ $r }}</option>@endforeach</select>
        </label>
        <label class="text-xs font-semibold">Status
            <select wire:model.live="status" class="{{ $campo }} mt-1 w-full"><option value="">Todos</option>@foreach(\App\Models\Absence::statusLabels() as $v => $r)<option value="{{ $v }}">{{ $r }}</option>@endforeach</select>
        </label>
        <label class="text-xs font-semibold">CID<input type="text" wire:model.live.debounce.500ms="cid" placeholder="J11" class="{{ $campo }} mt-1 w-full uppercase"></label>
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
        @foreach([['Registros', $d['total']], ['Dias de ausência', $d['dias']], ['Atestados médicos', $d['medicos']], ['Atestado sem anexo', $d['semAnexo']], ['Aguardando análise', $d['pendentes']]] as [$rotulo, $valor])
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs text-gray-500">{{ $rotulo }}</div><div class="text-2xl font-bold">{{ $valor }}</div>
            </div>
        @endforeach
    </div>

    <div class="flex justify-end"><x-filament::button wire:click="exportar" icon="heroicon-o-arrow-down-tray" color="gray">Exportar CSV</x-filament::button></div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 lg:col-span-2">
            <h3 class="mb-2 text-sm font-semibold">Por colaborador (sem as rejeitadas)</h3>
            <table class="w-full text-sm"><thead><tr class="text-left text-xs text-gray-500"><th>Colaborador</th><th class="text-right">Registros</th><th class="text-right">Dias</th><th class="text-right">Última</th></tr></thead><tbody>
                @forelse($d['porColaborador'] as $c)<tr class="border-t border-gray-100 dark:border-white/5"><td class="py-1">{{ $c['nome'] }}</td><td class="text-right">{{ $c['registros'] }}</td><td class="text-right font-semibold">{{ $c['dias'] }}</td><td class="text-right">{{ $c['ultima']->format('d/m/Y') }}</td></tr>
                @empty<tr><td colspan="4" class="py-3 text-gray-500">Nada no período.</td></tr>@endforelse
            </tbody></table>
        </div>
        <div class="space-y-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="mb-2 text-sm font-semibold">Por CID</h3>
                @forelse($d['porCid'] as $c)<div class="flex justify-between border-t border-gray-100 py-1 text-sm dark:border-white/5"><span class="font-mono">{{ $c['cid'] }}</span><span>{{ $c['registros'] }} reg. · {{ $c['dias'] }} dias</span></div>@empty<p class="text-sm text-gray-500">Nenhum CID informado.</p>@endforelse
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="mb-2 text-sm font-semibold">Por mês</h3>
                @forelse($d['porMes'] as $m)<div class="flex justify-between border-t border-gray-100 py-1 text-sm dark:border-white/5"><span>{{ $m['mes'] }}</span><span>{{ $m['registros'] }} reg. · {{ $m['dias'] }} dias</span></div>@empty<p class="text-sm text-gray-500">Nada no período.</p>@endforelse
            </div>
        </div>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="mb-2 text-sm font-semibold">Histórico detalhado</h3>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-xs text-gray-500"><th>Colaborador</th><th>Tipo</th><th>Período</th><th class="text-right">Dias</th><th>CID</th><th>Motivo</th><th>Status</th></tr></thead><tbody>
            @foreach($d['registros'] as $a)<tr class="border-t border-gray-100 dark:border-white/5"><td class="py-1">{{ $a->employee?->name }}</td><td>{{ \App\Models\Absence::typeLabels()[$a->type] ?? $a->type }}</td><td>{{ $a->start_date->format('d/m/Y') }}@if(! $a->start_date->equalTo($a->end_date)) – {{ $a->end_date->format('d/m/Y') }}@endif</td><td class="text-right">{{ $a->days }}</td><td>{{ $a->cid_code ?: '—' }}</td><td>{{ $a->reason }}</td><td>{{ \App\Models\Absence::statusLabels()[$a->status] ?? $a->status }}</td></tr>@endforeach
        </tbody></table></div>
    </div>
</x-filament-panels::page>
