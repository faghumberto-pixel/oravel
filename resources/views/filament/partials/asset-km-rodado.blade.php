{{-- Km rodado do veículo (cadastro do ativo). Variável: $ativo --}}
@php
    $servico = app(\App\Services\Frota\KmRodadoService::class);
    $linhas = [];
    foreach (['mes', '90d', '12m', 'tudo'] as $chave) {
        [$de, $ate, $rotulo] = \App\Services\Frota\KmRodadoService::periodo($chave);
        $linhas[$rotulo] = $servico->porVeiculo($ativo, $de, $ate);
    }
    $percursos = $linhas['Desde o início']['por_percurso'];
@endphp
<div class="space-y-3 text-sm">
    <table class="w-full text-left">
        <thead><tr class="border-b font-semibold"><th class="py-1 pr-3">Período</th><th class="py-1 pr-3 text-right">Total rodado</th><th class="py-1 pr-3 text-right">Em percursos</th><th class="py-1 pr-3 text-right">Fora de percurso</th><th class="py-1 pr-3 text-right">Viagens</th><th class="py-1 text-right">Custo estimado</th></tr></thead>
        <tbody>
            @foreach ($linhas as $rotulo => $r)
                <tr class="border-b">
                    <td class="py-1 pr-3">{{ $rotulo }}</td>
                    <td class="py-1 pr-3 text-right font-bold">{{ number_format($r['total'], 0, ',', '.') }} km</td>
                    <td class="py-1 pr-3 text-right">{{ number_format($r['em_percursos'], 0, ',', '.') }} km</td>
                    <td class="py-1 pr-3 text-right">{{ number_format($r['fora_de_percurso'], 0, ',', '.') }} km</td>
                    <td class="py-1 pr-3 text-right">{{ $r['viagens'] }}</td>
                    <td class="py-1 text-right">{{ $r['custo'] !== null ? 'R$ '.number_format($r['custo'], 2, ',', '.') : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if ($percursos->isNotEmpty())
        <p class="font-semibold">Por percurso (desde o início)</p>
        @foreach ($percursos as $p)
            <p>{{ $p['nome'] }}: <strong>{{ number_format($p['km'], 0, ',', '.') }} km</strong> em {{ $p['viagens'] }} {{ $p['viagens'] === 1 ? 'viagem' : 'viagens' }}</p>
        @endforeach
    @endif
</div>
