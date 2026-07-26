<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\QuoteExporter;
use App\Filament\Resources\QuoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQuotes extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(QuoteExporter::class),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            QuoteResource\Widgets\QuoteStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
