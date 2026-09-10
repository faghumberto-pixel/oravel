<?php

namespace App\Filament\Resources\EpiDeliveryResource\Pages;

use App\Filament\Resources\EpiDeliveryResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Sem DeleteAction de proposito -- toda EpiDelivery ja consumiu estoque no
 * momento em que existe (afterCreate() chama consumeStockForDelivery()),
 * diferente de MaterialRequest (so' libera excluir em rascunho, antes de
 * qualquer efeito colateral) e GoodsReceipt (nunca expoe excluir, mesmo
 * motivo). Excluir aqui sem reverter o estoque deixaria o saldo
 * dessincronizado -- fechar o ciclo e' sempre via registerReturnAction()/
 * registerReplacementAction() no Resource, nunca exclusão.
 */
class EditEpiDelivery extends EditRecord
{
    protected static string $resource = EpiDeliveryResource::class;
}
