<?php

namespace App\Filament\Central\Resources\SalesLeadResource\Pages;

use App\Filament\Central\Pages\DashboardCrm;
use App\Filament\Central\Resources\SalesLeadResource;
use App\Filament\Exports\SalesLeadExporter;
use App\Models\SalesLead;
use App\Support\CrmPalette;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * So o grid, sem widgets acima -- pedido do usuario 2026-08-19 ("separe o
 * grid dos graficos"). Os 10 widgets que ficavam aqui (cards + graficos +
 * mapas) foram pra App\Filament\Central\Pages\DashboardCrm, que agora reune
 * todos os graficos do modulo comercial num so lugar.
 */
class ListSalesLeads extends ListRecords
{
    protected static string $resource = SalesLeadResource::class;

    protected static string $view = 'filament.central.sales-leads-list';

    /** Cartoes clicaveis acima da lista (mesmo padrao de Prospects): chave => [rotulo, filtros]. */
    public function cards(): array
    {
        $abertos = [SalesLead::STAGE_GANHO, SalesLead::STAGE_PERDIDO];
        $defs = [['todos', 'Todos', [], SalesLead::query(), null]];
        foreach (SalesLead::stageLabels() as $stage => $label) {
            $defs[] = [$stage, $label, ['pipeline_stage' => ['value' => $stage]], SalesLead::where('pipeline_stage', $stage), $stage];
        }
        $defs[] = ['sem_contato', 'Sem contato há 3+ dias', ['sem_contato' => ['isActive' => true]],
            SalesLead::whereNotIn('pipeline_stage', $abertos)->where(fn ($w) => $w->whereNull('last_interaction_at')->orWhere('last_interaction_at', '<=', now()->subDays(3))), null];
        $defs[] = ['retorno_vencido', 'Retorno vencido', ['retorno_vencido' => ['isActive' => true]],
            SalesLead::whereNotIn('pipeline_stage', $abertos)->whereNotNull('next_followup_date')->whereDate('next_followup_date', '<=', now()), null];

        $atual = $this->filtrosAtivosNormalizados();
        $cards = [];
        foreach ($defs as [$chave, $rotulo, $estado, $query, $stage]) {
            $cards[] = [
                'chave' => $chave, 'rotulo' => $rotulo, 'filtros' => $estado, 'total' => $query->count(),
                'ativo' => $atual == $this->normalizar($estado),
                'paleta' => $stage ? CrmPalette::stage($stage) : null,
            ];
        }

        return $cards;
    }

    public function filtrarCard(string $chave): void
    {
        $card = collect($this->cards())->firstWhere('chave', $chave);
        $this->resetTableFiltersForm();
        $this->tableFilters = array_replace($this->tableFilters ?? [], $card['filtros'] ?? []);
        $this->resetPage();
    }

    private function filtrosAtivosNormalizados(): array
    {
        return $this->normalizar($this->tableFilters ?? []);
    }

    private function normalizar(array $f): array
    {
        $r = [];
        foreach (['pipeline_stage', 'segment', 'assigned_user_id'] as $k) {
            if (filled($f[$k]['value'] ?? null)) {
                $r[$k] = (string) $f[$k]['value'];
            }
        }
        foreach (['sem_contato', 'retorno_vencido'] as $k) {
            if (! empty($f[$k]['isActive'])) {
                $r[$k] = '1';
            }
        }

        return $r;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('dashboard')
                ->label('Ver Gráficos')
                ->icon('heroicon-o-presentation-chart-line')
                ->color('gray')
                ->url(fn () => DashboardCrm::getUrl()),
            Actions\ExportAction::make()->exporter(SalesLeadExporter::class),
            Actions\CreateAction::make(),
        ];
    }
}
