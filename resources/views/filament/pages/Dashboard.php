<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\RadarUrgencia;
use App\Filament\Widgets\RadarOperacional;

class Dashboard extends BaseDashboard
{
    public static ?string $requiredFeature = 'painel_gestao';

    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Painel de Controle';
    protected static ?string $slug = 'painel-controle';

    public function getWidgets(): array
    {
        return [
            RadarUrgencia::class,
            RadarOperacional::class,
        ];
    }

    public function mount(): void
    {
        $user = auth()->user();
        $tenant = \Filament\Facades\Filament::getTenant();

        if ($user && ($user->isAdmin() || $user->hasRole('gestor'))) return;

        if (!$tenant || !$tenant->hasFeature(self::$requiredFeature)) {
            abort(403, 'Acesso restrito ao módulo de Painel de Gestão.');
        }
    }
}