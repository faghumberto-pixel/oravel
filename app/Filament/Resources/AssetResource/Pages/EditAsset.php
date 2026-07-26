<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('printQr')
                ->label('Imprimir Etiqueta')
                ->icon('heroicon-o-printer')
                ->color('info')
                // A correção está aqui: enviando o tenant e o asset para a rota
                ->url(fn ($record): string => route('asset.print-qr', [
                    'tenant' => Tenancy::current()?->slug ?? $record->tenant_id,
                    'asset' => $record->id,
                ]))
                ->openUrlInNewTab(),

            // So aparece quando o Laudo de Recebimento achou avaria e travou
            // o ativo em quarentena (EquipmentPatioArrivalMobile::finalize()) --
            // liberacao e' sempre manual, nunca automatica.
            Actions\Action::make('liberar_quarentena')
                ->label('Liberar da Quarentena')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Confirma que a avaria encontrada no Laudo de Recebimento foi tratada e o ativo pode voltar a ficar disponível?')
                ->visible(fn () => $this->record->status === Asset::STATUS_QUARENTENA)
                ->form([
                    Forms\Components\Textarea::make('release_notes')
                        ->label('Observação da liberação (opcional)'),
                ])
                ->action(function (array $data) {
                    // patioArrivals() e' hasManyThrough(EquipmentMovement) -- ambas
                    // equipment_movements e equipment_patio_arrivals tem uma coluna
                    // completed_at, entao sem qualificar a tabela o Postgres rejeita
                    // por ambiguidade ("column reference completed_at is ambiguous").
                    $arrival = $this->record->patioArrivals()
                        ->whereNotNull('equipment_patio_arrivals.completed_at')
                        ->whereNull('equipment_patio_arrivals.quarantine_released_at')
                        ->latest('equipment_patio_arrivals.completed_at')
                        ->first();

                    $arrival?->update([
                        'quarantine_released_at' => now(),
                        'quarantine_released_by_user_id' => auth()->id(),
                        'initial_condition_notes' => trim(($arrival->initial_condition_notes ?: '')."\nLiberação: {$data['release_notes']}"),
                    ]);

                    $this->record->update(['status' => Asset::STATUS_DISPONIVEL]);

                    Notification::make()->title('Ativo liberado da quarentena')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
