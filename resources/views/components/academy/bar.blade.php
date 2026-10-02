@props(['percent' => 0, 'label' => null, 'height' => 8])
@php
    $p = max(0, min(100, (int) round($percent)));
    // verde >= 70, âmbar 40–69, vermelho < 40. Cores inline: não dependem do CSS compilado do painel.
    $color = $p >= 70 ? '#16a34a' : ($p >= 40 ? '#d97706' : '#dc2626');
@endphp
<div {{ $attributes->merge(['style' => 'min-width:110px']) }} data-percent="{{ $p }}">
    <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px;">
        <span style="color:#6b7280">{{ $label }}</span>
        <span style="font-weight:600;color:{{ $color }}">{{ $p }}%</span>
    </div>
    <div style="height:{{ $height }}px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
        <div style="height:{{ $height }}px;width:{{ $p }}%;background:{{ $color }};border-radius:999px;"></div>
    </div>
</div>
