<?php

namespace App\Observers;

use App\Services\DestinatariosAvisos;
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

    /**
     * Segura o equipamento = contrato Ativo e em vigor. É o que TRAVA o ativo
     * (Locado): Rascunho não trava nada nem aparece como contrato ativo.
     */
    private function holdsAsset(Contract $contract): bool
    {
        return $contract->status === 'Ativo' && $contract->is_active;
    }

    /**
     * Contrato em andamento, qualquer situação menos Encerrado. Serve só para
     * LIBERAR: antes de 14/10/2026 o formulário não tinha campo de situação e
     * todo contrato criado pela tela ficou em Rascunho, mas já tinha travado o
     * ativo -- esses contratos antigos continuam liberando o equipamento ao
     * serem trocados, encerrados ou excluídos.
     */
    private function isOpen(Contract $contract): bool
    {
        return $contract->status !== 'Encerrado' && $contract->is_active;
    }

    private function releaseAsset(?string $assetId, ?Contract $except = null): void
    {
        if (! $assetId) {
            return;
        }

        // Outro contrato Ativo no mesmo ativo continua segurando o equipamento.
        $outroAtivo = Contract::where('asset_id', $assetId)
            ->where('status', 'Ativo')
            ->where('is_active', true)
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->exists();

        if ($outroAtivo) {
            return;
        }

        Asset::find($assetId)?->update([
            'status' => 'disponivel',
            'client_id' => null,
        ]);
    }

    public function created(Contract $contract): void
    {
        Log::info('Oravel: Observer de Contrato disparado: '.$contract->contract_number);

        if ($contract->asset && $this->holdsAsset($contract)) {
            $this->syncAssetLocation($contract);

            Log::info('Oravel: Ativo '.$contract->asset->name.' mobilizado com sucesso.');
        }
    }

    public function updated(Contract $contract): void
    {
        $wasOpen = $contract->getOriginal('status') !== 'Encerrado' && (bool) $contract->getOriginal('is_active');
        $wasHeld = $contract->getOriginal('status') === 'Ativo' && (bool) $contract->getOriginal('is_active');
        $isOpen = $this->isOpen($contract);
        $holds = $this->holdsAsset($contract);

        if ($contract->wasChanged('asset_id')) {
            $oldAssetId = $contract->getOriginal('asset_id');
            $oldAsset = $oldAssetId ? Asset::find($oldAssetId) : null;

            // Contrato antigo ainda em Rascunho, mas que na prática segurava o
            // equipamento (ativo Locado para o mesmo cliente): a troca leva o
            // novo equipamento junto.
            $emVigorLegado = $wasOpen && ! $wasHeld
                && $oldAsset?->status === 'locado'
                && $oldAsset->client_id === $contract->client_id;

            if ($wasOpen) {
                $this->releaseAsset($oldAssetId, $contract);
            }

            if ($contract->asset && ($holds || $emVigorLegado)) {
                $this->syncAssetLocation($contract);
            }

            return;
        }

        if (($wasOpen && ! $isOpen) || ($wasHeld && ! $holds)) {
            // Encerrado (ou voltou a Rascunho): devolve o equipamento ao pátio.
            $this->releaseAsset($contract->asset_id, $contract);

            return;
        }

        if (! $wasHeld && $holds && $contract->asset) {
            // Rascunho virou Ativo, ou contrato encerrado foi reaberto.
            $this->syncAssetLocation($contract);

            return;
        }

        if ($holds && $contract->asset && $contract->wasChanged(self::LOCATION_FIELDS)) {
            $this->syncAssetLocation($contract);

            $this->notifyLocationChanged($contract);
        }
    }

    public function deleted(Contract $contract): void
    {
        if ($this->isOpen($contract)) {
            $this->releaseAsset($contract->asset_id, $contract);
        }
    }

    private function syncAssetLocation(Contract $contract): void
    {
        $location = $contract->resolvedLocation();

        // Relê o ativo: o que está em memória pode estar velho (ex.: liberado
        // por outro passo) e um update sem mudança aparente não gravaria nada.
        $asset = Asset::find($contract->asset_id);

        $asset?->update([
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
        $recipients = DestinatariosAvisos::para($contract->tenant_id, 'contrato_local_mudou');

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
