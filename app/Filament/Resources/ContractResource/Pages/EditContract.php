<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Filament\Resources\ContractResource\Widgets\ContractAIAnalysisWidget;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('timeline')
                ->label('📅 Timeline')
                ->url(fn () => route('filament.admin.resources.contracts.timeline', ['record' => $this->getRecord()]))
                ->button()
                ->color('primary'),
            Actions\DeleteAction::make(),
        ];
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
