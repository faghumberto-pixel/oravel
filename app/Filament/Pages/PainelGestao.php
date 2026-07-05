<?php

namespace App\Filament\Pages;

use App\Filament\Attributes\BelongsToFeature;

use Filament\Pages\Page;
use App\Filament\Widgets\RadarOperacional;
use App\Filament\Widgets\RadarUrgencia;
use App\Filament\Widgets\ListaAlertaAtivos;
use App\Filament\Widgets\AgendaCampo;

#[BelongsToFeature('users')]
class PainelGestao extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static string $view = 'filament.pages.painel-gestao';
    protected static ?string $slug = 'painel-controle';
    protected static ?string $title = 'Painel de Controle';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int $navigationSort = -10;

    public $activeTab = 'gestao';

    public function selectTab($tab)
    {
        $this->activeTab = $tab;
    }

    // Retorna vazio para o Filament não injetar widgets automaticamente no topo
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}