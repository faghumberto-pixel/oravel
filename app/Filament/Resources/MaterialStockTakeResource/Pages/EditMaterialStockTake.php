<?php

namespace App\Filament\Resources\MaterialStockTakeResource\Pages;

use App\Filament\Resources\MaterialStockTakeResource;
use App\Models\MaterialStockTake;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMaterialStockTake extends EditRecord
{
    protected static string $resource = MaterialStockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('finalizar')
                ->label('Finalizar Inventário')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Finalizar este inventário?')
                ->modalDescription('Todo item com quantidade contada diferente do saldo do sistema vai gerar um ajuste de estoque automaticamente. Itens sem contagem preenchida são ignorados.')
                ->visible(fn () => $this->record->status === MaterialStockTake::STATUS_RASCUNHO)
                ->action(function () {
                    $this->record->finalize();

                    Notification::make()
                        ->title('Inventário finalizado — ajustes de estoque aplicados')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === MaterialStockTake::STATUS_RASCUNHO),
        ];
    }
}
