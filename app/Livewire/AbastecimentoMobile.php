<?php

namespace App\Livewire;

use App\Livewire\Concerns\EscolheVeiculoDoApp;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaAbastecimento;
use App\Services\Frota\AbastecimentoService;
use App\Services\Frota\VinculoMotoristaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Abastecimento pelo celular (Logística): escolhe o veículo, informa km, litros e valor; o km do veículo é marcado junto. */
#[Layout('layouts.logistica-mobile')]
class AbastecimentoMobile extends Component
{
    use EscolheVeiculoDoApp;

    public string $dataHora = '';

    public string $odometro = '';

    public string $combustivel = 'diesel_s10';

    public string $litros = '';

    public string $valorTotal = '';

    public bool $tanqueCheio = true;

    public string $origem = 'externo';

    public string $posto = '';

    public string $notaFiscal = '';

    public ?string $motoristaId = null;

    public string $observacoes = '';

    public string $justificativaOdometro = '';

    public function mount(?string $assetId = null): void
    {
        Gate::authorize('viewAny', FrotaAbastecimento::class);
        $this->iniciarVeiculo($assetId);
    }

    protected function aoEscolherVeiculo(Asset $ativo): void
    {
        $this->reset(['litros', 'valorTotal', 'posto', 'notaFiscal', 'observacoes', 'justificativaOdometro', 'erro']);
        $this->tanqueCheio = true;
        $this->origem = 'externo';
        $this->dataHora = now()->format('Y-m-d\TH:i');
        $this->odometro = (string) (int) floor((float) $ativo->odometro_atual);
        $this->motoristaId = VinculoMotoristaService::titular($ativo)?->motorista_id;
    }

    public function registrar(): void
    {
        Gate::authorize('create', FrotaAbastecimento::class);
        $this->mensagem = $this->erro = null;

        try {
            $ativo = Asset::findOrFail($this->ativoId);
            $registro = app(AbastecimentoService::class)->registrar($ativo, [
                'abastecido_em' => $this->dataHora ?: null, 'odometro' => (int) $this->odometro, 'combustivel' => $this->combustivel,
                'litros' => $this->litros, 'valor_total' => $this->valorTotal, 'tanque_cheio' => $this->tanqueCheio, 'origem' => $this->origem,
                'posto' => $this->posto, 'nota_fiscal' => $this->notaFiscal, 'motorista_id' => $this->motoristaId ?: null,
                'observacoes' => $this->observacoes, 'justificativa_odometro' => $this->justificativaOdometro ?: null,
            ], auth()->user());

            $this->mensagem = 'Abastecimento registrado.'.($registro->consumo_km_l ? ' Consumo: '.number_format((float) $registro->consumo_km_l, 2, ',', '.').' km/l.' : '');
            $this->ativoId = null;
        } catch (ValidationException $e) {
            $this->erro = collect($e->errors())->flatten()->first();
        }
    }

    public function render()
    {
        $ativo = $this->ativoEscolhido();

        return view('livewire.logistica.abastecimento', [
            'ativo' => $ativo,
            'veiculos' => $ativo ? collect() : $this->veiculosParaEscolher(),
            'combustiveis' => FrotaAbastecimento::combustivelLabels(),
            'origens' => FrotaAbastecimento::origemLabels(),
            'motoristas' => $ativo ? FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all() : [],
            'ultimo' => $ativo ? FrotaAbastecimento::where('ativo_id', $ativo->id)->latest('abastecido_em')->first() : null,
        ]);
    }
}
