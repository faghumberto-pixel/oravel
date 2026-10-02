<?php

namespace App\Filament\Central\Resources\CourseResource\Pages;

use App\Filament\Central\Resources\CourseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCourses extends ListRecords
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pontos')
                ->label('Participação dos clientes')
                ->icon('heroicon-o-star')
                ->color('gray')
                ->url(CourseResource::getUrl('pontos')),
            Actions\CreateAction::make(),
        ];
    }
}
