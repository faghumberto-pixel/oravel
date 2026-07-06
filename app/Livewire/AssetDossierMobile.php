<?php

namespace App\Livewire;

use App\Filament\Concerns\HasAssetDossierData;
use App\Models\Asset;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Versao pro campo/patio (tecnico no celular) do Dossie Rapido do Ativo --
 * mesma fonte de dados que a versao desktop (App\Filament\Pages\AssetDossier),
 * via HasAssetDossierData, mas visual proprio (tema escuro, toques grandes,
 * sem o shell do painel Filament). E o destino do QR code do ativo.
 */
#[Layout('layouts.checklist-mobile')]
class AssetDossierMobile extends Component
{
    use HasAssetDossierData;

    public ?Asset $asset = null;

    public string $query = '';

    /** @var array<int, array{id: string, name: string, patrimonio: ?string, tag: ?string}> */
    public array $searchResults = [];

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
    }

    public function clear(): void
    {
        $this->asset = null;
        $this->searchResults = [];
        $this->query = '';
    }

    public function render()
    {
        return view('livewire.asset-dossier-mobile');
    }
}
