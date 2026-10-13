<?php

namespace App\Livewire;

use App\Livewire\Concerns\EscolheVeiculoDoApp;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaVinculoMotorista;
use App\Services\Frota\VinculoMotoristaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Motorista titular do veículo pelo celular (Logística): incluir o primeiro, substituir ou encerrar o vínculo. */
#[Layout('layouts.logistica-mobile')]
class MotoristaVeiculoMobile extends Component
{
    use EscolheVeiculoDoApp;

    public ?string $motoristaId = null;

    public string $inicio = '';

    public string $observacoes = '';

    public function mount(?string $assetId = null): void
    {
        Gate::authorize('viewAny', FrotaVinculoMotorista::class);
        $this->iniciarVeiculo($assetId);
    }

    protected function aoEscolherVeiculo(Asset $ativo): void
    {
        $this->reset(['motoristaId', 'observacoes']);
        $this->inicio = now()->format('Y-m-d');
    }

    public function atribuir(): void
    {
        Gate::authorize('create', FrotaVinculoMotorista::class);
        $this->mensagem = $this->erro = null;

        try {
            $ativo = Asset::findOrFail($this->ativoId);
            $atual = VinculoMotoristaService::titular($ativo);
            $novo = app(VinculoMotoristaService::class)->atribuir($ativo, (string) $this->motoristaId, $this->inicio ?: null, $this->observacoes ?: null, auth()->user());

            $this->mensagem = $atual ? 'Motorista substituído: agora é '.$novo->motorista?->name.'.' : $novo->motorista?->name.' é o motorista do veículo.';
            $this->ativoId = null;
        } catch (ValidationException $e) {
            $this->erro = collect($e->errors())->flatten()->first();
        }
    }

    public function encerrar(): void
    {
        Gate::authorize('create', FrotaVinculoMotorista::class);
        $this->mensagem = $this->erro = null;

        try {
            $ativo = Asset::findOrFail($this->ativoId);
            $atual = VinculoMotoristaService::titular($ativo);
            abort_unless($atual, 404, 'Este veículo não tem motorista titular.');
            app(VinculoMotoristaService::class)->encerrar($atual, $this->inicio ?: null);

            $this->mensagem = 'Vínculo encerrado: o veículo ficou sem motorista titular.';
            $this->ativoId = null;
        } catch (ValidationException $e) {
            $this->erro = collect($e->errors())->flatten()->first();
        }
    }

    public function render()
    {
        $ativo = $this->ativoEscolhido();

        return view('livewire.logistica.motorista', [
            'ativo' => $ativo,
            'veiculos' => $ativo ? collect() : $this->veiculosParaEscolher(),
            'atual' => $ativo ? VinculoMotoristaService::titular($ativo)?->load('motorista') : null,
            'historico' => $ativo ? FrotaVinculoMotorista::where('ativo_id', $ativo->id)->whereNotNull('fim')->with('motorista')->latest('fim')->limit(5)->get() : collect(),
            'motoristas' => $ativo ? FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all() : [],
        ]);
    }
}
