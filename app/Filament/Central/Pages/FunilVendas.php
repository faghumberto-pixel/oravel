<?php

namespace App\Filament\Central\Pages;

use App\Models\SalesLead;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

/**
 * Funil de vendas em quadro, mesmo estilo visual do Kanban do Patio
 * (App\Filament\Pages\MaintenanceKanban) -- colunas por estagio, card por
 * lead. Sem drag-and-drop de proposito: o funil so avanca pelas mesmas
 * acoes gated de SalesLeadResource (Avancar/Converter/Perder), nao por
 * arrastar livre -- e' literalmente o requisito de "evitar avanco
 * intuitivo" que motivou o design do model inteiro.
 */
class FunilVendas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Funil de Vendas';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $title = 'Funil de Vendas — CRM Comercial';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.central.pages.funil-vendas';

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
     * Sem parametro/form -- so' checa a mesma regra de
     * SalesLead::blockerForNextStage(). Converter/Marcar Perdido exigem
     * dado extra (plano+admin / motivo), entao ficam no detalhe do lead
     * (SalesLeadResource), pra onde o card do quadro linka.
     */
    public function advance(string $leadId): void
    {
        $lead = SalesLead::findOrFail($leadId);

        try {
            $lead->advanceStage();
            Notification::make()->title('Estágio avançado.')->success()->send();
        } catch (\RuntimeException $e) {
            Notification::make()->title('Não foi possível avançar')->body($e->getMessage())->warning()->send();
        }
    }
}
