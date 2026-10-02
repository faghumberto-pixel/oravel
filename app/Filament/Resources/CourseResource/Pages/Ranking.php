<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseResource;
use App\Services\AcademyPoints;
use Filament\Resources\Pages\Page;

/**
 * Ranking de pontos da Academia dentro da empresa (so' o tenant do usuario logado).
 */
class Ranking extends Page
{
    protected static string $resource = CourseResource::class;

    protected static string $view = 'filament.academy.ranking';

    protected static ?string $title = 'Ranking da Academia';

    public function mount(): void
    {
        abort_unless(CourseResource::canViewAny(), 403);
    }

    public function ranking()
    {
        return app(AcademyPoints::class)->ranking(auth()->user());
    }
}
