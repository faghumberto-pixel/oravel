<?php

namespace App\Filament\Resources\EquipmentDamageResource\Pages;

use App\Filament\Resources\EquipmentDamageResource;
use App\Models\Asset;
use App\Models\EquipmentDamage;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEquipmentDamage extends ViewRecord
{
    protected static string $resource = EquipmentDamageResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Identificação')
                    ->schema([
                        Infolists\Components\TextEntry::make('maintenanceOrder.os_number')->label('OS'),
                        Infolists\Components\TextEntry::make('asset.patrimonio')->label('Patrimônio')->placeholder('—'),
                        Infolists\Components\TextEntry::make('asset.name')->label('Ativo'),
                        Infolists\Components\TextEntry::make('reportedBy.name')->label('Reportado por'),
                        Infolists\Components\TextEntry::make('created_at')->label('Data do registro')->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Diagnóstico do Técnico')
                    ->schema([
                        Infolists\Components\TextEntry::make('severity')
                            ->label('Severidade')
                            ->badge()
                            ->formatStateUsing(fn (string $state) => match ($state) {
                                EquipmentDamage::SEVERITY_LEVE => 'Leve',
                                EquipmentDamage::SEVERITY_MODERADA => 'Moderada',
                                EquipmentDamage::SEVERITY_GRAVE => 'Grave / Perda Total',
                                default => $state,
                            }),
                        Infolists\Components\IconEntry::make('requires_replacement')->label('Exige troca?')->boolean(),
                        Infolists\Components\TextEntry::make('description')->label('Descrição')->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Evidências Fotográficas')
                    ->schema([
                        Infolists\Components\ViewEntry::make('photos')
                            ->label('')
                            ->view('filament.infolists.equipment-damage-photos'),
                    ]),

                Infolists\Components\Section::make('Ciência do Cliente')
                    ->schema([
                        Infolists\Components\ViewEntry::make('client_signature')
                            ->label('Assinatura')
                            ->view('filament.infolists.signature-image'),
                        Infolists\Components\TextEntry::make('client_acknowledged_at')->label('Assinado em')->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->client_acknowledged_at !== null),

                Infolists\Components\Section::make('Revisão do Supervisor')
                    ->schema([
                        Infolists\Components\TextEntry::make('supervisor_notes')->label('Observações do supervisor')->columnSpanFull(),
                        Infolists\Components\TextEntry::make('supervisorReviewedBy.name')->label('Revisado por'),
                        Infolists\Components\TextEntry::make('supervisor_reviewed_at')->label('Revisado em')->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->supervisor_notes || $record->supervisor_reviewed_at),

                Infolists\Components\Section::make('Tratativa Comercial')
                    ->schema([
                        Infolists\Components\TextEntry::make('estimated_cost')
                            ->label('Valor estimado')
                            ->money('BRL'),
                        Infolists\Components\TextEntry::make('replacementAsset.patrimonio')
                            ->label('Patrimônio do substituto')
                            ->placeholder('Nenhum vinculado ainda'),
                        Infolists\Components\TextEntry::make('replacementAsset.name')
                            ->label('Ativo substituto vinculado')
                            ->placeholder('Nenhum vinculado ainda'),
                        Infolists\Components\TextEntry::make('commercialReviewedBy.name')->label('Tratado por'),
                        Infolists\Components\TextEntry::make('commercial_reviewed_at')->label('Em')->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->commercial_reviewed_at !== null || $record->replacement_asset_id !== null),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('baixar_laudo')
                ->label('Baixar Laudo Jurídico (PDF)')
                ->color('gray')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn () => route('equipment-damages.laudo.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('confirmar')
                ->label('Confirmar / Ajustar Diagnóstico')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn () => $this->record->status === EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\Select::make('severity')
                        ->label('Severidade')
                        ->options([
                            EquipmentDamage::SEVERITY_LEVE => 'Leve',
                            EquipmentDamage::SEVERITY_MODERADA => 'Moderada',
                            EquipmentDamage::SEVERITY_GRAVE => 'Grave / Perda Total',
                        ])
                        ->default(fn () => $this->record->severity)
                        ->required(),
                    Forms\Components\Toggle::make('requires_replacement')
                        ->label('Exige substituição do equipamento?')
                        ->default(fn () => $this->record->requires_replacement),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'severity' => $data['severity'],
                        'requires_replacement' => $data['requires_replacement'],
                        'status' => EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL,
                        'supervisor_reviewed_by' => auth()->id(),
                        'supervisor_reviewed_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Diagnóstico confirmado')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('pedir_info')
                ->label('Pedir Mais Informação')
                ->color('warning')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->visible(fn () => $this->record->status === EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\Textarea::make('note')
                        ->label('O que precisa ser esclarecido?')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update(['supervisor_notes' => $data['note']]);

                    if ($technician = $this->record->reportedBy) {
                        Notification::make()
                            ->title('Supervisor pediu mais informações sobre uma avaria')
                            ->body($data['note'])
                            ->warning()
                            ->sendToDatabase($technician);
                    }

                    Notification::make()
                        ->title('Pedido de informação enviado ao técnico')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('iniciar_cobranca')
                ->label('Iniciar Cobrança')
                ->color('success')
                ->icon('heroicon-o-currency-dollar')
                ->visible(fn () => $this->record->status === EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\TextInput::make('estimated_cost')
                        ->label('Valor estimado da cobrança (R$)')
                        ->numeric()
                        ->prefix('R$')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'estimated_cost' => $data['estimated_cost'],
                        'status' => EquipmentDamage::STATUS_EM_COBRANCA,
                        'commercial_reviewed_by' => auth()->id(),
                        'commercial_reviewed_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Cobrança iniciada')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('vincular_substituto')
                ->label('Vincular Ativo Substituto')
                ->color('info')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => $this->record->status === EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL
                    && $this->record->requires_replacement
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\Select::make('replacement_asset_id')
                        ->label('Ativo substituto')
                        ->options(fn () => Asset::query()
                            ->where('asset_category', $this->record->asset?->asset_category)
                            ->where('id', '!=', $this->record->asset_id)
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update(['replacement_asset_id' => $data['replacement_asset_id']]);

                    Notification::make()
                        ->title('Ativo substituto vinculado')
                        ->success()
                        ->send();
                }),

            // Ate 2026-07-14 resolvido/cancelado existiam como constante e
            // como opcao de filtro na listagem, mas nenhuma acao de tela
            // levava uma Avaria ate esses estados -- ficava sem fechamento
            // formal depois de em_cobranca.
            Actions\Action::make('marcar_resolvida')
                ->label('Marcar como Resolvida')
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->modalHeading('Marcar Avaria como Resolvida?')
                ->visible(fn () => $this->record->status === EquipmentDamage::STATUS_EM_COBRANCA
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\Textarea::make('note')
                        ->label('Observação final (opcional)'),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => EquipmentDamage::STATUS_RESOLVIDO,
                        'supervisor_notes' => $data['note'] ?: $this->record->supervisor_notes,
                    ]);

                    Notification::make()
                        ->title('Avaria marcada como resolvida')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('cancelar_avaria')
                ->label('Cancelar Avaria')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading('Cancelar esta Avaria?')
                ->modalDescription('Use quando a avaria reportada não procede (falso positivo).')
                ->visible(fn () => ! in_array($this->record->status, [EquipmentDamage::STATUS_RESOLVIDO, EquipmentDamage::STATUS_CANCELADO], true)
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo do cancelamento')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => EquipmentDamage::STATUS_CANCELADO,
                        'supervisor_notes' => $data['reason'],
                    ]);

                    Notification::make()
                        ->title('Avaria cancelada')
                        ->warning()
                        ->send();
                }),
        ];
    }
}
