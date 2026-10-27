<?php

namespace App\Filament\Pages;

use App\Dashboards\Personalizacao\DashboardResolver;
use Filament\Pages\Page;

/** Dashboards personalizados que a Oravel atribuiu à empresa (um por aba). Só aparece se houver algum atribuído. */
class DashboardsDaEmpresa extends Page
{
    protected static string $view = 'filament.pages.dashboards-da-empresa';

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Dashboards';

    protected static ?string $title = 'Dashboards';

    protected static ?string $slug = 'dashboards';

    protected static ?int $navigationSort = -9;

    public ?string $painel = null;

    public static function canAccess(): bool
    {
        return DashboardResolver::visiveisPara(auth()->user())->isNotEmpty();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function selecionar(string $id): void
    {
        $this->painel = $id;
    }

    protected function getViewData(): array
    {
        $usuario = auth()->user();
        $paineis = DashboardResolver::visiveisPara($usuario);

        // O id vem do navegador: só vale se for de um dashboard atribuído e visível para este usuário.
        $atual = $paineis->firstWhere('id', $this->painel) ?? $paineis->first();

        return [
            'paineis' => $paineis,
            'atual' => $atual,
            'blocks' => $atual ? DashboardResolver::blocks($atual, $usuario) : [],
        ];
    }
}
