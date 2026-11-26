{{-- Gestão à Vista comercial: os blocos do dashboard de Vendas, na mesma grade dos demais dashboards. --}}
@include('dashboards.styles')

<div class="odb">
    <div class="odb-resumo-t">
        <h2>Indicadores de Resultados – Comercial</h2>
        <span>Metas, funil, propostas e visitas: o que move a venda, em uma tela</span>
    </div>

    <div class="odb-grid">
        @foreach ($blocks as $bloco)
            <section class="odb-w" style="--span: {{ $bloco['size'] }}" data-block="{{ $bloco['key'] }}">
                <div class="odb-wh">
                    <h3>{{ $bloco['title'] }}<small>{{ $bloco['subtitle'] }}</small></h3>
                </div>
                @include('dashboards.types.'.$bloco['payload']['type'], ['p' => $bloco['payload'], 'span' => $bloco['size']])
            </section>
        @endforeach
    </div>
</div>
