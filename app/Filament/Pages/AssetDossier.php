<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAssetDossierData;
use App\Models\Asset;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Dossie sintetizado de um Ativo: dados gerais, cliente atual, contrato
 * vigente, horas trabalhadas, Matriz ABC, avarias e OS abertas -- versao
 * desktop (buscar por patrimonio/nome/tag/serie na propria tela). Pro uso
 * no campo/patio via QR code, ver App\Livewire\AssetDossierMobile (mesma
 * fonte de dados via HasAssetDossierData, visual proprio pra celular). Ver
 * AssetDossierPdfController pra versao pra impressao.
 */
class AssetDossier extends Page
{
    use HasAssetDossierData;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Dossiê Rápido (QR/Patrimônio)';

    protected static ?string $title = 'Dossiê Rápido do Ativo';

    protected static ?string $slug = 'ativos/dossie/{assetId?}';

    protected static string $view = 'filament.pages.asset-dossier';

    // Nome diferente do parametro de mount() de proposito: com o mesmo
    // nome ("asset" pros dois), o Livewire tenta atribuir a string crua da
    // rota direto nessa propriedade (tipada ?Asset) antes do mount()
    // converter, quebrando com "Cannot assign string to property ... of
    // type ?Asset".
    public ?Asset $asset = null;

    public string $query = '';

    /** @var array<int, array{id: string, name: string, patrimonio: ?string, tag: ?string}> */
    public array $searchResults = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Asset::class);
    }

    public function mount(?string $assetId = null): void
    {
        if ($assetId) {
            $this->asset = Asset::find($assetId);
        }
    }

    /**
     * Busca flexivel (Asset::search(), nao so patrimonio exato): 1
     * resultado ja abre o dossie direto, mais de um mostra uma lista pra
     * escolher, zero avisa que nao achou nada.
     */
    public function search(): void
    {
        $this->validate(['query' => 'required|string']);
        $this->searchResults = [];

        $found = Asset::search($this->query);

        if ($found->isEmpty()) {
            Notification::make()
                ->title('Nenhum ativo encontrado.')
                ->body('Tente parte do patrimônio, nome, tag ou número de série.')
                ->danger()
                ->send();

            return;
        }

        if ($found->count() === 1) {
            $this->redirect(static::getUrl(['assetId' => $found->first()->id]));

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
        $this->redirect(static::getUrl(['assetId' => $assetId]));
    }

    public function clear(): void
    {
        $this->redirect(static::getUrl());
    }
}
