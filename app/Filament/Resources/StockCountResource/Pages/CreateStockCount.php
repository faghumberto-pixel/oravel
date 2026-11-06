<?php

namespace App\Filament\Resources\StockCountResource\Pages;

use App\Filament\Resources\StockCountResource;
use App\Services\Estoque\ContagemFisica;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStockCount extends CreateRecord
{
    protected static string $resource = StockCountResource::class;

    /** A folha de contagem e' montada pelo servico (um item por endereco do escopo), nao pelo form. */
    protected function handleRecordCreation(array $data): Model
    {
        $contagem = app(ContagemFisica::class)->criar(
            (string) auth()->user()->tenant_id, (string) $data['internal_unit_id'], (string) $data['name'],
            $data['stock_group_id'] ?? null, $data['aisle'] ?? null, (string) auth()->id(),
        );
        if (filled($data['notes'] ?? null)) {
            $contagem->update(['notes' => $data['notes']]);
        }

        $n = $contagem->items()->count();
        Notification::make()->title($n > 0 ? "Folha de contagem criada com {$n} item(ns)" : 'Contagem criada, mas sem itens')
            ->body($n > 0 ? 'Quem for contar pode abrir o app no celular (botão "Contar no celular").' : 'Nenhum item está colocado em endereço neste escopo. Coloque os itens nos endereços primeiro.')
            ->{$n > 0 ? 'success' : 'warning'}()->send();

        return $contagem;
    }
}
