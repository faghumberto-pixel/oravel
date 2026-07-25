<?php

namespace App\Filament\Resources\CrmLeadResource\Widgets;

use App\Filament\Widgets\Charts\GaugeChart;
use App\Models\CrmLead;
use App\Support\Tenancy;

class ConversionRateGaugeWidget extends GaugeChart
{
    protected static bool $isLazy = false;

    /**
     * Era 190px (herdado de GaugeChart) -- padronizado em 220px junto com
     * os outros 3 gráficos da linha, pra as 4 caixas ficarem do mesmo
     * tamanho (pedido do usuário).
     */
    protected static ?string $maxHeight = '220px';

    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    public function mount(
        float $value = 0,
        ?float $target = null,
        ?string $chartTitle = null,
        ?array $bands = null,
        ?string $needleColor = null,
    ): void {
        $total = CrmLead::count();
        $convertidos = CrmLead::where('stage', CrmLead::STAGE_CONVERTIDO)->count();

        parent::mount(
            value: $total > 0 ? round(($convertidos / $total) * 100, 1) : 0.0,
            target: 30,
            chartTitle: 'Taxa de Conversão',
        );
    }
}
