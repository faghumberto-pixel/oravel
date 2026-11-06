<x-filament-panels::page>
    @include('dashboards.styles')

    <form wire:submit.prevent>{{ $this->form }}</form>

    <div class="odb">
        @if (! $cabecalho)
            <p class="odb-lead">{{ $item ? 'Item não encontrado.' : 'Escolha um item acima para ver tudo o que aconteceu com ele: entradas, saídas, ajustes, uso em ordens de serviço e na frota, e as contagens físicas (com quem contou e quando).' }}</p>
        @else
            <div class="odb-grid">
                <section class="odb-w" style="--span: 12">
                    <div class="odb-wh"><h3>{{ $cabecalho['nome'] }}<small>{{ $cabecalho['tipo_rotulo'] }}@if ($cabecalho['sku']) · {{ $cabecalho['sku'] }}@endif</small></h3></div>
                    <div style="display:flex;gap:28px;flex-wrap:wrap;font-size:13px">
                        @foreach ($cabecalho['detalhes'] as $rotulo => $valor)
                            <div><span style="opacity:.65">{{ $rotulo }}</span><br><strong>{{ $valor }}</strong></div>
                        @endforeach
                    </div>
                </section>

                @foreach ([['Entradas', $totais['entradas'], 'ok'], ['Saídas', $totais['saidas'], 'warn'], ['Registros de uso', $totais['usos'], 'n'], ['Contagens físicas', $totais['contagens'], 'a']] as [$rotulo, $valor, $tom])
                    <section class="odb-w" style="--span: 3">
                        <div class="odb-wh"><h3>{{ $rotulo }}</h3></div>
                        <div class="odb-kpi">{{ rtrim(rtrim(number_format((float) $valor, 3, ',', '.'), '0'), ',') ?: '0' }}</div>
                    </section>
                @endforeach

                <section class="odb-w" style="--span: 12">
                    <div class="odb-wh"><h3>Linha do tempo do item<small>do mais recente ao mais antigo (até 60 registros)</small></h3></div>
                    @include('dashboards.types.timeline', ['p' => $timeline])
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
