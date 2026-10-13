<x-filament-panels::page>
    @php $d = $this->dados(); @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <label class="text-sm font-medium">Período
            <select wire:model.live="periodo" class="mt-1 block rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                @foreach ($d['periodos'] as $chave => $nome)<option value="{{ $chave }}">{{ $nome }}</option>@endforeach
            </select>
        </label>
        <a href="{{ route('km-rodado.print', ['periodo' => $periodo]) }}" target="_blank" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Imprimir</a>
    </div>

    <x-filament::section heading="Por veículo — {{ $d['rotulo'] }}" description="Total = histórico de odômetro (todo registro de km). Em percursos = km das viagens com percurso. Fora de percurso = o resto.">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b font-semibold"><th class="py-2 pr-4">Veículo</th><th class="py-2 pr-4 text-right">Total rodado</th><th class="py-2 pr-4 text-right">Em percursos</th><th class="py-2 pr-4 text-right">Fora de percurso</th><th class="py-2 pr-4 text-right">Viagens</th><th class="py-2 text-right">Custo estimado</th></tr></thead>
                <tbody>
                    @forelse ($d['veiculos'] as $v)
                        <tr class="border-b">
                            <td class="py-2 pr-4 font-medium">{{ $v['ativo']->placa ?? $v['ativo']->name }} <span class="text-xs text-gray-500">{{ $v['ativo']->name }}</span></td>
                            <td class="py-2 pr-4 text-right font-bold">{{ number_format($v['total'], 0, ',', '.') }} km</td>
                            <td class="py-2 pr-4 text-right">{{ number_format($v['em_percursos'], 0, ',', '.') }} km</td>
                            <td class="py-2 pr-4 text-right">{{ number_format($v['fora_de_percurso'], 0, ',', '.') }} km</td>
                            <td class="py-2 pr-4 text-right">{{ $v['viagens'] }}</td>
                            <td class="py-2 text-right">{{ $v['custo'] !== null ? 'R$ '.number_format($v['custo'], 2, ',', '.') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-center text-gray-500">Nenhum veículo cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Por percurso — {{ $d['rotulo'] }}" description="Cada viagem concluída soma o km real (odômetro da entrada − da saída) no percurso, nos dois sentidos.">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b font-semibold"><th class="py-2 pr-4">Percurso</th><th class="py-2 pr-4">Trajeto</th><th class="py-2 pr-4 text-right">Previsto</th><th class="py-2 pr-4 text-right">Viagens</th><th class="py-2 pr-4 text-right">Km rodado</th><th class="py-2 pr-4 text-right">Km médio</th><th class="py-2 text-right">Desvio</th></tr></thead>
                <tbody>
                    @forelse ($d['percursos'] as $l)
                        <tr class="border-b">
                            <td class="py-2 pr-4 font-medium">{{ $l['percurso']->nome }}</td>
                            <td class="py-2 pr-4">{{ $l['percurso']->trajeto() }}</td>
                            <td class="py-2 pr-4 text-right">{{ $l['percurso']->km_previsto ? number_format((float) $l['percurso']->km_previsto, 1, ',', '.').' km' : '—' }}</td>
                            <td class="py-2 pr-4 text-right">{{ $l['viagens'] }}</td>
                            <td class="py-2 pr-4 text-right font-bold">{{ number_format($l['km_total'], 0, ',', '.') }} km</td>
                            <td class="py-2 pr-4 text-right">{{ $l['km_medio'] !== null ? number_format($l['km_medio'], 1, ',', '.').' km' : '—' }}</td>
                            <td class="py-2 text-right">{{ $l['desvio_pct'] !== null ? (($l['desvio_pct'] > 0 ? '+' : '').number_format($l['desvio_pct'], 1, ',', '.').'%') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-4 text-center text-gray-500">Nenhum percurso cadastrado. Crie em Logística → Frota → Percursos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament::page>
