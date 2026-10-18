<?php

namespace App\Filament\Central\Pages;

use App\Services\OnboardingMetricas;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/** Métricas do onboarding por solução (só super admin): a medida que mostra se o guia ajuda o cliente a ver resultado no mesmo dia. */
class OnboardingMetricasPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Onboarding';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $title = 'Onboarding dos clientes';

    protected static ?string $slug = 'onboarding-metricas';

    protected static ?int $navigationSort = 88;

    protected static string $view = 'filament.central.pages.onboarding-metricas';

    public const PERIODOS = ['7' => 'Últimos 7 dias', '30' => 'Últimos 30 dias', '90' => 'Últimos 90 dias', '0' => 'Todo o período'];

    public string $periodo = '30';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    /** @return list<array<string, mixed>> */
    public function getMetricas(): array
    {
        $dias = (int) $this->periodo;

        return app(OnboardingMetricas::class)->porSolucao($dias > 0 ? Carbon::now()->subDays($dias) : null);
    }
}
