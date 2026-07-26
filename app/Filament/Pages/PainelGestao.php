<?php

namespace App\Filament\Pages;

use App\Support\SegmentDashboardWidgets;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class PainelGestao extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string $view = 'filament.pages.painel-gestao';

    protected static ?string $slug = 'painel-controle';

    protected static ?string $title = 'Painel de Controle';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -10;

    public static function canAccess(): bool
    {
        $tenant = Tenancy::current();

        // Sem tenant (super admin sem "atuar como", contexto de console): nao
        // ha plano pra consultar, entao nao faz sentido esconder.
        if (! $tenant) {
            return true;
        }

        return $tenant->hasFeature('modulo_dashboard');
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public $activeTab = 'gestao';

    public function selectTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function getGestaoWidgets(): array
    {
        return SegmentDashboardWidgets::forSegment(Tenancy::current()?->segment);
    }

    // Retorna vazio para o Filament não injetar widgets automaticamente no topo
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
