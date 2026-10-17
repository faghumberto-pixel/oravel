<?php

namespace App\Filament\Central\Resources\ContratoResource\Pages;

use App\Filament\Central\Resources\ContratoResource;
use App\Support\Pacotes;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListContratos extends ListRecords
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('criarVitrines')
                ->label('Criar planos de vitrine dos pacotes')
                ->icon('heroicon-o-building-storefront')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Criar planos de vitrine')
                ->modalDescription('Cria um contrato de vitrine para cada pacote que ainda não tem (inativo e sem preço). Os que já existem não são alterados. Depois, edite cada um para definir o valor e ligar "À venda no site".')
                ->action(function () {
                    $criados = [];

                    foreach (array_keys(Pacotes::opcoes()) as $codigo) {
                        if (! Pacotes::vitrine($codigo)) {
                            Pacotes::planoDeVitrine($codigo);
                            $criados[] = Pacotes::nome($codigo);
                        }
                    }

                    Notification::make()
                        ->title($criados ? 'Planos criados: '.implode(', ', $criados) : 'Todos os pacotes já têm plano de vitrine.')
                        ->body($criados ? 'Eles estão inativos e sem preço. Filtre por "Planos de vitrine" para editá-los.' : null)
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()->label('Novo Contrato'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ContratoResource\Widgets\ContratoFunnelStats::class,
            ContratoResource\Widgets\ClientesPorPacoteStats::class,
        ];
    }
}
