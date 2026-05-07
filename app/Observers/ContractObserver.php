<?php

namespace App\Observers;

use App\Models\Contract;
use Illuminate\Support\Facades\Log;

class ContractObserver
{
    public function created(Contract $contract): void
    {
        Log::info('Oravel: Observer de Contrato disparado para o contrato: ' . $contract->contract_number);

        if ($contract->asset) {
            $contract->asset->update([
                'status' => 'alocado',
                'client_id' => $contract->client_id,
                'latitude' => $contract->latitude_obra,
                'longitude' => $contract->longitude_obra,
            ]);
            Log::info('Oravel: Status do Ativo ' . $contract->asset->name . ' atualizado para alocado.');
        } else {
            Log::error('Oravel: Ativo não encontrado para o contrato ' . $contract->contract_number);
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
