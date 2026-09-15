<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Filament\Resources\ContractResource\Widgets\ContractAIAnalysisWidget;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected static string $view = 'filament.resources.contract-resource.pages.view-contract';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Contrato: ' . ($this->record->contract_number ?? 'N/A');
    }

    protected function getFooterWidgets(): array
    {
        return [
            ContractAIAnalysisWidget::make([
                'contract' => $this->record,
            ]),
        ];
    }
}
