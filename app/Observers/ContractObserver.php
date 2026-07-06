<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\Contract;
use Illuminate\Support\Facades\Log;

class ContractObserver
{
    public function created(Contract $contract): void
    {
        Log::info('Oravel: Observer de Contrato disparado: '.$contract->contract_number);

        if ($contract->asset) {
            // Atualiza status e vinculação para o Kanban e Relatórios
            $contract->asset->update([
                'status' => 'locado',
                'client_id' => $contract->client_id,
                'latitude' => $contract->latitude_obra,
                'longitude' => $contract->longitude_obra,
            ]);

            Log::info('Oravel: Ativo '.$contract->asset->name.' mobilizado com sucesso.');
        }
    }

    public function updated(Contract $contract): void
    {
        // Se o contrato for inativado, faz a demobilização automática
        if ($contract->isDirty('is_active') && ! $contract->is_active) {
            if ($contract->asset) {
                $contract->asset->update([
                    'status' => 'disponivel',
                    'client_id' => null,
                ]);
            }
        }

        // Troca de ativo dentro do mesmo contrato (ex: processo de Troca de
        // Equipamento) -- libera o ativo antigo e trava o novo no cliente
        // do contrato, mesmo efeito de created()/deleted() acima.
        if ($contract->wasChanged('asset_id')) {
            $originalAssetId = $contract->getOriginal('asset_id');

            if ($originalAssetId) {
                Asset::whereKey($originalAssetId)->update([
                    'status' => 'disponivel',
                    'client_id' => null,
                ]);
            }

            if ($contract->asset) {
                $contract->asset->update([
                    'status' => 'locado',
                    'client_id' => $contract->client_id,
                    'latitude' => $contract->latitude_obra,
                    'longitude' => $contract->longitude_obra,
                ]);
            }
        }
    }

    public function deleted(Contract $contract): void
    {
        if ($contract->asset) {
            $contract->asset->update([
                'status' => 'disponivel',
                'client_id' => null,
            ]);
        }
    }
}
