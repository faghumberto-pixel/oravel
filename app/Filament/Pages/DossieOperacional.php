<?php

namespace App\Filament\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Models\Asset;
use App\Models\Contract;
use App\Models\MaintenanceOrder;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

#[BelongsToFeature('maintenance')]
class DossieOperacional extends Page
{
    protected static ?string $slug = 'maintenance-orders/{record}/dossie';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.dossie-operacional';

    public MaintenanceOrder $record;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public function mount(MaintenanceOrder $record): void
    {
        $user = auth()->user();
        abort_unless($user && $user->can('view', $record), 403);

        $this->record = $record->load(['asset.contracts.internalUnit', 'asset.internalUnit', 'client', 'technician', 'evidences', 'equipmentMovements.locations']);
    }

    public function getTitle(): string
    {
        return 'Dossiê Operacional — OS #'.$this->record->os_number;
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function isOperationCheckout(): bool
    {
        return $this->record->maintenance_type === MaintenanceOrder::TYPE_CHECKOUT;
    }

    public function getOperationLabel(): string
    {
        return $this->isOperationCheckout()
            ? 'Check-out (Desmobilização)'
            : 'Check-in (Mobilização)';
    }

    /**
     * Comparativo Antes/Depois (locadoras de construcao civil, evidencia
     * pra cobranca/contestacao com o cliente da obra) -- os 2 slots fixos
     * ('antes'/'depois', singleton) ja existiam nas evidencias da OS, so'
     * nunca tinham uma visualizacao lado a lado dedicada. Destaca quando
     * alguma evidencia adicional (severity === 'mau_uso') foi registrada
     * junto, ver StoresPhotoEvidence::persistPhotoEvidences().
     */
    public function getAntesDepoisProperty(): array
    {
        return [
            'antes' => $this->record->evidences->firstWhere('evidence_type', 'antes'),
            'depois' => $this->record->evidences->firstWhere('evidence_type', 'depois'),
        ];
    }

    public function getMauUsoEvidencesProperty()
    {
        return $this->record->evidences->where('severity', 'mau_uso');
    }

    public function getEvidencesByCategory()
    {
        return $this->record->evidences->groupBy(function ($evidence) {
            if (filled($evidence->category)) {
                return $evidence->category;
            }

            return match ($evidence->evidence_type) {
                'antes' => 'Estado Inicial',
                'depois' => 'Estado Final',
                default => 'Evidência',
            };
        });
    }

    public function isFullyAudited(): bool
    {
        return $this->record->evidences->isNotEmpty()
            && $this->record->evidences->every(fn ($evidence) => filled($evidence->latitude) && filled($evidence->longitude));
    }

    /**
     * Locado -> local resolvido do contrato vigente (Contract::resolvedLocation());
     * senao -> unidade/filial base do ativo (Asset.internal_unit_id).
     * Mesma logica usada no Placeholder de MaintenanceOrderResource e na
     * aba Localização de AssetResource -- so' que aqui como array pro
     * blade decidir a apresentacao.
     */
    public function getLocalizacaoAtual(): ?array
    {
        $asset = $this->record->asset;

        if (! $asset) {
            return null;
        }

        if ($asset->status === Asset::STATUS_LOCADO) {
            $location = $asset->activeContract()?->resolvedLocation();

            if (! $location) {
                return null;
            }

            $condicao = $asset->activeContract()->condicao_ambiente;

            return [
                'origem' => 'Contrato (via localização)',
                'label' => $location['label'],
                'address' => $location['address'],
                'city' => $location['city'],
                'uf' => $location['uf'],
                'condicao' => $condicao ? (Contract::condicaoOptions()[$condicao] ?? $condicao) : null,
            ];
        }

        $unit = $asset->internalUnit;

        if (! $unit) {
            return null;
        }

        return [
            'origem' => 'Unidade Base',
            'label' => $unit->name,
            'address' => $unit->address,
            'city' => $unit->city,
            'uf' => $unit->state,
            'condicao' => null,
        ];
    }
}
