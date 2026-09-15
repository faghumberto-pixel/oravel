<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Filament\Resources\ContractResource\Widgets\ContractAIAnalysisWidget;
use App\Models\Contract;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    public ?Contract $record = null;

    protected function getFooterWidgets(): array
    {
        return [
            ContractAIAnalysisWidget::make([
                'contract' => $this->record,
            ]),
        ];
    }
}
