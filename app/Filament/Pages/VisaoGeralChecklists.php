<?php

namespace App\Filament\Pages;

use App\Models\AssetCategory;
use App\Models\CkExecution;
use App\Services\Checklists\ChecklistVisaoGeral;
use App\Support\CkVisaoGeralExport;
use App\Support\Tenancy;
use Filament\Actions\Action;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Visao geral dos checklists (Relatorios): cobertura por ativo, servicos com e sem checklist e corretivas x checklist. Tudo clicavel: cada
 * cartao aplica o filtro e cada ativo, OS ou checklist feito abre o destino. Somente leitura; acesso como o do relatorio de execucoes.
 */
class VisaoGeralChecklists extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static ?string $navigationGroup = 'Ativos';

    protected static ?string $navigationParentItem = 'Checklist';

    protected static ?string $navigationLabel = 'Visão geral de checklists';

    protected static ?string $title = 'Visão geral de checklists';

    protected static ?string $slug = 'visao-geral-checklists';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.visao-geral-checklists';

    #[Url(as: 'aba')]
    public string $aba = 'cobertura';

    public ?string $de = null;

    public ?string $ate = null;

    public ?string $grupo = null;

    public ?string $status = null;

    public int $dias = ChecklistVisaoGeral::DIAS_PADRAO;

    public ?string $checklist = null;

    public ?string $tipo = null;

    public ?string $diagnostico = null;

    /**
     * Itens do submenu "Checklist" (Ativos): Equipamentos, Servicos e Corretivas abrem esta pagina ja na aba certa.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $aba = fn (): string => (string) request()->query('aba', 'cobertura');
        $item = fn (string $rotulo, string $icone, int $ordem, string $nome) => NavigationItem::make($rotulo)
            ->group(static::getNavigationGroup())->parentItem('Checklist')->icon($icone)->sort($ordem)
            ->url(static::getUrl(['aba' => $nome]))
            ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteName()) && ($nome === 'cobertura' ? ! in_array($aba(), ['servicos', 'corretivas'], true) : $aba() === $nome))
            ->visible(static::canAccess());

        return [
            $item('Equipamentos', 'heroicon-o-wrench-screwdriver', 1, 'cobertura'),
            $item('Serviços', 'heroicon-o-clipboard-document-check', 2, 'servicos'),
            $item('Corretivas × checklist', 'heroicon-o-exclamation-triangle', 3, 'corretivas'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check() && Gate::allows('viewAny', CkExecution::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->de = now()->subDays(90)->toDateString();
        $this->ate = now()->toDateString();
    }

    /** @return array<string, mixed> */
    public function filtros(): array
    {
        return ['de' => $this->de, 'ate' => $this->ate, 'grupo' => $this->grupo, 'status' => $this->status, 'dias' => $this->dias, 'checklist' => $this->checklist, 'tipo' => $this->tipo];
    }

    public function servico(): ChecklistVisaoGeral
    {
        return app(ChecklistVisaoGeral::class);
    }

    /** @return array<string, string> */
    public function grupos(): array
    {
        $tenant = auth()->user()?->tenant_id ?? Tenancy::current()?->id;

        return AssetCategory::query()->where('tenant_id', $tenant)->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function irParaAba(string $aba): void
    {
        $this->aba = in_array($aba, ['cobertura', 'servicos', 'corretivas'], true) ? $aba : 'cobertura';
    }

    /** Clicar num cartao aplica o filtro da lista (clicar de novo limpa). */
    public function filtrarCobertura(?string $status): void
    {
        $this->aba = 'cobertura';
        $this->status = $this->status === $status ? null : $status;
        $this->resetPage('pCob');
    }

    public function filtrarServicos(?string $situacao): void
    {
        $this->aba = 'servicos';
        $this->checklist = $this->checklist === $situacao ? null : $situacao;
        $this->resetPage('pServ');
    }

    public function filtrarCorretivas(?string $diagnostico): void
    {
        $this->aba = 'corretivas';
        $this->diagnostico = $this->diagnostico === $diagnostico ? null : $diagnostico;
        $this->resetPage('pCor');
    }

    public function updated(string $propriedade): void
    {
        if (in_array($propriedade, ['de', 'ate', 'grupo', 'dias', 'tipo'], true)) {
            $this->resetPage('pCob');
            $this->resetPage('pServ');
            $this->resetPage('pCor');
        }
    }

    public function limparFiltros(): void
    {
        $this->status = $this->checklist = $this->tipo = $this->diagnostico = $this->grupo = null;
        $this->dias = ChecklistVisaoGeral::DIAS_PADRAO;
        $this->mount();
        $this->resetPage('pCob');
        $this->resetPage('pServ');
        $this->resetPage('pCor');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar')->label('Exportar Excel')->icon('heroicon-o-table-cells')->color('gray')->action(function () {
                $caminho = CkVisaoGeralExport::xlsx($this->aba, $this->filtros(), $this->servico());

                return response()->download($caminho, 'visao-geral-checklists-'.$this->aba.'-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
            }),
        ];
    }
}
