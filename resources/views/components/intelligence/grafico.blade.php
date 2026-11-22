@props(['cfg', 'altura' => 260])

{{-- Gráfico Chart.js com o mesmo componente Alpine dos widgets do Filament (cores de texto/grade seguem o tema claro/escuro). --}}
<div style="position: relative; height: {{ $altura }}px;" {{ $attributes }}>
    <div
        @if (\Filament\Support\Facades\FilamentView::hasSpaMode()) x-load="visible" @else x-load @endif
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
        wire:ignore
        x-data="chart({ cachedData: @js($cfg['data']), options: @js($cfg['options']), type: @js($cfg['type']) })"
        style="height: 100%;"
    >
        <canvas x-ref="canvas" style="height: 100%;"></canvas>
        <span x-ref="backgroundColorElement" class="text-gray-100 dark:text-gray-800"></span>
        <span x-ref="borderColorElement" class="text-gray-400"></span>
        <span x-ref="gridColorElement" class="text-gray-200 dark:text-gray-800"></span>
        <span x-ref="textColorElement" class="text-gray-500 dark:text-gray-400"></span>
    </div>
    {{ $slot }}
</div>
