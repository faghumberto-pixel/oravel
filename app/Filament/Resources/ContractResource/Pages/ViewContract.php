<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Filament\Resources\ContractResource\Widgets\ContractAIAnalysisWidget;
use App\Models\Contract;
use Filament\Resources\Pages\Page;

class ViewContract extends Page
{
    protected static string $resource = ContractResource::class;

    protected static string $view = 'filament.resources.contract-resource.pages.view-contract';

    public ?Contract $record = null;

    public function mount(Contract $record): void
    {
        $this->record = $record;
    }

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
