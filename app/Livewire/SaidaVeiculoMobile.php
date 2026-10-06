<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaSaidaVeiculo;
use App\Services\Frota\SaidaVeiculoService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Entrada e saída de veículos pelo celular (Logística): lista os veículos, registra a saída e a volta. */
#[Layout('layouts.checklist-mobile')]
class SaidaVeiculoMobile extends Component
{
    /** Veículo escolhido (null = lista). */
    public ?string $ativoId = null;

    public string $finalidade = 'visita_tecnica';

    public ?string $motoristaId = null;

    public string $condutorNome = '';

    public string $destino = '';

    public string $motivo = '';

    public string $dataHora = '';

    public string $odometro = '';

    public string $combustivel = '';

    public string $observacoes = '';

    public string $justificativaOdometro = '';

    public ?string $mensagem = null;

    public ?string $erro = null;

    public function mount(?string $assetId = null): void
    {
        Gate::authorize('viewAny', FrotaSaidaVeiculo::class);
        if ($assetId) {
            $this->escolher($assetId);
        }
    }

    public function escolher(string $id): void
    {
        $ativo = Asset::findOrFail($id);
        abort_unless($ativo->isVehicle(), 404, 'Este ativo não é um veículo.');
        $this->ativoId = $ativo->id;
        $this->reiniciarCampos();
        $this->odometro = (string) (int) floor((float) $ativo->odometro_atual);
    }

    public function voltar(): void
    {
        $this->ativoId = null;
        $this->erro = null;
    }

    private function reiniciarCampos(): void
    {
        $this->reset(['finalidade', 'motoristaId', 'condutorNome', 'destino', 'motivo', 'combustivel', 'observacoes', 'justificativaOdometro', 'erro']);
        $this->dataHora = now()->format('Y-m-d\TH:i');
    }

    public function saida(): void
    {
        Gate::authorize('create', FrotaSaidaVeiculo::class);
        $this->executar(function (Asset $ativo) {
            app(SaidaVeiculoService::class)->registrarSaida($ativo, [
                'finalidade' => $this->finalidade, 'motorista_id' => $this->motoristaId ?: null, 'condutor_nome' => $this->condutorNome,
                'destino' => $this->destino, 'motivo' => $this->motivo, 'saida_em' => $this->dataHora ?: null, 'odometro' => (int) $this->odometro,
                'combustivel' => $this->combustivel ?: null, 'observacoes' => $this->observacoes, 'justificativa_odometro' => $this->justificativaOdometro ?: null,
            ], auth()->user());

            return 'Saída registrada.';
        });
    }

    public function entrada(): void
    {
        Gate::authorize('create', FrotaSaidaVeiculo::class);
        $this->executar(function (Asset $ativo) {
            $aberta = $ativo->saidaAberta();
            abort_unless($aberta, 404, 'Este veículo não está fora.');
            app(SaidaVeiculoService::class)->registrarEntrada($aberta, [
                'retorno_em' => $this->dataHora ?: null, 'odometro' => (int) $this->odometro, 'combustivel' => $this->combustivel ?: null,
                'observacoes' => $this->observacoes, 'justificativa_odometro' => $this->justificativaOdometro ?: null,
            ], auth()->user());

            return 'Entrada registrada.';
        });
    }

    private function executar(\Closure $acao): void
    {
        $this->mensagem = $this->erro = null;

        try {
            $ativo = Asset::findOrFail($this->ativoId);
            $this->mensagem = $acao($ativo);
            $this->ativoId = null;
        } catch (ValidationException $e) {
            $this->erro = collect($e->errors())->flatten()->first();
        }
    }

    public function render()
    {
        $ativo = $this->ativoId ? Asset::find($this->ativoId) : null;
        $veiculos = $ativo ? collect() : Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->orderBy('placa')->get();
        $fora = FrotaSaidaVeiculo::fora()->with('motorista')->get()->keyBy('ativo_id');

        return view('livewire.saida-veiculo-mobile', [
            'ativo' => $ativo,
            'aberta' => $ativo ? $fora->get($ativo->id) : null,
            'veiculos' => $veiculos,
            'fora' => $fora,
            'finalidades' => FrotaSaidaVeiculo::finalidadeLabels(),
            'motoristas' => $ativo ? FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all() : [],
        ]);
    }
}
