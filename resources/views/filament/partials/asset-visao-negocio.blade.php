@php
    // Visao de negocio do equipamento (a mesma ideia da tela simples): quem e', onde esta, quanto vale e quanto custa.
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $rotulos = ['disponivel' => 'Disponível', 'locado' => 'Locado', 'manutencao' => 'Em manutenção', 'operando' => 'Operando', 'aguardando_triagem' => 'Aguardando triagem', 'quarentena' => 'Quarentena', 'reservado' => 'Reservado'];
    $cores = [
        'success' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300', 'warning' => 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        'danger' => 'bg-red-500/15 text-red-700 dark:text-red-300', 'info' => 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
        'primary' => 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300', 'purple' => 'bg-purple-500/15 text-purple-700 dark:text-purple-300',
        'gray' => 'bg-gray-500/15 text-gray-700 dark:text-gray-300',
    ];
    $statusRotulo = $rotulos[$ativo->status] ?? ucfirst(str_replace('_', ' ', (string) $ativo->status));
    $statusCor = $cores[\App\Models\Asset::statusColor((string) $ativo->status)] ?? $cores['gray'];
    $valorAtual = $ativo->acquisition_value ? (float) ($ativo->getDepreciationData()['current_value'] ?? $ativo->acquisition_value) : null;
    $foto = $ativo->getFirstMediaUrl('fotos', 'thumb') ?: null;
    $itens = [
        ['Cliente atual', $ativo->client?->name ?? '—'],
        ['Valor de aquisição', $ativo->acquisition_value ? $brl($ativo->acquisition_value) : '—'],
        ['Valor atual (após depreciação)', $valorAtual !== null ? $brl($valorAtual) : '—'],
        ['Horímetro', $ativo->horimetro_atual !== null ? number_format((float) $ativo->horimetro_atual, 0, ',', '.').' h' : '—'],
    ];
@endphp

<div class="space-y-4 text-sm">
    <div class="flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
        @if ($foto)
            <img src="{{ $foto }}" alt="" class="h-20 w-20 shrink-0 rounded-lg object-cover">
        @endif
        <div class="min-w-0 flex-1">
            <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $ativo->name }}</div>
            <div class="mt-1 flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                <span>Patrimônio: <b class="text-gray-950 dark:text-white">{{ $ativo->patrimonio ?: '—' }}</b></span>
                <span>Grupo: <b class="text-gray-950 dark:text-white">{{ $ativo->checklistGroup?->name ?? '—' }}</b></span>
                <span>Categoria: <b class="text-gray-950 dark:text-white">{{ $ativo->asset_category ?: '—' }}</b></span>
            </div>
            @if ($ativo->description)
                <p class="mt-2 text-gray-600 dark:text-gray-300">{{ $ativo->description }}</p>
            @endif
        </div>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusCor }}">{{ $statusRotulo }}</span>
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ($itens as [$rotulo, $valor])
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $rotulo }}</div>
                <div class="text-base font-semibold text-gray-950 dark:text-white">{{ $valor }}</div>
            </div>
        @endforeach
    </div>
</div>
