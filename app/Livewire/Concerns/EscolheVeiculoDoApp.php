<?php

namespace App\Livewire\Concerns;

use App\Models\Asset;
use Illuminate\Support\Collection;

/** Escolha do veículo nas telas de Logística do app: lista, busca (placa, patrimônio, nome) ou direto pelo QR (/{assetId}). */
trait EscolheVeiculoDoApp
{
    public ?string $ativoId = null;

    public string $buscaVeiculo = '';

    public ?string $mensagem = null;

    public ?string $erro = null;

    /** Aberto pelo app do colaborador (outro endereço): os links voltam para o app, não para o painel. */
    public bool $modoApp = false;

    protected function iniciarVeiculo(?string $assetId): void
    {
        $this->modoApp = (bool) request()->attributes->get('colab');

        if ($assetId) {
            $this->escolherVeiculo($assetId);
        }
    }

    public function escolherVeiculo(string $id): void
    {
        $ativo = Asset::findOrFail($id);
        abort_unless($ativo->isVehicle(), 404, 'Este ativo não é um veículo.');

        $this->ativoId = $ativo->id;
        $this->buscaVeiculo = '';
        $this->mensagem = $this->erro = null;
        $this->aoEscolherVeiculo($ativo);
    }

    public function trocarVeiculo(): void
    {
        $this->ativoId = null;
        $this->erro = null;
    }

    /** Gancho: preencher campos ao escolher o veículo (ex.: odômetro atual). */
    protected function aoEscolherVeiculo(Asset $ativo): void {}

    /** @return Collection<int, Asset> */
    protected function veiculosParaEscolher(): Collection
    {
        if (mb_strlen(trim($this->buscaVeiculo)) >= 2) {
            return Asset::search($this->buscaVeiculo)->filter(fn (Asset $a) => $a->isVehicle())->values();
        }

        return Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->naoBaixado()->orderBy('placa')->limit(40)->get();
    }

    protected function ativoEscolhido(): ?Asset
    {
        return $this->ativoId ? Asset::find($this->ativoId) : null;
    }

    protected function paraHub(): string
    {
        return $this->modoApp ? route('colab.logistica') : '/admin';
    }
}
