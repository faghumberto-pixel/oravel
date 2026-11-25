<?php

namespace App\Filament\Central\Resources\IntelligenceTestimonialResource\Pages;

use App\Filament\Central\Resources\IntelligenceTestimonialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIntelligenceTestimonials extends ListRecords
{
    protected static string $resource = IntelligenceTestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
