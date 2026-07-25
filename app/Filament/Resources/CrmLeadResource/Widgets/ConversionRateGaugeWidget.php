<?php

namespace App\Filament\Resources\CrmLeadResource\Widgets;

use App\Filament\Widgets\Charts\GaugeChart;
use App\Models\CrmLead;
use App\Support\Tenancy;

class ConversionRateGaugeWidget extends GaugeChart
{
    protected static bool $isLazy = false;

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
