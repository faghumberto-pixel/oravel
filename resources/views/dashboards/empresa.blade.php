{{-- "Painel de Gestão": visão da empresa inteira. $resumo vem de App\Dashboards\VisaoEmpresa::resumo(). --}}
@include('dashboards.styles')

<div class="odb">
    {{-- Com uma área só não há resumo: o número principal já está na própria seção. --}}
    @if ($resumo['destaques'])
        <div class="odb-resumo-t">
            <h2>Resumo da empresa</h2>
            <span>O principal número de cada área que o seu contrato cobre</span>
        </div>

        <div class="odb-grid">
            @foreach ($resumo['destaques'] as $d)
                <section class="odb-w odb-dest" style="--span: {{ $d['span'] }};--tom: {{ $d['tom'] }}" data-block="{{ $d['bloco']['key'] }}">
                    <div class="odb-dest-l"><x-filament::icon :icon="$d['icon']" />{{ $d['label'] }}</div>
                    <h3>{{ $d['bloco']['title'] }}<small>{{ $d['bloco']['subtitle'] }}</small></h3>
                    @include('dashboards.types.'.$d['bloco']['payload']['type'], ['p' => $d['bloco']['payload'], 'span' => $d['span']])
                </section>
            @endforeach
        </div>

    @endif

    @foreach ($resumo['secoes'] as $sec)
        <div class="odb-sec" style="--tom: {{ $sec['tom'] }}" data-secao="{{ $sec['key'] }}">
            <div class="odb-sec-h">
                <span class="odb-sec-i"><x-filament::icon :icon="$sec['icon']" /></span>
                <h2>{{ $sec['label'] }}</h2>
                @if ($sec['link'])
                    <a class="odb-link" href="{{ $sec['link'] }}">Ver tudo →</a>
                @endif
            </div>
            @foreach (['kpis', 'graficos', 'listas'] as $grupo)
                @if ($sec[$grupo])
                    <div class="odb-grid odb-grupo">
                        @foreach ($sec[$grupo] as $bloco)
                            <section class="odb-w" style="--span: {{ $bloco['size'] }}" data-block="{{ $bloco['key'] }}">
                                <div class="odb-wh">
                                    <h3>{{ $bloco['title'] }}<small>{{ $bloco['subtitle'] }}</small></h3>
                                </div>
                                @include('dashboards.types.'.$bloco['payload']['type'], ['p' => $bloco['payload'], 'span' => $bloco['size']])
                            </section>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach
</div>
