<?php

namespace App\Filament\Resources\AIAnalysisResource\Pages;

use App\Filament\Resources\AIAnalysisResource;
use App\Filament\Resources\AIAnalysisResource\Widgets\AIQuickLaunchWidget;
use Filament\Resources\Pages\ListRecords;

class ListAIAnalyses extends ListRecords
{
    protected static string $resource = AIAnalysisResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            AIQuickLaunchWidget::class,
        ];
    }
}
