<?php

namespace App\Filament\Resources\EquipmentDamageResource\Pages;

use App\Filament\Resources\EquipmentDamageResource;
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
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
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
        ];
    }
}
