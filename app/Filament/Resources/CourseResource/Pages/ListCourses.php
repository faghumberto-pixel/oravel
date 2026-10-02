<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseResource;
use App\Services\AcademyPoints;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCourses extends ListRecords
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        $points = auth()->user() ? app(AcademyPoints::class)->total(auth()->user()) : 0;

        return [
            Action::make('equipe')
                ->label('Participação da equipe')
                ->icon('heroicon-o-chart-bar')
                ->visible(fn () => (bool) auth()->user()?->isAdmin() && auth()->user()->tenant_id)
                ->url(CourseResource::getUrl('equipe')),
            Action::make('ranking')
                ->label('⭐ '.number_format($points, 0, ',', '.').' pontos · Ranking')
                ->color('gray')
                ->url(CourseResource::getUrl('ranking')),
        ];
    }
}
