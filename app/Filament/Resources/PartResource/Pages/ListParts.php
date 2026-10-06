<?php

namespace App\Filament\Resources\PartResource\Pages;

use App\Filament\Resources\PartResource;
use App\Services\Frota\CatalogoEstoqueFrota;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListParts extends ListRecords
{
    protected static string $resource = PartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('catalogo_frota')
                ->label('Criar itens da frota')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Criar itens-padrão da frota')
                ->modalDescription('Cria as categorias (peças, insumos e fluidos, pneus, segurança) e os itens que ainda não existem, com estoque mínimo sugerido. Nada do que você já cadastrou é alterado.')
                ->visible(fn () => Tenancy::current() !== null)
                ->action(function () {
                    $r = app(CatalogoEstoqueFrota::class)->garantir((string) Tenancy::current()->id);
                    Notification::make()->title("Criados: {$r['categorias']} categoria(s) e {$r['itens']} item(ns)")->success()->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
