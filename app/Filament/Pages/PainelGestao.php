<?php

namespace App\Filament\Pages;

use App\Dashboards\Personalizacao\DashboardResolver;
use App\Models\Asset;
use App\Models\Branch;
use App\Support\SegmentDashboardWidgets;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;

class PainelGestao extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.pages.painel-gestao';

    protected static ?string $slug = 'painel-controle';

    protected static ?string $title = 'Painel de Controle';

    protected static ?string $navigationLabel = 'Início';

    protected static ?int $navigationSort = -10;

    // "Início" agora fica fixo no topo da sidebar (vendor/filament-panels/
    // components/sidebar/index.blade.php), fora da área que rola.
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        // Empresa com Início personalizado (escolhido na Central): quem vê ao menos um bloco entra, sem depender do contrato do Painel de Controle.
        if ($user && DashboardResolver::visiveisPara($user, true)->isNotEmpty()) {
            return true;
        }

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

        // O contrato manda, inclusive para o administrador da empresa: se a Central tirou "Painel: Dashboard" do contrato, o painel
        // (e a aba Gestão à Vista, que mora nele) some para todos. Antes o administrador passava direto, sem olhar o contrato.
        // Super admin continua vendo (Tenant::hasFeature libera).
        return $tenant->hasFeature('modulo_dashboard');
    }

    /** Id do dashboard do Início personalizado que está aberto (aba). Vem do navegador: só vale se for um dos permitidos. */
    public ?string $painelInicio = null;

    public function selecionarPainel(string $id): void
    {
        $this->painelInicio = $id;
    }

    private function iniciosPersonalizados(): Collection
    {
        return DashboardResolver::visiveisPara(auth()->user(), true);
    }

    /** O Início padrão (Gestão à Vista e Painel de Controle) só sai de cena quando a Central marcou um dashboard como Início da empresa. */
    public function getView(): string
    {
        return $this->iniciosPersonalizados()->isNotEmpty() ? 'filament.pages.inicio-personalizado' : parent::getView();
    }

    protected function getViewData(): array
    {
        $paineis = $this->iniciosPersonalizados();
        if ($paineis->isEmpty()) {
            return parent::getViewData();
        }

        $atual = $paineis->firstWhere('id', $this->painelInicio) ?? $paineis->first();

        return [
            'paineis' => $paineis,
            'atual' => $atual,
            'blocks' => DashboardResolver::blocks($atual, auth()->user()),
        ];
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

    // Filtros globais da aba "Gestão à Vista" -- mesmo padrão de chave
    // from/until do Filter::make() de data range ja usado no projeto
    // (PreventiveMaintenanceExecutionResource/SiteVisitResource), aqui como
    // propriedades publicas Livewire em vez de filtro de Table. Default:
    // mes atual (pedido do prompt original).
    public ?string $gestaoFrom = null;

    public ?string $gestaoUntil = null;

    public ?string $gestaoBranchId = null;

    public ?string $gestaoAssetId = null;

    // Contador incrementado por atualizarDados() -- vira parte da wire:key
    // dos widgets da aba Gestao a Vista, forcando o Livewire a
    // reinstancia-los (e rechamar mount() com os filtros atuais) mesmo
    // quando nenhuma propriedade publica dos widgets filhos mudou sozinha.
    public int $gestaoRefreshTick = 0;

    public function mount(): void
    {
        $this->gestaoFrom = now()->startOfMonth()->toDateString();
        $this->gestaoUntil = now()->endOfMonth()->toDateString();
    }

    public function selectTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function atualizarDados(): void
    {
        $this->gestaoRefreshTick++;
    }

    /**
     * Filtros atuais da aba Gestão à Vista, no formato que
     * GestaoAVistaService/os widgets esperam.
     *
     * @return array{from: ?string, until: ?string, branchId: ?string, assetId: ?string}
     */
    public function getGestaoFiltros(): array
    {
        return [
            'from' => $this->gestaoFrom,
            'until' => $this->gestaoUntil,
            'branchId' => $this->gestaoBranchId,
            'assetId' => $this->gestaoAssetId,
        ];
    }

    /**
     * @return Collection<int, Branch>
     */
    public function getGestaoBranches(): Collection
    {
        return Branch::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Asset>
     */
    public function getGestaoAssets(): Collection
    {
        return Asset::query()->orderBy('name')->get(['id', 'name', 'patrimonio']);
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
