@props(['i'])
@php
    $cat = \App\Domain\Intelligence\Support\Catalogo::indicadores()[$i['chave']] ?? null;
    $ruim = $cat['ruim'] ?? 0; $bom = $cat['bom'] ?? 100;
    $pos = $bom == $ruim ? 0 : max(0, min(100, ($i['valor'] - $ruim) / ($bom - $ruim) * 100));
    $cor = \App\Domain\Intelligence\Support\Graficos::corDaNotaPublica($i['nota']);
    $f = fn ($v) => number_format($v, abs($v) >= 100 ? 0 : 1, ',', '.');
@endphp
<div>
    <div style="display:flex;justify-content:space-between;gap:8px;font-size:.78rem">
        <span style="font-weight:600">{{ $i['titulo'] }}</span>
        <span><b style="color:{{ $cor }}">{{ $f($i['valor']) }} {{ $i['unidade'] }}</b> <span style="opacity:.6">· meta {{ $f($bom) }}</span></span>
    </div>
    <div style="position:relative;height:10px;border-radius:999px;margin:6px 0 2px;background:linear-gradient(90deg,#dc2626 0%,#d97706 40%,#2563eb 70%,#16a34a 100%);opacity:.9">
        <span style="position:absolute;top:-4px;left:calc({{ $pos }}% - 9px);width:18px;height:18px;border-radius:50%;background:#fff;border:4px solid {{ $cor }};box-shadow:0 1px 4px rgba(0,0,0,.35)"></span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.64rem;opacity:.55"><span>{{ $f($ruim) }} {{ $i['unidade'] }} (crítico)</span><span>{{ $f($bom) }} {{ $i['unidade'] }} (ideal)</span></div>
</div>
