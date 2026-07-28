<?php

namespace App\Filament\Central\Pages;

use App\Models\Client;
use App\Models\SalesLead;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Quadro por estagio do CRM Comercial, estilo Pipedrive (pedido explicito
 * do usuario 2026-07-28: cards com avatar do responsavel, valor total por
 * coluna, drag-and-drop suave via SortableJS -- ver resources/js/
 * central-kanban.js). Movimentacao entre os 4 estagios abertos e' livre
 * (arrastar o card OU o <select> de fallback acessivel, ambos chamam
 * moveToStage() abaixo) -- pedido explicito do usuario revertendo a trava
 * original ("evitar avanco intuitivo"). Ganho/Perdido continuam so' pelas
 * acoes dedicadas em SalesLeadResource (Converter/Marcar Perdido), que
 * exigem dado real -- a coluna Ganho no quadro e' so' leitura (nao aceita
 * soltar card, ver data-accepts-drop na view). O funil "de verdade" (visual
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

    /**
     * Edicao rapida de segmento/origem direto do card (input com sugestoes,
     * nao select fechado -- pedido explicito do usuario: "nao consigo
     * adicionar, apenas escolher"). O campo recebe o ROTULO digitado (o que
     * aparece no input), nao o slug interno -- resolveLabelToValue() traduz
     * de volta pro slug quando bate com um rotulo conhecido, ou slugifica o
     * texto novo como valor customizado.
     */
    public function updateSegment(string $leadId, string $typedLabel): void
    {
        SalesLead::findOrFail($leadId)->update([
            'segment' => $this->resolveLabelToValue($typedLabel, Client::nicheLabels()),
        ]);
    }

    public function updateSource(string $leadId, string $typedLabel): void
    {
        SalesLead::findOrFail($leadId)->update([
            'source' => $this->resolveLabelToValue($typedLabel, SalesLead::sourceLabels()),
        ]);
    }

    /**
     * @param  array<string, string>  $knownLabels  slug => rotulo
     */
    private function resolveLabelToValue(string $typedLabel, array $knownLabels): ?string
    {
        $typedLabel = trim($typedLabel);

        if ($typedLabel === '') {
            return null;
        }

        foreach ($knownLabels as $slug => $label) {
            if (mb_strtolower($label) === mb_strtolower($typedLabel)) {
                return $slug;
            }
        }

        // Valor novo, digitado pelo usuario -- vira slug (mesmo padrao dos
        // valores conhecidos), sem precisar cadastrar em lugar nenhum antes.
        return Str::slug($typedLabel, '_');
    }
}
