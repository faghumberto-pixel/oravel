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
        $user = auth()->user();

        // Tecnico puro (nao admin, nao supervisiona nenhum departamento) nao
        // precisa do dashboard de gestao -- mesmo criterio ja usado no
        // redirect de /dashboard (routes/web.php) pra mandar esse usuario
        // direto pra "Minhas Ordens de Servico" em vez do Painel de
        // Controle. Antes o redirect ja evitava isso no login, mas o item
        // "Dashboard" continuava visivel e clicavel no menu lateral.
        if ($user && ! $user->isAdmin() && empty($user->supervisedDepartmentIds())) {
            return false;
        }

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

    // Titulo grande "Painel de Controle" removido (pedido do usuario,
    // 2026-07-27) -- ja tem o mesmo texto, menor, dentro do cabecalho
    // escuro compacto logo abaixo (ver painel-gestao.blade.php). Retornar
    // vazio some com o bloco de heading inteiro (components/page/index.
    // blade.php so' renderiza <x-filament-panels::header> quando getHeading()
    // nao e' vazio), nao so' o texto.
    public function getHeading(): string
    {
        return '';
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
