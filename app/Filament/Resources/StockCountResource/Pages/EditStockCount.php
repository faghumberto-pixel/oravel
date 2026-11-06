<?php

namespace App\Filament\Resources\StockCountResource\Pages;

use App\Filament\Resources\StockCountResource;
use App\Filament\Resources\StockCountResource\Widgets\ConferenciaDaContagem;
use App\Services\Estoque\ContagemFisica;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use RuntimeException;

/** Conferência: a contagem frente ao estoque do sistema, item a item, com quem contou e quando. */
class EditStockCount extends EditRecord
{
    protected static string $resource = StockCountResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ConferenciaDaContagem::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('celular')->label('Contar no celular')->icon('heroicon-o-device-phone-mobile')->color('gray')
                ->url(fn () => route('estoque.contagem-mobile', ['contagem' => $this->record->id]))->openUrlInNewTab()->visible(fn () => $this->record->aberta()),
            Actions\Action::make('finalizar')->label('Finalizar contagem e ajustar estoque')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn () => $this->record->aberta())->requiresConfirmation()
                ->modalHeading('Finalizar esta contagem?')
                ->modalDescription('Cada item contado com quantidade diferente do esperado vai gerar um ajuste de estoque (materiais e peças/óleo) e o endereço passa a valer a quantidade contada. Itens agregados ficam só registrados. Itens sem contagem são ignorados. Não dá para desfazer.')
                ->action(function () {
                    try {
                        $r = app(ContagemFisica::class)->finalizar($this->record, (string) auth()->id());
                    } catch (RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Contagem finalizada')->body($r['ajustados'].' ajuste(s) de estoque, '.$r['divergentes'].' divergência(s), '.$r['sem_contagem'].' item(ns) sem contagem.')->success()->send();
                    $this->redirect(StockCountResource::getUrl('edit', ['record' => $this->record]));
                }),
        ];
    }
}
