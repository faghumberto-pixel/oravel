<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Contract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Wizard de Desmobilização" — técnico retorna equipamento do cliente ao pátio.
 * 5 etapas linear: identificar (scan QR), checklist com comparação vs mobilização,
 * fotos/horímetro com divergências, resumo, assinatura de retorno.
 */
#[Layout('layouts.checklist-mobile')]
class AssetDemobilizationWizard extends Component
{
    use WithFileUploads;

    public const TOTAL_STEPS = 5;

    public ?AssetMovement $movement = null;

    public ?AssetMovement $mobilizationRecord = null;

    public ?Asset $asset = null;

    public ?Contract $contract = null;

    #[Url]
    public int $step = 1;

    // --- Etapa 1: Identificação ---
    public ?string $qrCode = null;

    public ?string $assetSearch = null;

    /** @var array<int, array{id: string, name: string, patrimonio: ?string}> */
    public array $assetResults = [];

    public ?string $qrMensagem = null;

    // --- Etapa 2: Checklist com comparação ---
    public array $checklistItems = [];

    public array $checklistComparison = [];

    public string $expandedItemId = '';

    // --- Etapa 3: Estado físico com divergências ---
    public ?float $horimetroEnd = null;

    public string $fuelLevelEnd = 'half';

    public string $observations = '';

    public array $divergences = [];

    public $photoFront = null;

    public $photoBack = null;

    public $photoSide = null;

    // --- Etapa 4: Resumo de divergências ---
    // (apenas resumo, sem novos campos)

    // --- Etapa 5: Assinatura ---
    public ?string $signature = null;

    public string $saveState = 'idle';

    public function mount(?AssetMovement $movement = null): void
    {
        if ($movement) {
            Gate::authorize('update', $movement);
            $this->movement = $movement;
            $this->asset = $movement->asset;
            $this->contract = $movement->contract;

            // Carregar registro anterior de mobilização para comparação
            $this->mobilizationRecord = AssetMovement::query()
                ->where('asset_id', $movement->asset_id)
                ->where('movement_type', 'mobilization')
                ->where('completed_at', '<', $movement->created_at ?? now())
                ->latest('completed_at')
                ->first();

            if ($this->mobilizationRecord && ! empty($this->mobilizationRecord->checklist_items)) {
                $this->checklistComparison = $this->mobilizationRecord->checklist_items;
            }
        }
    }

    public function updatedStep(): void
    {
        $this->step = max(1, min(self::TOTAL_STEPS, (int) $this->step));
    }

    public function getTotalStepsProperty(): int
    {
        return self::TOTAL_STEPS;
    }

    public function getStepLabelProperty(): string
    {
        return match ($this->step) {
            1 => 'Identificar Equipamento',
            2 => 'Checklist com Comparação',
            3 => 'Estado Físico',
            4 => 'Resumo de Divergências',
            default => 'Assinatura de Retorno',
        };
    }

    public function getStepProgressProperty(): int
    {
        return (int) round($this->step / self::TOTAL_STEPS * 100);
    }

    /** Equipamentos do tenant que casam com o texto (sem diferenciar maiusculas). */
    /** Ativos que esta tela pode oferecer: do tenant e em locacao. */
    private function baseDosAtivos()
    {
        return Asset::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'em_locacao');
    }

    private function equipamentosDaBusca(string $termo)
    {
        $termo = mb_strtolower(trim($termo));

        return $this->baseDosAtivos()
            ->where(function ($q) use ($termo) {
                $q->whereRaw('LOWER(patrimonio) LIKE ?', ["%{$termo}%"])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$termo}%"]);
            })
            ->orderBy('name');
    }

    /**
     * Resultado da leitura do QR da etiqueta (ou de um codigo digitado): o link do dossie
     * (.../dossie-mobile/{id}) ou o patrimonio, tag ou placa do equipamento.
     */
    public function buscarPorCodigo(string $codigo): void
    {
        $codigo = trim($codigo);
        $this->qrMensagem = null;

        if ($codigo === '') {
            return;
        }

        $id = preg_match('#dossie-mobile/([0-9a-f-]{8,})#i', $codigo, $m) && Str::isUuid($m[1]) ? $m[1] : null;
        $termo = mb_strtolower($codigo);

        $achados = $this->baseDosAtivos()
            ->where(function ($q) use ($id, $termo) {
                if ($id) {
                    $q->orWhere('id', $id);
                }
                $q->orWhereRaw('LOWER(patrimonio) = ?', [$termo])
                    ->orWhereRaw('LOWER(tag) = ?', [$termo])
                    ->orWhereRaw('LOWER(placa) = ?', [$termo]);
            })
            ->limit(2)
            ->get();

        if ($achados->count() === 1) {
            $this->selecionarAtivo($achados->first());

            return;
        }

        if ($achados->count() > 1) {
            $this->assetSearch = $codigo;
            $this->updatedAssetSearch();

            return;
        }

        $this->qrMensagem = 'Nenhum equipamento em locação encontrado para esse QR.';
    }

    /**
     * Lista as opcoes enquanto o usuario digita (2+ letras). Antes a busca manual nao
     * tinha gatilho nenhum (searchAsset() nunca era chamado) e comparava LOWER(coluna)
     * com o texto digitado sem converter, entao patrimonio em maiusculas nunca casava.
     */
    public function updatedAssetSearch(): void
    {
        $termo = trim((string) $this->assetSearch);

        $this->assetResults = mb_strlen($termo) < 2
            ? []
            : $this->equipamentosDaBusca($termo)->limit(6)->get(['id', 'name', 'patrimonio'])->toArray();
    }

    public function selectAsset(string $assetId): void
    {
        $asset = $this->equipamentosDaBusca((string) $this->assetSearch)->whereKey($assetId)->first();

        if (! $asset) {
            return;
        }

        $this->selecionarAtivo($asset);
    }

    /** Mantido por compatibilidade: seleciona o primeiro resultado do texto atual. */
    public function searchAsset(): void
    {
        if (! $this->assetSearch) {
            return;
        }

        if ($asset = $this->equipamentosDaBusca($this->assetSearch)->first()) {
            $this->selecionarAtivo($asset);
        }
    }

    private function selecionarAtivo(Asset $asset): void
    {

        $this->asset = $asset;
        $this->assetSearch = null;
        $this->assetResults = [];

        // Carregar contrato ativo relacionado
        $contract = Contract::query()
            ->where('asset_id', $asset->id)
            ->where('status', 'ativo')
            ->latest('created_at')
            ->first();

        if ($contract) {
            $this->contract = $contract;
        }

        // Carregar mobilização anterior
        $this->mobilizationRecord = AssetMovement::query()
            ->where('asset_id', $asset->id)
            ->where('movement_type', 'mobilization')
            ->where('sync_status', '!=', 'pending')
            ->latest('completed_at')
            ->first();

        if ($this->mobilizationRecord && ! empty($this->mobilizationRecord->checklist_items)) {
            $this->checklistComparison = $this->mobilizationRecord->checklist_items;
            $this->checklistItems = $this->mobilizationRecord->checklist_items;
        }

    }

    public function confirmAsset(): void
    {
        if (! $this->asset || ! $this->contract) {
            $this->addError('asset', 'Equipamento ou contrato não encontrado.');

            return;
        }

        if (! $this->mobilizationRecord) {
            $this->addError('asset', 'Nenhum registro de mobilização anterior encontrado.');

            return;
        }

        $this->step = 2;
    }

    public function expandItem(string $itemId): void
    {
        $this->expandedItemId = $this->expandedItemId === $itemId ? '' : $itemId;
    }

    public function toggleItemState(string $itemId): void
    {
        if (! isset($this->checklistItems[$itemId])) {
            return;
        }

        $current = $this->checklistItems[$itemId]['state'] ?? 'present';
        $next = match ($current) {
            'present' => 'absent',
            'absent' => 'damaged',
            'damaged' => 'present',
            default => 'present',
        };

        $this->checklistItems[$itemId]['state'] = $next;

        // Detectar divergências: comparar com mobilização anterior
        $originalState = $this->checklistComparison[$itemId]['state'] ?? 'present';
        if ($next !== $originalState) {
            if (! isset($this->divergences[$itemId])) {
                $this->divergences[$itemId] = [];
            }
            $this->divergences[$itemId]['original_state'] = $originalState;
            $this->divergences[$itemId]['new_state'] = $next;
            $this->divergences[$itemId]['name'] = $this->checklistItems[$itemId]['name'];
        } else {
            unset($this->divergences[$itemId]);
        }
    }

    public function addChecklistItem(): void
    {
        $this->checklistItems[uniqid()] = [
            'name' => 'Novo item',
            'state' => 'present',
            'notes' => '',
        ];
    }

    public function removeChecklistItem(string $itemId): void
    {
        unset($this->checklistItems[$itemId]);
        unset($this->divergences[$itemId]);
    }

    public function next(): void
    {
        if (! $this->persistCurrentStep()) {
            return;
        }

        if ($this->step === 5) {
            $this->completeDemobilization();

            return;
        }

        $this->step = min(self::TOTAL_STEPS, $this->step + 1);
    }

    public function back(): void
    {
        if ($this->step > 1) {
            $this->step = $this->step - 1;

            return;
        }

        $this->redirectRoute('filament.admin.resources.maintenance-orders.index', navigate: false);
    }

    private function persistCurrentStep(): bool
    {
        $this->saveState = 'saving';

        try {
            match ($this->step) {
                1 => $this->persistIdentification(),
                2 => $this->persistChecklist(),
                3 => $this->persistPhysicalState(),
                4 => $this->persistDivergenceSummary(),
                5 => $this->persistSignature(),
                default => null,
            };
        } catch (ValidationException $e) {
            $this->saveState = 'idle';
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $this->saveState = 'error';

            return false;
        }

        $this->saveState = 'saved';

        return true;
    }

    private function persistIdentification(): void
    {
        if (! $this->asset || ! $this->contract) {
            throw ValidationException::withMessages([
                'asset' => 'Equipamento e contrato são obrigatórios.',
            ]);
        }
    }

    private function persistChecklist(): void
    {
        if (empty($this->checklistItems)) {
            throw ValidationException::withMessages([
                'checklist' => 'Adicione pelo menos um item à checklist.',
            ]);
        }
    }

    private function persistPhysicalState(): void
    {
        $this->validate([
            'horimetroEnd' => ['required', 'numeric', 'min:0'],
            'fuelLevelEnd' => ['required', 'in:empty,half,full'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'horimetroEnd' => 'horímetro final',
            'fuelLevelEnd' => 'nível de combustível',
        ]);

        // Calcular consumo de horímetro
        $horimetroStart = $this->mobilizationRecord?->horimeter_reading_start ?? 0;
        $consumption = $this->horimetroEnd - $horimetroStart;

        if ($consumption > 500) {
            $this->divergences['high_consumption'] = [
                'type' => 'high_consumption',
                'message' => "Consumo alto: {$consumption} horas",
                'start' => $horimetroStart,
                'end' => $this->horimetroEnd,
            ];
        }
    }

    private function persistDivergenceSummary(): void
    {
        // Etapa 4 é apenas resumo, sem persistência especial
    }

    private function persistSignature(): void
    {
        if (! $this->signature) {
            throw ValidationException::withMessages([
                'signature' => 'Assinatura é obrigatória para finalizar a desmobilização.',
            ]);
        }
    }

    private function completeDemobilization(): void
    {
        // Criar ou atualizar registro de desmobilização
        $data = [
            'tenant_id' => auth()->user()->tenant_id,
            'asset_id' => $this->asset->id,
            'contract_id' => $this->contract->id,
            'technician_id' => auth()->id(),
            'movement_type' => 'demobilization',
            'checklist_items' => $this->checklistItems,
            'horimeter_reading_end' => $this->horimetroEnd,
            'fuel_level_end' => $this->fuelLevelEnd,
            'observations' => $this->observations,
            'signature_data' => $this->signature,
            'divergences' => $this->divergences,
            'sync_status' => 'pending',
            'completed_at' => now(),
        ];

        if ($this->movement) {
            $this->movement->update($data);
        } else {
            AssetMovement::create($data);
        }

        // Revert asset status from "em_locacao" back to "disponivel"
        $this->asset->update(['status' => 'disponivel']);

        $this->redirectRoute(
            'filament.admin.resources.maintenance-orders.index',
            navigate: false
        );
    }

    public function clearSignature(): void
    {
        $this->signature = null;
    }

    public function render()
    {
        return view('livewire.asset-demobilization-wizard');
    }
}
