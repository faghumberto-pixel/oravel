<?php

namespace App\Filament\Central\Pages;

use App\Models\SalesLead;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

/**
 * Quadro por estagio do CRM Comercial, mesmo estilo visual do Kanban do
 * Patio (App\Filament\Pages\MaintenanceKanban) -- colunas por estagio,
 * card por lead. Movimentacao entre os 4 estagios abertos e' livre (select
 * no card, SalesLead::moveToStage()) -- pedido explicito do usuario
 * revertendo a trava original ("evitar avanco intuitivo"). Ganho/Perdido
 * continuam so' pelas acoes dedicadas em SalesLeadResource (Converter/
 * Marcar Perdido), que exigem dado real. O funil "de verdade" (visual
 * afunilado) fica em App\Filament\Central\Pages\FunilVendas.
 */
class Kanban extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Kanban';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $title = 'Kanban — CRM Comercial';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.central.pages.kanban';

    public function getColumns(): array
    {
        $stages = SalesLead::stageLabels();
        unset($stages[SalesLead::STAGE_PERDIDO]);

        return $stages;
    }

    public function getLeadsByStage(): Collection
    {
        return SalesLead::with(['assignedUser'])
            ->where('pipeline_stage', '!=', SalesLead::STAGE_PERDIDO)
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('pipeline_stage');
    }

    /**
     * Movimentacao livre (sem trava) entre os 4 estagios abertos -- ver
     * comentario em SalesLead::moveToStage().
     */
    public function moveToStage(string $leadId, string $stage): void
    {
        $lead = SalesLead::findOrFail($leadId);

        try {
            $lead->moveToStage($stage);
            Notification::make()->title('Estágio atualizado.')->success()->send();
        } catch (\RuntimeException $e) {
            Notification::make()->title('Não foi possível mover')->body($e->getMessage())->warning()->send();
        }
    }
}
