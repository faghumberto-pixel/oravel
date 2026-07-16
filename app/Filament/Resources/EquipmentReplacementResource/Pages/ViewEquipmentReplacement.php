<?php

namespace App\Filament\Resources\EquipmentReplacementResource\Pages;

use App\Filament\Resources\EquipmentReplacementResource;
use App\Models\Asset;
use App\Models\EquipmentReplacement;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * O resto do workflow (avancar de desmobilizacao pra mobilizacao,
 * concluir a troca) acontece sozinho via EquipmentMovementObserver +
 * EquipmentReplacement::syncStatusFromMovements() quando as movimentacoes
 * sao concluidas no fluxo mobile ja existente -- aqui so' as 2 acoes que
 * precisam de decisao humana (qual substituto, quando iniciar).
 */
class ViewEquipmentReplacement extends ViewRecord
{
    protected static string $resource = EquipmentReplacementResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Identificação')
                ->schema([
                    Infolists\Components\TextEntry::make('originalAsset.patrimonio')->label('Patrimônio (Original)'),
                    Infolists\Components\TextEntry::make('originalAsset.name')->label('Ativo Original'),
                    Infolists\Components\TextEntry::make('replacementAsset.patrimonio')->label('Patrimônio (Substituto)')->placeholder('—'),
                    Infolists\Components\TextEntry::make('replacementAsset.name')->label('Ativo Substituto')->placeholder('— não identificado —'),
                    Infolists\Components\TextEntry::make('contract.id')->label('Contrato')->placeholder('— ativo próprio, sem locação —'),
                    Infolists\Components\TextEntry::make('requestedBy.name')->label('Solicitado por'),
                    Infolists\Components\TextEntry::make('urgency')
                        ->label('Urgência')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => ucfirst($state)),
                    Infolists\Components\TextEntry::make('reason')->label('Motivo')->columnSpanFull(),
                ])
                ->columns(2),

            Infolists\Components\Section::make('Linha do Tempo')
                ->schema([
                    Infolists\Components\TextEntry::make('created_at')->label('Solicitado em')->dateTime('d/m/Y H:i'),
                    Infolists\Components\TextEntry::make('identified_at')->label('Substituto identificado em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('desmobilizationMovement.completed_at')->label('Desmobilização concluída em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('delivered_at')->label('Substituto entregue em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('completed_at')->label('Troca concluída em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    Infolists\Components\IconEntry::make('client_signature')
                        ->label('Assinatura do cliente')
                        ->boolean()
                        ->getStateUsing(fn (EquipmentReplacement $record) => filled($record->client_signature)),
                ])
                ->columns(3),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('identificar_substituto')
                ->label('Identificar Substituto')
                ->icon('heroicon-o-magnifying-glass')
                ->color('info')
                ->visible(fn () => $this->record->status === EquipmentReplacement::STATUS_SOLICITADO
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\Select::make('replacement_asset_id')
                        ->label('Ativo Substituto')
                        ->options(fn () => Asset::query()
                            ->where('asset_category', $this->record->originalAsset?->asset_category)
                            ->where('id', '!=', $this->record->original_asset_id)
                            ->get()
                            ->mapWithKeys(fn (Asset $asset) => [$asset->id => "{$asset->patrimonio} — {$asset->name}"]))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->identifyReplacement(
                        Asset::find($data['replacement_asset_id']),
                        auth()->user()
                    );

                    Notification::make()->title('Substituto identificado')->success()->send();
                }),

            Actions\Action::make('iniciar_movimentacoes')
                ->label('Iniciar Movimentações')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Cria as movimentações de desmobilização (ativo original) e mobilização (substituto) para a Logística executar.')
                ->visible(fn () => $this->record->status === EquipmentReplacement::STATUS_SUBSTITUTO_IDENTIFICADO
                    && auth()->user()->can('update', $this->record))
                ->action(function () {
                    $this->record->startLogisticsMovements();

                    Notification::make()->title('Movimentações de troca criadas')->success()->send();
                }),

            Actions\Action::make('cancelar')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => ! in_array($this->record->status, [EquipmentReplacement::STATUS_CONCLUIDO, EquipmentReplacement::STATUS_CANCELADO], true)
                    && auth()->user()->can('update', $this->record))
                ->action(function () {
                    $this->record->update(['status' => EquipmentReplacement::STATUS_CANCELADO]);

                    Notification::make()->title('Troca cancelada')->warning()->send();
                }),
        ];
    }
}
