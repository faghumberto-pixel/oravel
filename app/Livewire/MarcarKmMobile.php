<?php

namespace App\Livewire;

use App\Livewire\Concerns\EscolheVeiculoDoApp;
use App\Models\Asset;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaSaidaVeiculo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Marcar o km do veículo pelo celular (Logística): leitura avulsa do odômetro, com histórico das últimas marcações. */
#[Layout('layouts.logistica-mobile')]
class MarcarKmMobile extends Component
{
    use EscolheVeiculoDoApp;

    public string $odometro = '';

    public string $justificativa = '';

    public function mount(?string $assetId = null): void
    {
        Gate::authorize('viewAny', FrotaSaidaVeiculo::class);
        $this->iniciarVeiculo($assetId);
    }

    protected function aoEscolherVeiculo(Asset $ativo): void
    {
        $this->justificativa = '';
        $this->odometro = (string) (int) floor((float) $ativo->odometro_atual);
    }

    public function marcar(): void
    {
        Gate::authorize('create', FrotaSaidaVeiculo::class);
        $this->mensagem = $this->erro = null;

        try {
            $ativo = Asset::findOrFail($this->ativoId);
            if (! is_numeric($this->odometro) || (int) $this->odometro < 0) {
                throw ValidationException::withMessages(['odometro' => 'Informe o km do painel do veículo.']);
            }
            FrotaLeituraOdometro::registrar($ativo, (int) $this->odometro, 'manual', null, $this->justificativa ?: null, auth()->id());

            $this->mensagem = 'Km marcado: '.number_format((int) $this->odometro, 0, ',', '.').' km.';
            $this->ativoId = null;
        } catch (ValidationException $e) {
            $this->erro = collect($e->errors())->flatten()->first();
        }
    }

    public function render()
    {
        $ativo = $this->ativoEscolhido();

        return view('livewire.logistica.km', [
            'ativo' => $ativo,
            'veiculos' => $ativo ? collect() : $this->veiculosParaEscolher(),
            'leituras' => $ativo ? FrotaLeituraOdometro::where('ativo_id', $ativo->id)->latest('lido_em')->limit(5)->get() : collect(),
        ]);
    }
}
