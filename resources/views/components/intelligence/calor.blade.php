@props(['calor'])
<div style="overflow-x:auto">
    <table style="border-collapse:separate;border-spacing:4px;width:100%;font-size:.78rem">
        <thead><tr><th></th>@foreach ($calor['colunas'] as $c)<th style="font-weight:600;opacity:.7;padding:2px 6px">{{ $c }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($calor['linhas'] as $l)
                <tr>
                    <td style="white-space:nowrap;padding-right:10px;font-weight:600">{{ $l['rotulo'] }}</td>
                    @foreach ($l['notas'] as $n)
                        @php $cor = \App\Domain\Intelligence\Support\Graficos::corDaNotaPublica($n); @endphp
                        <td style="text-align:center;padding:9px 6px;border-radius:8px;font-weight:700;color:#fff;background:{{ $cor }};min-width:64px">{{ $n === null ? '—' : number_format($n, 0) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:.68rem;opacity:.75;margin-top:6px">
        <span><i style="background:#16a34a" class="swatch"></i> 80+ Excelente</span><span><i style="background:#2563eb" class="swatch"></i> 60+ Bom</span><span><i style="background:#d97706" class="swatch"></i> 35+ Atenção</span><span><i style="background:#dc2626" class="swatch"></i> abaixo de 35 Crítico</span>
    </div>
    <style>.swatch{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:4px}</style>
</div>
