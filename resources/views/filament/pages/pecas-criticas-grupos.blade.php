<x-filament::page>
    @php
        $linhas = collect($this->linhas);
        $rotulos = \App\Services\Catalogo\PecasCriticas::ROTULOS;
        $cores = ['sem_estoque' => 'danger', 'abaixo_minimo' => 'warning', 'sem_vinculo' => 'gray', 'ok' => 'success'];
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    @endphp

    @if ($linhas->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-300">Nenhuma peça crítica cadastrada. Vincule Materiais na aba "Operador e peças" de cada grupo do catálogo.</p>
        </x-filament::section>
    @else
        <x-filament::section description="O estoque vem do Material vinculado (o mesmo que a OS consome). Peças sem material vinculado não têm como ser acompanhadas.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-testid="pecas-criticas">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2 pr-4">Grupo</th>
                            <th class="py-2 pr-4">Peça</th>
                            <th class="py-2 pr-4">Material</th>
                            <th class="py-2 pr-4 text-right">Estoque</th>
                            <th class="py-2 pr-4 text-right">Mínimo</th>
                            <th class="py-2 pr-4 text-right">Ativos do grupo</th>
                            <th class="py-2">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($linhas as $l)
                            <tr wire:key="peca-{{ $l['peca']->id }}">
                                <td class="py-2 pr-4">{{ $l['grupo']->name }}</td>
                                <td class="py-2 pr-4">{{ $l['peca']->descricao }}</td>
                                <td class="py-2 pr-4">{{ $l['material']?->name ?? '—' }}</td>
                                <td class="py-2 pr-4 text-right">{{ $l['estoque'] === null ? '—' : $fmt($l['estoque']) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $l['minimo'] === null ? '—' : $fmt($l['minimo']) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $l['ativos'] }}</td>
                                <td class="py-2"><x-filament::badge :color="$cores[$l['estado']]">{{ $rotulos[$l['estado']] }}</x-filament::badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament::page>
