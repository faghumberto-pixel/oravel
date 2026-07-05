<?php

namespace App\Observers;

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
