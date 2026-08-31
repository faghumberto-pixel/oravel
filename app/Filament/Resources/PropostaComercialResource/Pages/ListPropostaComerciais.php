<?php

namespace App\Filament\Resources\PropostaComercialResource\Pages;

use App\Filament\Resources\PropostaComercialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * Até 2026-08-28 não tinha CreateAction de propósito -- a proposta só
 * nascia pelo wizard mobile do vendedor
 * (App\Livewire\PropostaComercialMobile). Filament não injeta o botão
 * "Criar" sozinho quando a rota 'create' existe -- ListRecords::
 * getHeaderActions() precisa declarar Actions\CreateAction::make()
 * explicitamente, senão a rota fica acessível só por URL direta, sem
 * nenhum link na tela. Agora existe também a criação pelo desktop (ver
 * CreatePropostaComercial), então o botão precisa aparecer.
 */
class ListPropostaComerciais extends ListRecords
{
    protected static string $resource = PropostaComercialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('imprimir_filtro_atual')
                ->label('Imprimir Todas (filtro atual)')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(function () {
                    $filtroStatus = $this->tableFilters['status']['value'] ?? null;

                    return route('proposta-comercial.print-batch', array_filter([
                        'status' => $filtroStatus,
                    ]));
                })
                ->openUrlInNewTab(),
            Actions\CreateAction::make()
                ->label('Nova Proposta Comercial'),
        ];
    }
}
