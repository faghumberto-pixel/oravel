<?php

namespace App\Services;

use App\Filament\Resources\MaterialRequestResource;
use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\MaterialLocationStock;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Ponto unico de escrita de estoque por filial -- substitui as chamadas
 * diretas a StockMovement::record() que existiam antes (GoodsReceiptItemObserver,
 * MaterialConsumptionService, MaterialStockTake), agora todas passando por
 * aqui pra manter material_location_stock, o ledger e o cache
 * Material.current_stock sempre consistentes entre si.
 */
class MaterialStockService
{
    public function receive(
        Material $material,
        InternalUnit $unit,
        float $quantity,
        ?Model $reference = null,
        ?string $userId = null,
        ?string $documentReference = null,
    ): MaterialLocationStock {
        return DB::transaction(function () use ($material, $unit, $quantity, $reference, $userId, $documentReference) {
            $stock = $this->findOrCreateStock($material, $unit);
            $stock->increment('current_quantity', (int) round($quantity));
            $stock->refresh();

            StockMovement::record(
                $material,
                StockMovement::TYPE_ENTRADA_COMPRA,
                $quantity,
                (float) $stock->current_quantity,
                $reference,
                $userId,
                toLocationId: $unit->id,
                documentReference: $documentReference,
            );

            $material->recalculateCurrentStock();
            $this->notifyIfLowStock($stock);

            return $stock;
        });
    }

    public function consume(
        Material $material,
        InternalUnit $unit,
        float $quantity,
        ?Model $reference = null,
        ?string $userId = null,
    ): MaterialLocationStock {
        return DB::transaction(function () use ($material, $unit, $quantity, $reference, $userId) {
            $stock = $this->findOrCreateStock($material, $unit);
            $stock->decrement('current_quantity', (int) round($quantity));
            $stock->refresh();

            StockMovement::record(
                $material,
                StockMovement::TYPE_SAIDA_CONSUMO,
                $quantity,
                (float) $stock->current_quantity,
                $reference,
                $userId,
                fromLocationId: $unit->id,
            );

            $material->recalculateCurrentStock();
            $this->notifyIfLowStock($stock);

            return $stock;
        });
    }

    public function transfer(
        Material $material,
        InternalUnit $from,
        InternalUnit $to,
        float $quantity,
        ?string $reason = null,
        ?string $userId = null,
    ): void {
        DB::transaction(function () use ($material, $from, $to, $quantity, $reason, $userId) {
            $fromStock = $this->findOrCreateStock($material, $from);
            $fromStock->decrement('current_quantity', (int) round($quantity));
            $fromStock->refresh();

            $toStock = $this->findOrCreateStock($material, $to);
            $toStock->increment('current_quantity', (int) round($quantity));
            $toStock->refresh();

            StockMovement::record(
                $material,
                StockMovement::TYPE_TRANSFERENCIA,
                $quantity,
                (float) $toStock->current_quantity,
                null,
                $userId,
                fromLocationId: $from->id,
                toLocationId: $to->id,
                reason: $reason,
            );

            // current_stock (soma de todas as filiais) nao muda numa
            // transferencia -- so' redistribui entre duas linhas. Ainda
            // assim recalcula (barato, e' so' um SUM) pra manter o cache
            // honesto mesmo se alguma inconsistencia anterior existir.
            $material->recalculateCurrentStock();
            $this->notifyIfLowStock($fromStock);
        });
    }

    public function adjust(
        Material $material,
        InternalUnit $unit,
        float $newQuantity,
        ?string $userId = null,
        ?Model $reference = null,
    ): MaterialLocationStock {
        return DB::transaction(function () use ($material, $unit, $newQuantity, $userId, $reference) {
            $stock = $this->findOrCreateStock($material, $unit);
            $difference = $newQuantity - $stock->current_quantity;
            $stock->update(['current_quantity' => (int) round($newQuantity)]);

            StockMovement::record(
                $material,
                StockMovement::TYPE_AJUSTE_MANUAL,
                $difference,
                (float) $stock->current_quantity,
                $reference,
                $userId,
                toLocationId: $unit->id,
            );

            $material->recalculateCurrentStock();
            $this->notifyIfLowStock($stock);

            return $stock;
        });
    }

    /**
     * Cria a linha de estoque na filial na primeira vez que o material
     * movimenta la' -- limiares novos herdam Material.min_stock/max_stock
     * como padrao (editavel depois por filial). Se o Material ainda NAO
     * tem nenhuma linha de estoque em lugar nenhum (current_stock legado,
     * de antes de existir estoque por filial, e nunca migrado -- mesmo
     * caso do backfill em 2026_07_16_110500, mas pra Materials criados
     * DEPOIS daquela migration), a primeira linha nasce com esse saldo em
     * vez de 0, senao o proximo consumo derruba o saldo pro negativo.
     */
    private function findOrCreateStock(Material $material, InternalUnit $unit): MaterialLocationStock
    {
        $existing = MaterialLocationStock::where('material_id', $material->id)
            ->where('internal_unit_id', $unit->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $isFirstLocationEver = ! MaterialLocationStock::where('material_id', $material->id)->exists();

        return MaterialLocationStock::create([
            'tenant_id' => $material->tenant_id,
            'material_id' => $material->id,
            'internal_unit_id' => $unit->id,
            'current_quantity' => $isFirstLocationEver ? (int) $material->current_stock : 0,
            'minimum_threshold' => $material->min_stock ?? 0,
            'maximum_threshold' => $material->max_stock ?: null,
        ]);
    }

    /**
     * Ver EquipmentReplacementObserver::notifyRole() -- mesmo motivo: nao
     * usar User::role($nome) direto, Spatie resolve por nome globalmente
     * (ignora tenant_id).
     */
    private function notifyIfLowStock(MaterialLocationStock $stock): void
    {
        if (! $stock->isLowStock()) {
            return;
        }

        $role = Role::where('name', Material::ROLE_GESTOR_SUPRIMENTOS)
            ->where('guard_name', 'web')
            ->where('tenant_id', $stock->tenant_id)
            ->first();

        if (! $role) {
            return;
        }

        $material = $stock->material ?? Material::find($stock->material_id);
        $unit = $stock->internalUnit ?? InternalUnit::find($stock->internal_unit_id);

        $recipients = User::role($role)->where('tenant_id', $stock->tenant_id)->get();

        // Nao cria Pedido de Compra sozinho (material_requests nao tem de
        // onde tirar uma OS obrigatoria pra virar PartsRequest, e criar
        // silenciosamente sem revisao nao e' o combinado) -- so' oferece o
        // atalho, com a filial ja pre-preenchida no link.
        $createUrl = MaterialRequestResource::getUrl('create', [
            'requested_for_location_id' => $unit?->id,
        ]);

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Estoque abaixo do mínimo')
                ->body("\"{$material?->name}\" em {$unit?->name}: {$stock->current_quantity} unidade(s), mínimo {$stock->minimum_threshold}.")
                ->actions([
                    Action::make('criar_pedido')
                        ->label('Criar Pedido de Compra')
                        ->url($createUrl)
                        ->button(),
                ])
                ->warning()
                ->sendToDatabase($recipient);
        }
    }
}
