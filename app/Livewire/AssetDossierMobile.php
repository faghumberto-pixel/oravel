<?php

namespace App\Livewire;

use App\Filament\Concerns\HasAssetDossierData;
use App\Models\Asset;
use App\Models\HorimeterReading;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Versao pro campo/patio (tecnico no celular) do Dossie Rapido do Ativo --
 * mesma fonte de dados que a versao desktop (App\Filament\Pages\AssetDossier),
 * via HasAssetDossierData, mas visual proprio (tema escuro, toques grandes,
 * sem o shell do painel Filament). E o destino do QR code do ativo.
 *
 * Tambem acumula o registro de horimetro direto por aqui (2026-08):
 * ApontamentoHorimetro (pagina Filament) ja existia mas exige a permissao
 * granular 'criar_apontamento_horimetro', que tecnico normalmente nao tem
 * (so' e' concedida por admin via Perfis de Acesso). Em vez de depender
 * dessa permissao aqui, seguimos o mesmo padrao do proprio Modo Campo
 * (MaintenanceOrderFieldWizard): quem pode VER o ativo (Gate 'view', ja
 * checado no mount/selectResult) pode registrar um apontamento pra ele --
 * e' uma acao de campo, nao de gestao/auditoria como a pagina desktop.
 */
#[Layout('layouts.checklist-mobile')]
class AssetDossierMobile extends Component
{
    use HasAssetDossierData;
    use WithFileUploads;

    public ?Asset $asset = null;

    public string $query = '';

    /** @var array<int, array{id: string, name: string, patrimonio: ?string, tag: ?string}> */
    public array $searchResults = [];

    // --- Registro de horímetro ---
    public string $horimeterReading = '';

    public bool $horimeterResetConfirmed = false;

    public string $horimeterNotes = '';

    public $horimeterPhoto = null;

    public bool $horimeterSaved = false;

    public function mount(?string $assetId = null): void
    {
        if ($assetId) {
            $this->asset = Asset::find($assetId);
            if ($this->asset) {
                Gate::authorize('view', $this->asset);
            }
        }
    }

    public function search(): void
    {
        $this->validate(['query' => 'required|string']);
        $this->searchResults = [];

        $found = Asset::search($this->query);

        if ($found->isEmpty()) {
            $this->addError('query', 'Nenhum ativo encontrado. Tente outro termo.');

            return;
        }

        if ($found->count() === 1) {
            $this->selectResult($found->first()->id);

            return;
        }

        $this->searchResults = $found->map(fn (Asset $asset) => [
            'id' => $asset->id,
            'name' => $asset->name,
            'patrimonio' => $asset->patrimonio,
            'tag' => $asset->tag,
        ])->all();
    }

    public function selectResult(string $assetId): void
    {
        $asset = Asset::find($assetId);

        if (! $asset) {
            return;
        }

        Gate::authorize('view', $asset);

        $this->asset = $asset;
        $this->searchResults = [];
        $this->query = '';
        $this->resetHorimeterForm();
    }

    public function clear(): void
    {
        $this->asset = null;
        $this->searchResults = [];
        $this->query = '';
        $this->resetHorimeterForm();
    }

    private function resetHorimeterForm(): void
    {
        $this->horimeterReading = '';
        $this->horimeterResetConfirmed = false;
        $this->horimeterNotes = '';
        $this->horimeterPhoto = null;
        $this->horimeterSaved = false;
    }

    public function getLastHorimeterReadingProperty(): ?HorimeterReading
    {
        if (! $this->asset) {
            return null;
        }

        return HorimeterReading::query()->latestForAsset($this->asset->id)->first();
    }

    /**
     * Mesma regra do ApontamentoHorimetro::precisaConfirmarReset() -- so'
     * pra decidir se mostra o checkbox antes do submit. Quem trava de
     * verdade e' o HorimeterReadingObserver.
     */
    public function getHorimeterNeedsResetConfirmProperty(): bool
    {
        $ultima = $this->lastHorimeterReading;

        if (! $ultima || ! is_numeric($this->horimeterReading)) {
            return false;
        }

        $atual = (float) $this->horimeterReading;
        $anterior = (float) $ultima->reading;
        $threshold = (float) config('oravel.horimeter_jump_threshold', 500);

        return $atual < $anterior || ($atual - $anterior) > $threshold;
    }

    public function saveHorimeterReading(): void
    {
        if (! $this->asset) {
            return;
        }

        Gate::authorize('view', $this->asset);

        $this->validate([
            'horimeterReading' => ['required', 'numeric', 'min:0'],
            'horimeterPhoto' => ['nullable', 'image', 'max:5120'],
            'horimeterNotes' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'horimeterReading' => 'leitura',
            'horimeterPhoto' => 'foto',
        ]);

        $photoPath = null;
        if ($this->horimeterPhoto) {
            $photoPath = $this->horimeterPhoto->store('horimeter-readings', 'public');
        }

        try {
            HorimeterReading::create([
                'tenant_id' => $this->asset->tenant_id,
                'asset_id' => $this->asset->id,
                'reading' => $this->horimeterReading,
                'recorded_at' => now(),
                'recorded_by' => auth()->id(),
                'source' => HorimeterReading::SOURCE_MANUAL,
                'reset_confirmed' => $this->horimeterResetConfirmed,
                'notes' => $this->horimeterNotes ?: null,
                'photo_path' => $photoPath,
            ]);
        } catch (ValidationException $e) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $e;
        }

        $this->resetHorimeterForm();
        $this->horimeterSaved = true;

        // Horimetro_atual do ativo ja' foi atualizado pelo Observer -- so'
        // falta recarregar a instancia que esta tela segura em memoria, sem
        // isso "Horas Trabalhadas" continuaria mostrando o valor antigo ate'
        // a pagina inteira recarregar (mesma causa do bug de custo de
        // material corrigido no wizard).
        $this->asset = $this->asset->fresh();
    }

    public function render()
    {
        return view('livewire.asset-dossier-mobile');
    }
}
