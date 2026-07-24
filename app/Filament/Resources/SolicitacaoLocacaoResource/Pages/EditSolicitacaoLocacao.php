<?php

namespace App\Filament\Resources\SolicitacaoLocacaoResource\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\SolicitacaoLocacaoResource;
use App\Models\EquipmentMovement;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('rental_requests')]
class EditSolicitacaoLocacao extends EditRecord
{
    protected static string $resource = SolicitacaoLocacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('timeline')
                ->label('Linha do Tempo')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->url(fn () => SolicitacaoLocacaoResource::getUrl('timeline', ['record' => $this->record])),
            Actions\Action::make('despacho')
                ->label(fn () => $this->dispatchStatusLabel())
                ->icon('heroicon-o-clipboard-document-check')
                ->color(fn () => $this->dispatchStatusColor())
                ->visible(fn () => filled($this->record->asset_id))
                ->url(fn () => route('solicitacoes-locacao.despacho-mobile', ['solicitacaoLocacao' => $this->record]))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }

    private function currentMovement(): ?EquipmentMovement
    {
        return EquipmentMovement::where('solicitacao_locacao_id', $this->record->id)
            ->where('type', EquipmentMovement::TYPE_MOBILIZACAO)
            ->latest()
            ->first();
    }

    private function dispatchStatusLabel(): string
    {
        return match ($this->currentMovement()?->status) {
            EquipmentMovement::STATUS_AGUARDANDO_APROVACAO => 'Checklist: Aguardando OK do Pátio',
            EquipmentMovement::STATUS_CONCLUIDO => 'Checklist: Liberado para Saída',
            EquipmentMovement::STATUS_EM_ANDAMENTO, EquipmentMovement::STATUS_AGUARDANDO_VISTORIA => 'Checklist de Saída em Andamento',
            default => 'Iniciar Checklist de Saída',
        };
    }

    private function dispatchStatusColor(): string
    {
        return match ($this->currentMovement()?->status) {
            EquipmentMovement::STATUS_AGUARDANDO_APROVACAO => 'warning',
            EquipmentMovement::STATUS_CONCLUIDO => 'success',
            default => 'gray',
        };
    }
}
