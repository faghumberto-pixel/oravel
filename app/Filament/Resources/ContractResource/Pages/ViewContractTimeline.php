<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Models\Contract;
use App\Services\ContractTimelineService;
use Filament\Resources\Pages\Page;

class ViewContractTimeline extends Page
{
    protected static string $resource = ContractResource::class;

    protected static string $view = 'filament.resources.contract-resource.pages.view-contract-timeline';

    #[\Livewire\Attributes\Computed]
    public Contract $contract;

    public array $timelineData = [];

    public function mount(Contract $record): void
    {
        $this->contract = $record;

        $service = new ContractTimelineService();
        $this->timelineData = $service->getTimelineData($this->contract);
    }

    public function getTitle(): string
    {
        return 'Timeline do Contrato #' . $this->contract->contract_number;
    }

    protected function getDefaultHeaderActions(): array
    {
        return [];
    }
}
