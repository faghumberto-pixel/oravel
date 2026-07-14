<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\Contract;
use App\Models\EquipmentReplacement;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ContractObserver
{
    /**
     * Campos que definem "onde o equipamento esta instalado" -- mudar
     * qualquer um deles num contrato JA ativo dispara a notificacao de
     * mudanca de local (ver updated()); local_tipo/internal_unit_id cobrem
     * o caso sede_empresa, cep_obra/condicao_ambiente cobrem outro.
     */
    private const LOCATION_FIELDS = ['local_tipo', 'internal_unit_id', 'cep_obra', 'condicao_ambiente'];

    public function created(Contract $contract): void
    {
        Log::info('Oravel: Observer de Contrato disparado: '.$contract->contract_number);

        if ($contract->asset) {
            $this->syncAssetLocation($contract);

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
                $this->syncAssetLocation($contract);
            }
        } elseif ($contract->wasChanged(self::LOCATION_FIELDS) && $contract->asset) {
            // Mesmo ativo, so' o local mudou -- ressincroniza lat/lng e avisa
            // quem cuida da logistica que precisa conferir/atualizar o ativo
            // fisicamente (a trilha de "quem mudou o que" fica automatica via
            // Contract::LogsActivity, consultavel no relatorio de atividades).
            $this->syncAssetLocation($contract);

            if ($contract->getOriginal('status') === 'Ativo') {
                $this->notifyLocationChanged($contract);
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

    private function syncAssetLocation(Contract $contract): void
    {
        $location = $contract->resolvedLocation();

        $contract->asset->update([
            'status' => 'locado',
            'client_id' => $contract->client_id,
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
        ]);
    }

    /**
     * Ver EquipmentReplacementObserver::notifyRole() -- mesmo motivo: nao
     * usar User::role($roleName) direto, pois Spatie resolve por nome
     * globalmente (ignora tenant_id) e falha silenciosamente pra qualquer
     * tenant que nao seja o primeiro a ter um papel com aquele nome.
     */
    private function notifyLocationChanged(Contract $contract): void
    {
        $role = Role::where('name', EquipmentReplacement::ROLE_LOGISTICA)
            ->where('guard_name', 'web')
            ->where('tenant_id', $contract->tenant_id)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)
            ->where('tenant_id', $contract->tenant_id)
            ->get();

        $novoLocal = $contract->resolvedLocation()['label'] ?? 'local não resolvido';

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Local de instalação do contrato mudou')
                ->body("Contrato #{$contract->contract_number} — Ativo {$contract->asset?->name}: novo local declarado ({$novoLocal}). Confira e atualize o ativo se necessário.")
                ->warning()
                ->sendToDatabase($recipient);
        }
    }
}
